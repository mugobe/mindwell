<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Resource;
use App\Services\CarePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin → Resources (2026-11-07, roadmap 37). Only admins add to the library (Collins).
 * Kinds: article (text written here), PDF/worksheet or audio (uploaded file), video or
 * web link (an address). Unpublished resources are hidden from clients.
 */
class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $topic = $request->string('topic')->toString();
        $q = $request->string('q')->trim()->toString();

        $resources = Resource::withCount('shares')
            ->when($topic !== '', fn ($w) => $w->where('topic', $topic))
            ->when($q !== '', fn ($w) => $w->where('title', 'like', '%'.$q.'%'))
            ->orderByDesc('is_published')->latest()->paginate(30)->withQueryString();

        return view('admin.resources.index', [
            'resources' => $resources,
            'topics' => Resource::whereNotNull('topic')->distinct()->orderBy('topic')->pluck('topic'),
            'topic' => $topic,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.resources.form', ['resource' => new Resource(['kind' => 'article', 'is_published' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $resource = Resource::create($data + ['created_by' => $request->user()->id]);
        $this->saveFile($request, $resource);

        AuditLog::record($request, 'resource.created', 'Added "'.$resource->title.'" to the resource library.', ['resource_id' => $resource->id]);

        return redirect()->route('admin.resources.index')->with('status', 'Added to the library.');
    }

    public function edit(Resource $resource): View
    {
        return view('admin.resources.form', ['resource' => $resource]);
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $resource->update($this->validated($request, $resource));
        $this->saveFile($request, $resource);

        AuditLog::record($request, 'resource.updated', 'Updated "'.$resource->title.'" in the resource library.', ['resource_id' => $resource->id]);

        return redirect()->route('admin.resources.index')->with('status', 'Saved.');
    }

    public function destroy(Request $request, Resource $resource): RedirectResponse
    {
        if ($resource->file_path) {
            Storage::disk('local')->delete($resource->file_path);
        }
        $title = $resource->title;
        $resource->delete();

        AuditLog::record($request, 'resource.deleted', 'Removed "'.$title.'" from the resource library.');

        return redirect()->route('admin.resources.index')->with('status', 'Removed from the library.');
    }

    private function validated(Request $request, ?Resource $resource): array
    {
        $kind = $request->input('kind');
        $needsFile = in_array($kind, ['pdf', 'audio'], true) && ! $resource?->file_path;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:300'],
            'kind' => ['required', Rule::in(array_keys(Resource::KINDS))],
            'topic' => ['nullable', 'string', 'max:60'],
            'body' => [$kind === 'article' ? 'required' : 'nullable', 'string', 'max:60000'],
            'url' => [in_array($kind, ['video', 'link'], true) ? 'required' : 'nullable', 'url:https,http', 'max:500'],
            'file' => array_merge([$needsFile ? 'required' : 'nullable'], Resource::FILE_RULE),
            'minutes' => ['nullable', 'integer', 'between:1,600'],
            'is_published' => ['nullable', 'boolean'],
        ], [
            'body.required' => 'Write the article text.',
            'url.required' => 'Paste the video or web address.',
            'file.required' => 'Choose the file to upload.',
            'file.mimes' => 'Upload a PDF or an audio file (MP3, M4A, WAV, OGG).',
            'file.max' => 'The file must be 20 MB or smaller.',
        ]);

        unset($data['file']);
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    private function saveFile(Request $request, Resource $resource): void
    {
        if (! $request->hasFile('file')) {
            return;
        }
        if ($resource->file_path) {
            Storage::disk('local')->delete($resource->file_path);
        }
        $f = CarePlan::store($request->file('file'), 'resources/'.$resource->id);
        $resource->update(['file_path' => $f['path'], 'file_name' => $f['name'], 'file_mime' => $f['mime'], 'file_size' => $f['size']]);
    }
}
