<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // How many sessions one billing period of this plan entitles the client to.
            // Lets a subscription act as a bucket of "session credits" instead of unlimited access.
            $table->unsignedInteger('sessions_included')->default(1)->after('billing_interval');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('sessions_included');
        });
    }
};
