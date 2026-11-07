<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\CareController as WebCare;
use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\Resource;
use App\Models\ResourceShare;
use App\Models\TherapyGoal;
use App\Services\CarePlan;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile API v1 — care features (2026-11-07): My therapy, the resource library, goal
 * progress and homework answers. Files come as 30-minute signed links (see
 * SignedFileController), so the app can open them without its login token.
 */
class CareController extends Controller
{
    public function myTherapy(Request $request, CarePlan $care): JsonResponse
    {
        $client = $request->user()->clientProfile ?? $request->user()->clientProfile()->create([]);
        $d = $care->forClient($client);
        $t = $d['therapist'];

        return response()->json([
            'therapist' => $t ? [
                'id' => $t->id,
                'name' => $t->user?->name,
                'title' => $t->professional_title,
                'photo_url' => $t->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($t->photo_path) : null,
            ] : null,
            'next_session' => $d['nextSession'] ? ApiPresenter::session($d['nextSession']) : null,
            'completed_sessions' => $d['completedCount'],
            'goals' => $d['goals']->map(fn ($g) => $this->goal($g))->values(),
            'homework' => $d['homework']->map(fn ($h) => $this->homework($h))->values(),
            'shared_resources' => $d['shared']->map(fn ($s) => [
                'id' => $s->id,
                'note' => $s->note,
                'opened' => (bool) $s->opened_at,
                'from' => $s->therapistProfile?->user?->name,
                'sent_at' => ApiPresenter::date($s->created_at),
                'resource' => $this->resource($s->resource),
            ])->values(),
        ]);
    }

    public function resources(Request $request): JsonResponse
    {
        $topic = $request->string('topic')->toString();
        $q = $request->string('q')->trim()->toString();

        return response()->json([
            'topics' => Resource::where('is_published', true)->whereNotNull('topic')->distinct()->orderBy('topic')->pluck('topic'),
            'resources' => Resource::where('is_published', true)
                ->when($topic !== '', fn ($w) => $w->where('topic', $topic))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%')))
                ->orderBy('title')->get()->map(fn ($r) => $this->resource($r))->values(),
        ]);
    }

    public function resource_(Request $request, Resource $resource): JsonResponse
    {
        abort_unless($resource->is_published, 404);
        $client = $request->user()->clientProfile;
        $share = $client ? ResourceShare::with('therapistProfile.user')->where('resource_id', $resource->id)->where('client_profile_id', $client->id)->latest()->first() : null;
        if ($client) {
            ResourceShare::where('resource_id', $resource->id)->where('client_profile_id', $client->id)->whereNull('opened_at')->update(['opened_at' => now()]);
        }

        return response()->json([
            'resource' => $this->resource($resource, full: true),
            'shared' => $share ? ['from' => $share->therapistProfile?->user?->name, 'note' => $share->note, 'sent_at' => ApiPresenter::date($share->created_at)] : null,
        ]);
    }

    public function goalProgress(Request $request, TherapyGoal $goal): JsonResponse
    {
        abort_unless($request->user()->clientProfile?->id === $goal->client_profile_id, 403);
        if ($goal->status !== 'active') {
            return response()->json(['message' => 'This goal is no longer active.'], 409);
        }
        $data = WebCare::validateProgress($request);
        WebCare::recordProgress($goal, 'client', (int) $data['progress'], $data['note'] ?? null);

        return response()->json(['message' => 'Your progress is saved. Your therapist will see it.', 'goal' => $this->goal($goal->fresh('updates'))]);
    }

    public function homeworkShow(Request $request, Homework $homework): JsonResponse
    {
        abort_unless($request->user()->clientProfile?->id === $homework->client_profile_id, 403);

        return response()->json(['homework' => $this->homework($homework->load('resource', 'therapistProfile.user'), full: true)]);
    }

    /** Multipart: reply (text) and/or file (photo or PDF, 10 MB). */
    public function homeworkReply(Request $request, Homework $homework): JsonResponse
    {
        abort_unless($request->user()->clientProfile?->id === $homework->client_profile_id, 403);
        WebCare::saveReply($request, $homework);

        return response()->json(['message' => 'Sent to your therapist. Well done.', 'homework' => $this->homework($homework->fresh(['resource', 'therapistProfile.user']), full: true)]);
    }

    private function goal(TherapyGoal $g): array
    {
        $last = $g->updates->first();

        return [
            'id' => $g->id,
            'title' => $g->title,
            'details' => $g->details,
            'status' => $g->status,
            'status_label' => $g->statusLabel(),
            'progress' => $g->progress,
            'target_on' => $g->target_on?->toDateString(),
            'last_update' => $last ? ['by' => $last->by_role, 'note' => $last->note, 'at' => ApiPresenter::date($last->created_at)] : null,
        ];
    }

    private function homework(Homework $h, bool $full = false): array
    {
        return [
            'id' => $h->id,
            'title' => $h->title,
            'instructions' => $full ? $h->instructions : null,
            'due_on' => $h->due_on?->toDateString(),
            'overdue' => $h->isOverdue(),
            'status' => $h->status,                 // assigned | done
            'reviewed' => (bool) $h->reviewed_at,
            'done_at' => ApiPresenter::date($h->done_at),
            'from' => $h->therapistProfile?->user?->name,
            'given_at' => ApiPresenter::date($h->created_at),
            'resource' => $h->resource && $h->resource->is_published ? $this->resource($h->resource) : null,
            'reply' => $full ? $h->reply : null,
            'reply_file' => $full && $h->reply_file_path ? ['name' => $h->reply_file_name, 'url' => $h->replyFileUrl(signed: true)] : null,
        ];
    }

    private function resource(Resource $r, bool $full = false): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'summary' => $r->summary,
            'kind' => $r->kind,                     // article | pdf | audio | video | link
            'kind_label' => $r->kindLabel(),
            'topic' => $r->topic,
            'length' => $r->lengthLabel(),
            'body' => $full ? $r->body : null,
            'url' => $r->url,
            'file_url' => $full ? $r->fileUrl(signed: true) : null,
        ];
    }
}
