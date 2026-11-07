<?php

namespace App\Console\Commands;

use App\Services\Checkout;
use App\Services\MarzPayClient;
use App\Services\MarzPayException;
use Illuminate\Console\Command;

/**
 * Talks to MarzPay with the keys in .env and prints what it answers (2026-11-02) — the
 * way to learn how the sandbox really behaves before switching PAYMENT_GATEWAY.
 * Never prints the keys. Phone numbers are masked so the output can be shared.
 *
 *   php artisan marzpay:check                          keys work? what's subscribed?
 *   php artisan marzpay:check --collect=0772123456     start a UGX 500 test collection
 *                              [--amount=1000] [--wait=120]  and watch its status
 *   php artisan marzpay:check --status=<uuid>          one status check
 *   php artisan marzpay:check --log                    last callbacks/calls logged
 *
 * A --collect test is NOT a Mindwell payment: nothing in the database changes. Its
 * callback still reaches /webhooks/marzpay and is written to storage/logs/marzpay.log
 * when MARZPAY_LOG_PAYLOADS=true, which shows us the real callback and signature.
 */
class MarzPayCheck extends Command
{
    protected $signature = 'marzpay:check
        {--collect= : Start a test collection from this Ugandan number}
        {--amount=500 : Amount in UGX for --collect (MarzPay minimum is 500)}
        {--wait=90 : Seconds to keep checking the status after --collect}
        {--status= : Check one collection by its MarzPay uuid}
        {--log : Show the end of storage/logs/marzpay.log}
        {--raw : Print full response bodies}
        {--live : Allow --collect when MARZPAY_SANDBOX=false (REAL money)}';

    protected $description = 'Check the MarzPay keys and sandbox, and show exactly what MarzPay answers';

    public function handle(MarzPayClient $marz): int
    {
        if ($this->option('log')) {
            return $this->showLog();
        }

        $this->summary($marz);

        if (! $marz->isConfigured()) {
            $this->error('Add MARZPAY_API_KEY and MARZPAY_API_SECRET to .env, then run: php artisan optimize');

            return self::FAILURE;
        }

        try {
            if ($uuid = $this->option('status')) {
                return $this->status($marz, $uuid);
            }

            if ($number = $this->option('collect')) {
                return $this->collect($marz, $number);
            }

            return $this->basics($marz);
        } catch (MarzPayException $e) {
            $this->error('MarzPay: '.MarzPayClient::maskPhones($e->getMessage()));
            $this->line('HTTP '.$e->httpStatus.($e->errorCode ? ' · '.$e->errorCode : ''));

            return self::FAILURE;
        }
    }

    private function summary(MarzPayClient $marz): void
    {
        $this->line('<options=bold>MarzPay settings</>');
        $this->line('  PAYMENT_GATEWAY        '.config('payments.gateway').(config('payments.gateway') === 'marzpay' ? '' : '  (new payments still use Flutterwave)'));
        $this->line('  API base               '.config('marzpay.base_url'));
        $this->line('  API key / secret       '.($marz->isConfigured() ? 'set' : 'MISSING'));
        $this->line('  Webhook secret         '.(filled(config('marzpay.webhook_secret')) ? 'set (callbacks must be signed)' : 'not set (callbacks accepted, always re-checked)'));
        $this->line('  Callback URL           '.(Checkout::marzPayCallbackUrl() ?? 'none (not https, so MarzPay cannot call back here)'));
        $this->line('  Sandbox                '.(config('marzpay.sandbox') ? 'yes (MARZPAY_SANDBOX=true)' : 'NO, live money'));
        $this->line('  Log payloads           '.(config('marzpay.log_payloads') ? 'yes → storage/logs/marzpay.log' : 'no'));
        $this->newLine();
    }

    private function basics(MarzPayClient $marz): int
    {
        $services = $marz->services();
        $this->line('<options=bold>GET /services</> → HTTP '.$services['http']);
        $this->show($services['body'], true);

        $collect = $marz->collectServices();
        $this->line('<options=bold>GET /collect-money/services</> → HTTP '.$collect['http']);
        $this->show($collect['body'], true);

        if ($services['http'] === 401) {
            $this->error('401: the key or secret is wrong (copy them again from Dashboard → API Keys).');

            return self::FAILURE;
        }

        // 2026-11-03 (first live test): the keys worked but nothing was subscribed, so every
        // collection was refused. Say so plainly instead of leaving it buried in the JSON.
        $collections = collect($services['body']['data']['services'] ?? [])
            ->filter(fn ($s) => ($s['service']['type'] ?? null) === 'collection' && in_array($s['service']['provider'] ?? '', ['MTN', 'Airtel'], true));
        $this->line('<options=bold>Mobile Money collections</>');
        foreach ($collections as $s) {
            $on = (bool) ($s['availability']['is_subscribed'] ?? false);
            $this->line('  '.str_pad($s['service']['name'], 22).($on ? '<info>subscribed</info>' : '<error> NOT subscribed </error>'));
        }
        if ($collections->isNotEmpty() && $collections->every(fn ($s) => ! ($s['availability']['is_subscribed'] ?? false))) {
            $this->newLine();
            $this->error('Payments will be refused until you subscribe to MTN Collection and Airtel Collection');
            $this->line('in the MarzPay dashboard (Services / Marketplace). Then run this check again.');

            return self::FAILURE;
        }

        $this->info('Keys work and collections are on. Next: php artisan marzpay:check --collect=07XXXXXXXX --amount=500 --live');

        return self::SUCCESS;
    }

