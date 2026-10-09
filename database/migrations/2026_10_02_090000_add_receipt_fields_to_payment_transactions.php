<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment history & receipts (2026-10-02) — see App\Services\Receipts.
 *
 *  - receipt_number: MW-2026-00001 style, given out once a payment succeeds (older
 *    payments get one the first time their receipt is opened).
 *  - method_label: how it was paid, e.g. "MTN Mobile Money · 0772 ••• 456", recorded when
 *    the charge starts (the network/phone weren't stored anywhere before this).
 *  - receipt_emailed_at: so a receipt email only ever goes out once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_transactions', 'receipt_number')) {
                $table->string('receipt_number', 30)->nullable()->unique()->after('tx_ref');
            }
            if (! Schema::hasColumn('payment_transactions', 'method_label')) {
                $table->string('method_label', 80)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('payment_transactions', 'receipt_emailed_at')) {
                $table->timestamp('receipt_emailed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_transactions', 'receipt_number')) {
                $table->dropUnique(['receipt_number']);
                $table->dropColumn('receipt_number');
            }
            foreach (['method_label', 'receipt_emailed_at'] as $column) {
                if (Schema::hasColumn('payment_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
