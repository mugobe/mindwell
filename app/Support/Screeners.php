<?php

namespace App\Support;

/**
 * PHQ-9 (depression) and GAD-7 (anxiety) — the questions, answer options, standard
 * severity bands, and the gentler wording clients see (added 2026-10-03 for progress
 * check-ins).
 *
 * The item wording is identical to App\Http\Controllers\IntakeController's constants
 * (left untouched there because intake is live and confirmed working), so check-in
 * scores line up exactly with the intake baseline.
 *
 * Two vocabularies, decided with Collins ("Gentle trend"):
 *  - band()       — the standard clinical labels. Therapists/admin only.
 *  - clientWord() — plain words for the client's own page. Never "moderately severe
 *                   depression" or similar.
 */
class Screeners
{
    public const PHQ9_ITEMS = [
        1 => 'Little interest or pleasure in doing things',
        2 => 'Feeling down, depressed, or hopeless',
        3 => 'Trouble falling or staying asleep, or sleeping too much',
        4 => 'Feeling tired or having little energy',
        5 => 'Poor appetite or overeating',
        6 => 'Feeling bad about yourself — or that you are a failure, or have let yourself or your family down',
        7 => 'Trouble concentrating on things, such as reading or watching TV',
        8 => 'Moving or speaking so slowly that others could have noticed — or the opposite, being fidgety or restless',
        9 => 'Thoughts that you would be better off dead, or of hurting yourself in some way',
    ];

    public const GAD7_ITEMS = [
        1 => 'Feeling nervous, anxious, or on edge',
        2 => 'Not being able to stop or control worrying',
        3 => 'Worrying too much about different things',
        4 => 'Trouble relaxing',
        5 => 'Being so restless that it is hard to sit still',
        6 => 'Becoming easily annoyed or irritable',
        7 => 'Feeling afraid as if something awful might happen',
    ];

    public const OPTIONS = [
        0 => 'Not at all',
        1 => 'Several days',
        2 => 'More than half the days',
        3 => 'Nearly every day',
    ];

    public const PHQ9_MAX = 27;
    public const GAD7_MAX = 21;

    /** Standard cut-offs: [lower bound => label]. */
    public const PHQ9_BANDS = [0 => 'Minimal', 5 => 'Mild', 10 => 'Moderate', 15 => 'Moderately severe', 20 => 'Severe'];
    public const GAD7_BANDS = [0 => 'Minimal', 5 => 'Mild', 10 => 'Moderate', 15 => 'Severe'];

    /** Same cut-offs, client wording. */
    public const PHQ9_CLIENT_WORDS = [0 => 'very little', 5 => 'mild', 10 => 'moderate', 15 => 'quite high', 20 => 'quite high'];
    public const GAD7_CLIENT_WORDS = [0 => 'very little', 5 => 'mild', 10 => 'moderate', 15 => 'quite high'];

    /** A change smaller than this is "about the same". */
    public const MEANINGFUL_CHANGE = 3;

    /** @param 'phq9'|'gad7' $scale */
    public static function band(string $scale, ?int $score): ?string
    {
        return self::lookup($scale === 'phq9' ? self::PHQ9_BANDS : self::GAD7_BANDS, $score);
    }

    /** @param 'phq9'|'gad7' $scale */
    public static function clientWord(string $scale, ?int $score): ?string
    {
        return self::lookup($scale === 'phq9' ? self::PHQ9_CLIENT_WORDS : self::GAD7_CLIENT_WORDS, $score);
    }

    /** 'lower' | 'higher' | 'same' | null (nothing to compare with). Lower is better. */
    public static function direction(?int $previous, ?int $current): ?string
    {
        if ($previous === null || $current === null) {
            return null;
        }

        $diff = $current - $previous;

        return match (true) {
            $diff <= -self::MEANINGFUL_CHANGE => 'lower',
            $diff >= self::MEANINGFUL_CHANGE => 'higher',
            default => 'same',
        };
    }

    /**
     * Validation rules + scoring for a submitted PHQ-9/GAD-7 form (fields phq_1..9,
     * gad_1..7 — same field names as intake).
     *
     * @return array<string, array>
     */
    public static function rules(): array
    {
        $rules = [];
        foreach (array_keys(self::PHQ9_ITEMS) as $i) {
            $rules["phq_{$i}"] = ['required', 'integer', 'between:0,3'];
        }
        foreach (array_keys(self::GAD7_ITEMS) as $i) {
            $rules["gad_{$i}"] = ['required', 'integer', 'between:0,3'];
        }

        return $rules;
    }

    /** @return array{phq9: int, gad7: int, self_harm: bool, responses: array} */
    public static function score(array $validated): array
    {
        $phq = [];
        foreach (array_keys(self::PHQ9_ITEMS) as $i) {
            $phq[$i] = (int) $validated["phq_{$i}"];
        }
        $gad = [];
        foreach (array_keys(self::GAD7_ITEMS) as $i) {
            $gad[$i] = (int) $validated["gad_{$i}"];
        }

        return [
            'phq9' => array_sum($phq),
            'gad7' => array_sum($gad),
            'self_harm' => $phq[9] > 0,
            'responses' => ['phq9' => $phq, 'gad7' => $gad],
        ];
    }

    private static function lookup(array $bands, ?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $label = null;
        foreach ($bands as $from => $text) {
            if ($score >= $from) {
                $label = $text;
            }
        }

        return $label;
    }
}
