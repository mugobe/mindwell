<?php

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\CareController as ClientCare;
use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\Homework;
use App\Models\Resource;
use App\Models\ResourceShare;
use App\Models\TherapyGoal;
use App\Services\CarePlan;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The therapist's side of the care features (2026-11-07), from the client's file:
 * set and update goals, give homework (optionally with a library worksheet and a due
 * date), mark homework reviewed, and send a library resource to the client.
 * Only for clients they have (had) a session with. The client gets a discreet email.
 */
class CareController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function storeGoal(Request $request, ClientProfile $clientProfile): RedirectResponse
    {
        $therapist = $this->therapistFor($request, $clientProfile);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:2000'],
            'target_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        TherapyGoal::create($data + ['client_profile_id' => $clientProfile->id, 'therapist_profile_id' => $therapist->id, 'status' => 'active', 'progress' => 0]);
        $this->notifier->careAdded($clientProfile, 'goal');

        return $this->back($clientProfile, 'Goal added. '.$this->first($clientProfile).' can see it in My therapy.');
    }

    public function updateGoal(Request $request, TherapyGoal $goal): RedirectResponse
    {
        $this->therapistFor($request, $goal->clientProfile);
        $data = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_keys(TherapyGoal::STATUSES))],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['status']) && $data['status'] !== $goal->status) {
            $goal->update([
                'status' => $data['status'],
                'achieved_at' => $data['status'] === 'achieved' ? now() : null,
                'progress' => $data['status'] === 'achieved' ? 100 : $goal->progress,
            ]);
            $goal->updates()->create(['by_role' => 'therapist', 'progress' => $goal->progress, 'note' => 'Marked '.strtolower($goal->statusLabel()).'.'.(filled($data['note'] ?? null) ? ' '.$data['note'] : '')]);
        } elseif (isset($data['progress']) || filled($data['note'] ?? null)) {
            ClientCare::recordProgress($goal, 'therapist', (int) ($data['progress'] ?? $goal->progress), $data['note'] ?? null);
        }

        return $this->back($goal->clientProfile, 'Goal updated.');
    }

    public function destroyGoal(Request $request, TherapyGoal $goal): RedirectResponse
    {
        $this->therapistFor($request, $goal->clientProfile);
        $client = $goal->clientProfile;
        $goal->delete();

        return $this->back($client, 'Goal removed.');
    }

    public function storeHomework(Request $request, ClientProfile $clientProfile): RedirectResponse
    {
        $therapist = $this->therapistFor($request, $clientProfile);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'resource_id' => ['nullable', 'uuid', 'exists:resources,id'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        Homework::create($data + ['client_profile_id' => $clientProfile->id, 'therapist_profile_id' => $therapist->id, 'status' => 'assigned']);
        $this->notifier->careAdded($clientProfile, 'homework');

        return $this->back($clientProfile, 'Homework given. '.$this->first($clientProfile).' will see it in My therapy.');
    }

    public function reviewHomework(Request $request, Homework $homework): RedirectResponse
    {
        $this->therapistFor($request, $homework->clientProfile);
        $homework->update(['reviewed_at' => now()]);

        return $this->back($homework->clientProfile, 'Marked as reviewed.');
    }

    public function destroyHomework(Request $request, Homework $homework): RedirectResponse
    {
        $this->therapistFor($request, $homework->clientProfile);
        $client = $homework->clientProfile;
        if ($homework->reply_file_path) {
            Storage::disk('local')->delete($homework->reply_file_path);
        }
        $homework->delete();

        return $this->back($client, 'Homework removed.');
    }

    public function shareResource(Request $request, ClientProfile $clientProfile): RedirectResponse
    {
        $therapist = $this->therapistFor($request, $clientProfile);
        $data = $request->validate([
            'resource_id' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['resource_id.required' => 'Choose a resource to send.']);
        $resource = Resource::where('is_published', true)->findOrFail($data['resource_id']);

        ResourceShare::create(['resource_id' => $resource->id, 'client_profile_id' => $clientProfile->id, 'therapist_profile_id' => $therapist->id, 'note' => $data['note'] ?? null]);
        $this->notifier->careAdded($clientProfile, 'resource');

        return $this->back($clientProfile, '"'.$resource->title.'" sent to '.$this->first($clientProfile).'.');
    }

    private function therapistFor(Request $request, ?ClientProfile $client)
    {
        $therapist = $request->user()->therapistProfile;
        abort_unless($client && CarePlan::therapistMayAct($therapist, $client), 403);

        return $therapist;
    }

    private function back(ClientProfile $client, string $message): RedirectResponse
    {
        return redirect()->to(route('therapist.clients.show', $client).'#care')->with('status', $message);
    }

    private function first(ClientProfile $client): string
    {
        return explode(' ', trim((string) $client->user?->name))[0] ?: 'Your client';
    }
}
