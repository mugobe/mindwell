<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MarzPay alongside Flutterwave (2026-11-02).
 *
 *  - gateway: which service started this payment. Existing rows are Flutterwave.
 *  - gateway_reference: the reference WE send to MarzPay. MarzPay insists on a UUID v4
 *    unique per attempt, which our MW-XXXXXXXXXX tx_ref is not, so it gets its own
 *    column and tx_ref stays as it is (receipts, admin screens).
 *
 * The service's own transaction id keeps living in flw_transaction_id (for MarzPay
 * that is data.transaction.uuid), so every existing "has a charge been started?" check
 * keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('gateway', 20)->default('flutterwave')->after('tx_ref');
            $table->string('gateway_reference', 64)->nullable()->unique()->after('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropUnique(['gateway_reference']);
            $table->dropColumn(['gateway', 'gateway_reference']);
        });
    }
};
