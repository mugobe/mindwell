<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourceShare;
use App\Services\CarePlan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The resource library for clients and therapists (2026-11-07, roadmap 37).
 * Clients see published resources, with the ones their therapist sent them on top.
 * Therapists browse it to choose what to share (they share from the client's file).
 */
class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $topic = $request->string('topic')->toString();
        $q = $request->string('q')->trim()->toString();
        $client = $request->user()->role === 'client' ? $request->user()->clientProfile : null;

        $resources = Resource::where('is_published', true)
            ->when($topic !== '', fn ($w) => $w->where('topic', $topic))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%')))
            ->orderBy('title')->get();

        return view('resources.index', [
            'resources' => $resources,
            'topics' => Resource::where('is_published', true)->whereNotNull('topic')->distinct()->orderBy('topic')->pluck('topic'),
            'topic' => $topic,
            'q' => $q,
            'shared' => $client
                ? ResourceShare::with('resource', 'therapistProfile.user')->where('client_profile_id', $client->id)
                    ->whereHas('resource', fn ($w) => $w->where('is_published', true))->latest()->limit(6)->get()
                : collect(),
        ]);
    }

    public function show(Request $request, Resource $resource): View
    {
        $this->authorizeSee($request, $resource);

        $share = null;
        if ($client = $request->user()->clientProfile) {
            $share = ResourceShare::with('therapistProfile.user')->where('resource_id', $resource->id)->where('client_profile_id', $client->id)->latest()->first();
            ResourceShare::where('resource_id', $resource->id)->where('client_profile_id', $client->id)->whereNull('opened_at')->update(['opened_at' => now()]);
        }

        return view('resources.show', ['resource' => $resource, 'share' => $share]);
    }

    public function file(Request $request, Resource $resource)
    {
        $this->authorizeSee($request, $resource);

        return CarePlan::stream($resource->file_path, $resource->file_name, $resource->file_mime);
    }

    private function authorizeSee(Request $request, Resource $resource): void
    {
        abort_unless($resource->is_published || in_array($request->user()->role, ['admin', 'therapist'], true), 404);
    }
}
