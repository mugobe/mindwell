<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use App\Services\PandoraSms;
use App\Support\UgandaPhone;
use Illuminate\Console\Command;

/**
 * Sends one test SMS through Pandora and shows exactly what Pandora answered — the
 * quickest way to confirm the PANDORA_SMS_* settings (added 2026-09-30).
 *
 *   php artisan mindwell:sms-test 0772123456
 */
class SmsTest extends Command
{
    protected $signature = 'mindwell:sms-test {number : A Ugandan mobile number, e.g. 0772123456}';

    protected $description = 'Send a test SMS through Pandora and show the response';

    public function handle(Notifier $notifier, PandoraSms $sms): int
    {
        $number = UgandaPhone::normalize($this->argument('number'));
        if (! $number) {
            $this->error(UgandaPhone::MESSAGE);

            return self::FAILURE;
        }

        if (! $sms->isConfigured()) {
            $this->warn('PANDORA_SMS_USERNAME / PANDORA_SMS_PASSWORD are not set in .env — nothing will actually be sent (it goes to storage/logs/laravel.log).');
        }

        $this->line('Endpoint:      '.config('pandora.endpoint').' ('.strtoupper((string) config('pandora.method')).')');
        $this->line('Sender:        '.config('pandora.sender'));
        $this->line('Number sent as: '.UgandaPhone::format($number, (string) config('pandora.number_format')).'  (PANDORA_SMS_NUMBER_FORMAT='.config('pandora.number_format').')');

        $result = $notifier->testSms($number);

        $result['ok']
            ? $this->info('Pandora accepted it: '.$result['detail'])
            : $this->error('Not sent: '.$result['detail']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
