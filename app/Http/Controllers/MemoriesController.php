<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Event;
use App\Models\Household;
use App\Models\MediaAsset;
use App\Models\TakedownRequest;
use App\Support\Audit;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class MemoriesController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $albums = Album::visibleTo($user)->with('cover')->withCount('visibleMedia')
            ->when($request->query('year'), fn ($q, $y) => $q->where('year', $y))
            ->when($request->query('household'), fn ($q, $h) => $q->where('household_id', $h))
            ->when($request->query('event'), fn ($q, $e) => $q->where('event_id', $e))
            ->orderByDesc('year')->latest()->get();

        return view('memories.index', [
            'albums' => $albums,
            'years' => Album::visibleTo($user)->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            'households' => Household::orderBy('name')->get(),
            'events' => Event::visibleTo($user)->latest('starts_at')->limit(30)->get(),
            'takedowns' => $user->hasPermission('memories.manage') ? TakedownRequest::with('media.album', 'requester.member')->where('status', 'open')->get() : collect(),
        ]);
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'], 'year' => ['nullable', 'integer', 'between:1900,2100'],
            'event_id' => ['nullable', 'exists:events,id'], 'household_id' => ['nullable', 'exists:households,id'],
            'is_annual_portrait' => ['boolean'], 'visibility' => ['required', Rule::in(['members', 'leaders'])],
        ]);
        $album = Album::create($d + ['is_annual_portrait' => $request->boolean('is_annual_portrait'), 'created_by' => $request->user()->id]);

        return redirect()->route('memories.show', $album)->with('status', __('Albamu imeundwa. Pakia picha.'));
    }

    public function show(Request $request, Album $album): View
    {
        abort_unless(Album::visibleTo($request->user())->whereKey($album->id)->exists(), 404);

        return view('memories.show', ['album' => $album->load('event'), 'media' => $album->visibleMedia()->with('uploader.member')->get()]);
    }

    public function upload(Request $request, Album $album): RedirectResponse
    {
        abort_unless(Album::visibleTo($request->user())->whereKey($album->id)->exists(), 404);
        $request->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:'.config('family.uploads.photo_max_kb')],
            'caption' => ['nullable', 'string', 'max:255'],
            'allow_download' => ['boolean'],
        ]);
        $n = 0;
        foreach ($request->file('photos') as $file) {
            try {
                $stored = ImageStore::storeWithThumb($file, 'albums/'.$album->public_id);
            } catch (RuntimeException) {
                continue;
            }
            $media = $album->media()->create([
                'uploaded_by' => $request->user()->id, 'path' => $stored['path'], 'thumb_path' => $stored['thumb'],
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200), 'mime' => 'image/jpeg', 'size' => Storage::disk('private')->size($stored['path']),
                'width' => $stored['width'], 'height' => $stored['height'], 'caption' => $request->input('caption'), 'allow_download' => $request->boolean('allow_download', true),
            ]);
            $album->cover_media_id ??= $media->id;
            $n++;
        }
        $album->save();

        return back()->with('status', __('Picha :n zimepakiwa.', ['n' => $n]));
    }

    /** Every image goes through here — there is no public URL to any family photo. */
    public function file(Request $request, MediaAsset $media, string $variant)
    {
        $user = $request->user();
        $album = $media->album;
        abort_unless(Album::visibleTo($user)->whereKey($album->id)->exists(), 404);
        abort_if($media->status !== 'visible' && ! $user->hasPermission('memories.manage'), 404);
        $path = $variant === 'thumb' ? ($media->thumb_path ?? $media->path) : $media->path;
        abort_unless(Storage::disk('private')->exists($path), 404);

        if ($variant === 'download') {
            abort_unless($media->allow_download || $user->hasPermission('memories.manage'), 403);

            return Storage::disk('private')->download($path, pathinfo($media->original_name, PATHINFO_FILENAME).'.jpg');
        }

        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'private, max-age=86400', 'Content-Disposition' => 'inline']);
    }

    public function caption(Request $request, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();
        abort_unless($media->uploaded_by === $user->id || $user->hasPermission('memories.manage'), 403);
        $d = $request->validate(['caption' => ['nullable', 'string', 'max:255'], 'taken_on' => ['nullable', 'date'], 'allow_download' => ['boolean'], 'cover' => ['boolean']]);
        $media->update(['caption' => $d['caption'] ?? null, 'taken_on' => $d['taken_on'] ?? null, 'allow_download' => $request->boolean('allow_download')]);
        if ($request->boolean('cover') && $user->hasPermission('memories.manage')) {
            $media->album->update(['cover_media_id' => $media->id]);
        }

        return back();
    }

    /** Anyone in a photo (or a parent of a child in it) can ask for it to be taken down. Hidden immediately pending review. */
    public function takedown(Request $request, MediaAsset $media): RedirectResponse
    {
        abort_unless(Album::visibleTo($request->user())->whereKey($media->album_id)->exists(), 404);
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        TakedownRequest::create(['media_asset_id' => $media->id, 'requested_by' => $request->user()->id, 'reason' => $d['reason']]);
        $media->update(['status' => 'hidden']);
        Audit::record('memories.takedown_requested', $media, 'Photo takedown requested (hidden pending review)');

        return back()->with('status', __('Picha imefichwa mara moja na ombi lako litapitiwa.'));
    }

    public function resolveTakedown(Request $request, TakedownRequest $takedown): RedirectResponse
    {
        $d = $request->validate(['decision' => ['required', Rule::in(['upheld', 'declined'])], 'resolution' => ['nullable', 'string', 'max:1000']]);
        $takedown->update(['status' => $d['decision'], 'resolution' => $d['resolution'] ?? null, 'resolved_by' => $request->user()->id]);
        if ($d['decision'] === 'upheld') {
            $m = $takedown->media;
            Storage::disk('private')->delete(array_filter([$m->path, $m->thumb_path]));
            $m->update(['status' => 'hidden']);
        } else {
            $takedown->media->update(['status' => 'visible']);
        }
        Audit::record('memories.takedown_'.$d['decision'], $takedown->media, 'Takedown '.$d['decision']);

        return back()->with('status', __('Ombi limeshughulikiwa.'));
    }
}
