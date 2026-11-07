<?php

namespace App\Http\Controllers;

use App\Models\Homework;
use App\Models\TherapyGoal;
use App\Services\CarePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The client's side of the care features (2026-11-07, roadmap 36, 39, 69):
 * "My therapy" (therapist, next session, goals, homework, resources from the therapist),
 * moving a goal's progress, and answering homework with words and/or a photo or PDF.
 */
class CareController extends Controller
{
    public function myTherapy(Request $request, CarePlan $care): View
    {
        $client = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);

        return view('care.my-therapy', $care->forClient($client));
    }

    public function goalProgress(Request $request, TherapyGoal $goal): RedirectResponse
    {
        $this->own($request, $goal->client_profile_id);
        abort_unless($goal->status === 'active', 403);

        $data = self::validateProgress($request);
        self::recordProgress($goal, 'client', (int) $data['progress'], $data['note'] ?? null);

        return redirect()->route('care.index')->with('status', 'Your progress is saved. Your therapist will see it.');
    }

    public static function validateProgress(Request $request): array
    {
        return $request->validate([
            'progress' => ['required', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    public static function recordProgress(TherapyGoal $goal, string $by, int $progress, ?string $note): void
    {
        $goal->update(['progress' => $progress]);
        $goal->updates()->create(['by_role' => $by, 'progress' => $progress, 'note' => filled($note) ? trim($note) : null]);
    }

    public function homework(Request $request, Homework $homework): View
    {
        $this->own($request, $homework->client_profile_id);

        return view('care.homework', ['homework' => $homework->load('resource', 'therapistProfile.user')]);
    }

    public function reply(Request $request, Homework $homework): RedirectResponse
    {
        $this->own($request, $homework->client_profile_id);
        self::saveReply($request, $homework);

        return redirect()->route('care.index')->with('status', 'Sent to your therapist. Well done.');
    }

    /** Words and/or one photo or PDF; marks the homework done. Shared with the API. */
    public static function saveReply(Request $request, Homework $homework): void
    {
        $data = $request->validate([
            'reply' => ['nullable', 'string', 'max:10000', 'required_without:file'],
            'file' => array_merge(['nullable'], Homework::FILE_RULE),
        ], [
            'reply.required_without' => 'Write your answer, or attach a photo or PDF.',
            'file.max' => 'The file must be 10 MB or smaller.',
            'file.mimes' => 'You can attach photos (JPG, PNG, HEIC) and PDF files.',
        ]);

        $attrs = [
            'reply' => filled($data['reply'] ?? null) ? trim($data['reply']) : $homework->reply,
            'status' => 'done',
            'done_at' => $homework->done_at ?? now(),
            'reviewed_at' => null,
        ];
        if ($request->hasFile('file')) {
            if ($homework->reply_file_path) {
                Storage::disk('local')->delete($homework->reply_file_path);
            }
            $f = CarePlan::store($request->file('file'), 'homework/'.$homework->id);
            $attrs += ['reply_file_path' => $f['path'], 'reply_file_name' => $f['name'], 'reply_file_mime' => $f['mime'], 'reply_file_size' => $f['size']];
        }
        $homework->update($attrs);
    }

    /** The client's reply file: the client, the therapist who gave it, or an admin. */
    public function homeworkFile(Request $request, Homework $homework)
    {
        $user = $request->user();
        $ok = $user->role === 'admin'
            || $user->clientProfile?->id === $homework->client_profile_id
            || ($user->therapistProfile && $user->therapistProfile->id === $homework->therapist_profile_id);
        abort_unless($ok, 403);

        return CarePlan::stream($homework->reply_file_path, $homework->reply_file_name, $homework->reply_file_mime);
    }

    private function own(Request $request, string $clientProfileId): void
    {
        abort_unless($request->user()->clientProfile?->id === $clientProfileId, 403);
    }
}
