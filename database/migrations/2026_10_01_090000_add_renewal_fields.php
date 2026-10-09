<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription renewals (2026-10-01) — see App\Services\SubscriptionRenewals.
 *
 * client_subscriptions
 *  - renewal_paid_at: the client has already paid for their NEXT period (renewed early).
 *    The period rolls over on current_period_end; until then their current credits
 *    stay exactly as they are.
 *  - renewal_reminder_sent_at / renewal_due_sent_at / expired_notice_sent_at: stamps so
 *    each reminder goes out once per period (cleared again when a period rolls over).
 *
 * payment_transactions
 *  - client_subscription_id: set only on a RENEWAL payment, so the confirmation knows to
 *    extend that subscription instead of starting a new one.
 *
 * Every column is guarded with hasColumn so re-running is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('client_subscriptions', 'renewal_paid_at')) {
                $table->timestamp('renewal_paid_at')->nullable()->after('current_period_end');
            }
            if (! Schema::hasColumn('client_subscriptions', 'renewal_reminder_sent_at')) {
                $table->timestamp('renewal_reminder_sent_at')->nullable();
            }
            if (! Schema::hasColumn('client_subscriptions', 'renewal_due_sent_at')) {
                $table->timestamp('renewal_due_sent_at')->nullable();
            }
            if (! Schema::hasColumn('client_subscriptions', 'expired_notice_sent_at')) {
                $table->timestamp('expired_notice_sent_at')->nullable();
            }
        });

        if (! Schema::hasColumn('payment_transactions', 'client_subscription_id')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->foreignUuid('client_subscription_id')->nullable()->after('subscription_plan_id')
                    ->constrained('client_subscriptions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_transactions', 'client_subscription_id')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_subscription_id');
            });
        }

        Schema::table('client_subscriptions', function (Blueprint $table) {
            foreach (['renewal_paid_at', 'renewal_reminder_sent_at', 'renewal_due_sent_at', 'expired_notice_sent_at'] as $column) {
                if (Schema::hasColumn('client_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
