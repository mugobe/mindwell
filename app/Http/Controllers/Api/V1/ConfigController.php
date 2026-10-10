<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ClientDetailsController;
use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\IntakeQuestions;
use Illuminate\Http\JsonResponse;

/**
 * Mobile API v1 — public settings the app needs before (and after) sign-in (2026-10-09):
 * prices, session length, crisis contacts, links to Terms/Privacy, the mood scale,
 * genders and languages. No secrets.
 */
class ConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $crisis = config('mindwell.crisis');

        $contacts = [];
        if (! empty($crisis['line_number'])) {
            $contacts[] = [
                'name' => $crisis['line_name'] ?: 'Crisis line',
                'number' => $crisis['line_number'],
                'dial' => preg_replace('/[^0-9+]/', '', $crisis['line_number']),
                'hours' => $crisis['line_hours'],
                'primary' => true,
            ];
        }
        $contacts[] = ['name' => 'Emergency (Uganda)', 'number' => '999 or 112', 'dial' => '112', 'hours' => 'Free · 24 hours, every day', 'primary' => empty($crisis['line_number'])];
        $contacts[] = ['name' => 'Mental Health Uganda', 'number' => '0800 21 21 21', 'dial' => '+256800212121', 'hours' => 'Toll-free · Monday to Friday, 8:30am – 5:00pm', 'primary' => false];

        return response()->json([
            'app' => [
                'name' => config('app.name', 'Mindwell'),
                'api_version' => 1,
                'min_app_version' => '1.0.0',
                'website' => url('/'),
            ],
            'currency' => config('flutterwave.currency', 'UGX'),
            'prices' => [
                'video_session' => (float) config('mindwell.one_off_session_price'),
                'in_person_session' => (float) config('mindwell.in_person_session_price'),
            ],
            'session_minutes' => (int) config('mindwell.session_duration_minutes', 70),
            'video' => [
                'join_early_minutes' => (int) config('mindwell.video_join_early_minutes', 10),
                'join_late_minutes' => (int) config('mindwell.video_join_late_minutes', 30),
            ],
            'lobby_target_minutes' => (int) config('mindwell.lobby_target_minutes', 10),
            'min_client_age' => (int) config('mindwell.min_client_age', 18),
            'crisis' => [
                'contacts' => $contacts,
                'note' => 'You can also go straight to the emergency department of your nearest hospital, or ask someone you trust to stay with you. If you\'re thinking about ending your life, please tell someone now — you don\'t have to go through this alone.',
            ],
            'legal' => [
                'version' => config('mindwell.legal.version'),
                'is_draft' => (bool) config('mindwell.legal.is_draft'),
                'terms_url' => route('terms'),
                'privacy_url' => route('privacy'),
                'urgent_help_url' => route('urgent-help'),
                'forgot_password_url' => rescue(fn () => route('password.request'), url('/forgot-password'), false),
            ],
            'moods' => collect(JournalEntry::MOODS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'genders' => collect(User::GENDERS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'languages' => ClientDetailsController::LANGUAGES,
            'mobile_money_networks' => [['value' => 'MTN', 'label' => 'MTN Mobile Money'], ['value' => 'AIRTEL', 'label' => 'Airtel Money']],
            'intake' => [
                'about_sections' => IntakeQuestions::enabled(),
            ],
        ]);
    }
}
