<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * The "About you" part of intake (2026-10-09, roadmap 63). Five sections, each switched
 * on or off by an admin (Admin → Setup → Intake questions). Every section that is on can
 * be skipped by the client — decided with Collins so the intake never feels too long.
 *
 * The topic and goal lists are editable by admin too (one per line). Answers are stored
 * as the label text, so renaming a topic later never breaks old answers.
 */
class IntakeQuestions
{
    public const SECTIONS = [
        'topics' => ['label' => 'What brings you here', 'hint' => 'Tap-to-pick topics plus "in your own words".'],
        'mood' => ['label' => 'How you feel right now', 'hint' => 'One tap on a 5-face scale. Also saved as their first mood entry.'],
        'background' => ['label' => 'Background', 'hint' => 'Counselling before, mental-health medication, anything important to know.'],
        'goals' => ['label' => 'Goals', 'hint' => 'What they hope to get from therapy (pick + own words).'],
        'preferences' => ['label' => 'Preferences', 'hint' => 'Therapist gender, language, video or in person. Shown to the team; not used for matching.'],
    ];

    public const DEFAULT_TOPICS = [
        'Anxiety or worry',
        'Low mood or depression',
        'Stress or burnout',
        'Relationships or family',
        'Grief or loss',
        'Trauma or a painful experience',
        'Work or school',
        'Sleep',
        'Self-esteem or confidence',
        'Alcohol or drug use',
        'Anger',
        'Something else',
    ];

    public const DEFAULT_GOALS = [
        'Someone to listen',
        'Practical tools to cope',
        'Understand myself better',
        'Feel less anxious',
        'Improve my mood',
        'Better relationships',
        'Help making a decision',
        'Not sure yet',
    ];

    public const COUNSELLING = ['never' => 'No, this is my first time', 'past' => 'Yes, in the past', 'now' => 'Yes, I see someone now'];

    public const MEDICATION = ['no' => 'No', 'yes' => 'Yes', 'prefer_not' => 'Prefer not to say'];

    public const PREF_GENDER = ['any' => 'No preference', 'female' => 'A woman', 'male' => 'A man'];

    public const PREF_MODE = ['any' => 'No preference', 'video' => 'Video', 'in_person' => 'In person'];

    public const LANGUAGE_SUGGESTIONS = ['English', 'Luganda', 'Swahili', 'Runyankole', 'Lusoga', 'Acholi', 'Ateso', 'Lugbara', 'French'];

    /** @return array<string, bool> section key => on/off */
    public static function switches(): array
    {
        $saved = (array) AppSetting::get('intake.sections', []);
        $out = [];
        foreach (array_keys(self::SECTIONS) as $key) {
            $out[$key] = array_key_exists($key, $saved) ? (bool) $saved[$key] : true; // default: on
        }

        return $out;
    }

    /** @return list<string> enabled section keys, in display order */
    public static function enabled(): array
    {
        return array_keys(array_filter(self::switches()));
    }

    public static function isOn(string $section): bool
    {
        return self::switches()[$section] ?? false;
    }

    public static function anyOn(): bool
    {
        return self::enabled() !== [];
    }

    /** Labels to show as chips: the current list plus anything this client picked before. */
    public static function topicChoices(?\App\Models\ClientIntakeDetail $d = null): array
    {
        return array_values(array_unique(array_merge(self::topics(), (array) $d?->topics)));
    }

    public static function goalChoices(?\App\Models\ClientIntakeDetail $d = null): array
    {
        return array_values(array_unique(array_merge(self::goals(), (array) $d?->goals)));
    }

    /** Which columns belong to which section. */
    public const FIELDS = [
        'topics' => ['topics', 'own_words'],
        'mood' => ['mood_now'],
        'background' => ['counselling_before', 'medication', 'background_notes'],
        'goals' => ['goals', 'goals_text'],
        'preferences' => ['pref_therapist_gender', 'pref_language', 'pref_mode'],
    ];

    /** @return list<string> */
    public static function topics(): array
    {
        $list = AppSetting::get('intake.topics');

        return is_array($list) && $list !== [] ? array_values($list) : self::DEFAULT_TOPICS;
    }

    /** @return list<string> */
    public static function goals(): array
    {
        $list = AppSetting::get('intake.goals');

        return is_array($list) && $list !== [] ? array_values($list) : self::DEFAULT_GOALS;
    }

