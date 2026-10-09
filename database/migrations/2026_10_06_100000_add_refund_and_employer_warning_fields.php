<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin money tools & reminders (2026-10-06, roadmap 22, 27).
 *
 * payment_transactions — refunds are RECORDED by an admin after they send the money
 *   back themselves (decided with Collins); `status` stays 'successful' and these
 *   columns say how much came back, when, why and who recorded it.
 *
 * employer_accounts.expiry_warning_sent_at — the "contract ends in 30 days" warning
 *   goes out once per renewal date.
 *
 * Guarded with hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_transactions', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (! Schema::hasColumn('payment_transactions', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('payment_transactions', 'refund_reason')) {
                $table->string('refund_reason', 500)->nullable();
            }
            if (! Schema::hasColumn('payment_transactions', 'refunded_by')) {
                $table->foreignUuid('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('employer_accounts', 'expiry_warning_sent_at')) {
            Schema::table('employer_accounts', function (Blueprint $table) {
                $table->timestamp('expiry_warning_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_transactions', 'refunded_by')) {
                $table->dropConstrainedForeignId('refunded_by');
            }
            foreach (['refunded_at', 'refund_amount', 'refund_reason'] as $column) {
                if (Schema::hasColumn('payment_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('employer_accounts', 'expiry_warning_sent_at')) {
            Schema::table('employer_accounts', function (Blueprint $table) {
                $table->dropColumn('expiry_warning_sent_at');
            });
        }
    }
};