    private function collect(MarzPayClient $marz, string $number): int
    {
        if (! config('marzpay.sandbox') && ! $this->option('live')) {
            $this->error('MARZPAY_SANDBOX=false: this would move REAL money. Add --live if you really mean it.');

            return self::FAILURE;
        }

        $phone = MarzPayClient::e164($number);
        if (! $phone) {
            $this->error('Enter a Ugandan mobile number, e.g. 0772123456.');

            return self::FAILURE;
        }

        $amount = (int) $this->option('amount');
        $reference = MarzPayClient::uuid4();

        $this->line('<options=bold>POST /collect-money</>  UGX '.number_format($amount).' from '.MarzPayClient::maskPhones($phone));
        $this->line('  our reference  '.$reference);

        $started = $marz->collect($reference, $amount, $phone, (string) config('marzpay.description'),
            [['mindwell_test' => 'marzpay:check']], Checkout::marzPayCallbackUrl());

        $this->line('  MarzPay uuid   '.$started['uuid']);
        $this->line('  status         '.$started['status'].($started['sandbox'] ? '  (sandbox)' : ''));
        $this->line('  message        '.($started['message'] ?? '—'));
        $this->newLine();

        $deadline = time() + max(0, (int) $this->option('wait'));
        $last = null;
        $final = null;

        $this->line('Checking the status every 5 seconds for '.(int) $this->option('wait').'s (approve the prompt on the phone if one comes)…');

        do {
            sleep(5);
            try {
                $now = $marz->collection($started['uuid']);
            } catch (MarzPayException $e) {
                if ($e->errorCode === 'NOT_FOUND' && $started['sandbox']) {
                    $this->warn('  '.date('H:i:s').'  MarzPay: "Transaction not found." The sandbox does not keep test payments,');
                    $this->warn('            so their status can never be looked up. Mindwell treats this as "still waiting";');
                    $this->warn('            use the Test mode buttons, or a signed sandbox callback on the test site.');

                    break;
                }
                $this->warn('  '.date('H:i:s').'  status check failed: '.$e->getMessage());

                continue;
            }

            if ($now['marzpay_status'] !== $last) {
                $this->line('  '.date('H:i:s').'  '.$now['marzpay_status'].'  → Mindwell reads it as: '.$now['status']);
                $last = $now['marzpay_status'];
            }

            if (in_array($now['status'], ['succeeded', 'failed', 'cancelled'], true)) {
                $final = $now;
                break;
            }
        } while (time() < $deadline);

        $this->newLine();
        $this->line('<options=bold>What Mindwell would store</>');
        $this->show($final ?? ($now ?? []), true);

        $this->line('<options=bold>Raw answers</>');
        $this->line('GET /collect-money/'.$started['uuid']);
        $this->show($marz->raw('get', '/collect-money/'.$started['uuid'])['body'], true);
        $this->line('GET /transactions/'.$started['uuid']);
        $this->show($marz->raw('get', '/transactions/'.$started['uuid'])['body'], true);

        if (! $final) {
            $this->warn('Still not final after the wait. In sandbox that may simply be how MarzPay behaves. Check again later with:');
            $this->line('  php artisan marzpay:check --status='.$started['uuid']);
        }

        $this->line('Callback: if MarzPay calls back, it shows up in: php artisan marzpay:check --log');

        return self::SUCCESS;
    }

    private function status(MarzPayClient $marz, string $uuid): int
    {
        $this->line('<options=bold>Status of '.$uuid.'</>');
        $this->show($marz->collection($uuid), true);

        if ($this->option('raw')) {
            $this->show($marz->raw('get', '/collect-money/'.$uuid)['body'], true);
            $this->show($marz->raw('get', '/transactions/'.$uuid)['body'], true);
        }

        return self::SUCCESS;
    }

    private function showLog(): int
    {
        $path = storage_path('logs/marzpay.log');
        if (! is_file($path)) {
            $this->warn('No storage/logs/marzpay.log yet. Set MARZPAY_LOG_PAYLOADS=true, run php artisan optimize, and try again.');

            return self::SUCCESS;
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -40);
        foreach ($lines as $line) {
            $this->line(MarzPayClient::maskPhones(mb_strimwidth($line, 0, 4000, '…')));
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function show(array $data, bool $always = false): void
    {
        if (! $always && ! $this->option('raw')) {
            return;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->line(MarzPayClient::maskPhones((string) $json));
        $this->newLine();
    }
}
