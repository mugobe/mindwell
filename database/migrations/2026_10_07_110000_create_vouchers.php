<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers / discount codes (2026-10-07, roadmap 25). Every rule is a setting on the
 * voucher itself, edited by admin — nothing is fixed in code (Collins: "these are
 * things that are subject to change").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('description')->nullable();
                $table->string('discount_type', 10);            // percent | fixed
                $table->decimal('discount_value', 12, 2);
                $table->decimal('max_discount', 12, 2)->nullable(); // cap for percent vouchers
                $table->decimal('min_amount', 12, 2)->nullable();   // only on payments at least this big
                $table->boolean('applies_to_sessions')->default(true);
                $table->boolean('applies_to_plans')->default(false);
                $table->boolean('applies_to_renewals')->default(false);
                $table->boolean('new_clients_only')->default(false);
                $table->foreignUuid('client_user_id')->nullable()->constrained('users')->nullOnDelete(); // one named client
                $table->unsignedInteger('max_uses_total')->nullable();
                $table->unsignedInteger('max_uses_per_client')->nullable()->default(1);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignUuid('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
                $table->foreignUuid('payment_transaction_id')->nullable()->unique()->constrained('payment_transactions')->nullOnDelete();
                $table->string('purpose', 20);                   // session | plan | renewal
                $table->decimal('original_amount', 12, 2);
                $table->decimal('discount_amount', 12, 2);
                $table->timestamps();
            });
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_transactions', 'voucher_id')) {
                $table->foreignUuid('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            }
            if (! Schema::hasColumn('payment_transactions', 'original_amount')) {
                $table->decimal('original_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('payment_transactions', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_transactions', 'voucher_id')) {
                $table->dropConstrainedForeignId('voucher_id');
            }
            foreach (['original_amount', 'discount_amount'] as $col) {
                if (Schema::hasColumn('payment_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