    /** Turn a textarea (one per line) into a clean list. */
    public static function linesToList(?string $text, int $max = 30): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text))
            ->map(fn ($l) => trim(mb_substr(trim($l), 0, 80)))
            ->filter()
            ->unique(fn ($l) => mb_strtolower($l))
            ->take($max)
            ->values()
            ->all();
    }

    /**
     * Validation rules for the sections that are on. Everything is optional (skippable).
     * Picked topics/goals must come from the current lists.
     */
    public static function rules(?\App\Models\ClientIntakeDetail $existing = null): array
    {
        // A client's earlier answers stay valid even if an admin has since renamed or
        // removed that topic/goal (answers keep their wording).
        $topics = array_values(array_unique(array_merge(self::topics(), (array) $existing?->topics)));
        $goals = array_values(array_unique(array_merge(self::goals(), (array) $existing?->goals)));

        $r = ['skipped' => ['nullable', 'array'], 'skipped.*' => ['string', 'in:'.implode(',', array_keys(self::SECTIONS))]];
        if (self::isOn('topics')) {
            $r['about_topics'] = ['nullable', 'array', 'max:20'];
            $r['about_topics.*'] = ['string', \Illuminate\Validation\Rule::in($topics)];
            // 2500, not 2000: the browser's maxlength counts a line break as 1 character
            // but it arrives as 2 (\r\n).
            $r['about_own_words'] = ['nullable', 'string', 'max:2500'];
        }
        if (self::isOn('mood')) {
            $r['about_mood'] = ['nullable', 'integer', 'between:1,5'];
        }
        if (self::isOn('background')) {
            $r['about_counselling'] = ['nullable', 'in:'.implode(',', array_keys(self::COUNSELLING))];
            $r['about_medication'] = ['nullable', 'in:'.implode(',', array_keys(self::MEDICATION))];
            $r['about_background'] = ['nullable', 'string', 'max:2500'];
        }
        if (self::isOn('goals')) {
            $r['about_goals'] = ['nullable', 'array', 'max:20'];
            $r['about_goals.*'] = ['string', \Illuminate\Validation\Rule::in($goals)];
            $r['about_goals_text'] = ['nullable', 'string', 'max:2500'];
        }
        if (self::isOn('preferences')) {
            $r['about_pref_gender'] = ['nullable', 'in:'.implode(',', array_keys(self::PREF_GENDER))];
            $r['about_pref_language'] = ['nullable', 'string', 'max:60'];
            $r['about_pref_mode'] = ['nullable', 'in:'.implode(',', array_keys(self::PREF_MODE))];
        }

        return $r;
    }

    /**
     * Map validated input to model attributes. A section the client skipped (or that is
     * switched off) is stored empty and listed in `skipped` when it was skipped.
     */
    public static function attributesFrom(array $v): array
    {
        $skipped = array_values(array_intersect(array_unique((array) ($v['skipped'] ?? [])), self::enabled()));
        $on = fn ($s) => self::isOn($s) && ! in_array($s, $skipped, true);
        $txt = fn ($k) => filled($v[$k] ?? null) ? trim((string) $v[$k]) : null;

        return [
            'topics' => $on('topics') ? (array_values(array_unique((array) ($v['about_topics'] ?? []))) ?: null) : null,
            'own_words' => $on('topics') ? $txt('about_own_words') : null,
            'mood_now' => $on('mood') && filled($v['about_mood'] ?? null) ? (int) $v['about_mood'] : null,
            'counselling_before' => $on('background') ? ($v['about_counselling'] ?? null) : null,
            'medication' => $on('background') ? ($v['about_medication'] ?? null) : null,
            'background_notes' => $on('background') ? $txt('about_background') : null,
            'goals' => $on('goals') ? (array_values(array_unique((array) ($v['about_goals'] ?? []))) ?: null) : null,
            'goals_text' => $on('goals') ? $txt('about_goals_text') : null,
            'pref_therapist_gender' => $on('preferences') ? ($v['about_pref_gender'] ?? null) : null,
            'pref_language' => $on('preferences') ? $txt('about_pref_language') : null,
            'pref_mode' => $on('preferences') ? ($v['about_pref_mode'] ?? null) : null,
            'skipped' => $skipped ?: null,
        ];
    }
}
