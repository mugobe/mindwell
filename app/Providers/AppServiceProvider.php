<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Session prices set by an admin (Admin → Plans & pricing → Session prices,
        // 2026-11-01) override the config/.env defaults. Every price read in the app goes
        // through config('mindwell.*_session_price'), so this one place is enough.
        // Until an admin saves a price, the config value (and .env) still applies.
        $video = \App\Models\AppSetting::get('prices.video_session');
        $inPerson = \App\Models\AppSetting::get('prices.in_person_session');
        if (is_numeric($video)) {
            config(['mindwell.one_off_session_price' => (float) $video]);
        }
        if (is_numeric($inPerson)) {
            config(['mindwell.in_person_session_price' => (float) $inPerson]);
        }
    }
}
