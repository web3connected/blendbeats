<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use App\Models\LoungePlaylistTrack;
use App\Models\MediaFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoungePlaylistController extends Controller
{
    public function index(): View
    {
        $tracks = Schema::hasTable('lounge_playlist_tracks')
            ? LoungePlaylistTrack::query()
                ->with('mediaFile.user:id,name,email')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        $approvedMediaIds = $tracks->pluck('media_file_id')->all();
        $availableMedia = MediaFile::query()
            ->with('user:id,name,email')
            ->where('collection', 'dj_media')
            ->whereNotNull('user_id')
            ->whereNotIn('id', $approvedMediaIds)
            ->latest('created_at')
            ->get()
            ->filter(fn (MediaFile $file): bool => $this->isPublicPlayableMedia($file))
            ->values();

        return view('admin.lounge-playlist.index', [
            'tracks' => $tracks,
            'availableMedia' => $availableMedia,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media_file_id' => ['required', 'integer', Rule::exists('media_files', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $file = MediaFile::query()->findOrFail($validated['media_file_id']);
        abort_unless($this->isPublicPlayableMedia($file), 422, 'Only public uploaded audio can join the Lounge Playlist.');

        LoungePlaylistTrack::query()->updateOrCreate(
            ['media_file_id' => $file->id],
            [
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => true,
                'is_featured' => $request->boolean('is_featured'),
                'approved_at' => now(),
            ],
        );

        return redirect()
            ->route('admin.admincenter.loungeplaylist.index')
            ->with('status', 'Lounge track approved.');
    }

    public function update(Request $request, LoungePlaylistTrack $track): RedirectResponse
    {
        $validated = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $track->update([
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
            'approved_at' => $track->approved_at ?? now(),
        ]);

        return redirect()
            ->route('admin.admincenter.loungeplaylist.index')
            ->with('status', 'Lounge track updated.');
    }

    public function destroy(LoungePlaylistTrack $track): RedirectResponse
    {
        $track->delete();

        return redirect()
            ->route('admin.admincenter.loungeplaylist.index')
            ->with('status', 'Lounge track removed.');
    }

    private function isPublicPlayableMedia(MediaFile $file): bool
    {
        $portfolio = $file->metadata['portfolio'] ?? [];

        return $file->isAudio()
            && ($portfolio['visibility'] ?? null) === 'public'
            && in_array($portfolio['media_kind'] ?? 'mix', ['mix', 'track'], true);
    }
}
