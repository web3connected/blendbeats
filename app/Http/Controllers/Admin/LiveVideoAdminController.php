<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LiveVideoAdminController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([LiveStream::STATUS_LIVE, LiveStream::STATUS_ENDED])],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $streams = LiveStream::query()
            ->with(['user.djProfile', 'liveChannel'])
            ->withCount(['likes', 'comments'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('agora_channel_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user.djProfile', fn ($profileQuery) => $profileQuery->where('dj_name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $disk = Storage::disk('public');
        $streams->getCollection()->each(function (LiveStream $stream) use ($disk): void {
            $path = $stream->recording_storage_path;
            $exists = filled($path) && $disk->exists($path);
            $stream->setAttribute('recording_file_exists', $exists);
            $stream->setAttribute('recording_file_size', $exists ? $disk->size($path) : null);
        });

        return view('admin.live-videos.index', [
            'streams' => $streams,
            'filters' => $filters,
            'summary' => [
                'live' => LiveStream::query()->where('status', LiveStream::STATUS_LIVE)->count(),
                'saved' => LiveStream::query()->where('status', LiveStream::STATUS_ENDED)->where('recording_enabled', true)->count(),
                'ready' => LiveStream::query()->where('recording_status', 'ready')->count(),
            ],
        ]);
    }

    public function destroy(LiveStream $liveStream): RedirectResponse
    {
        abort_if($liveStream->status === LiveStream::STATUS_LIVE, 409, 'An active live stream cannot be removed.');

        $path = $liveStream->recording_storage_path;
        if (filled($path) && Storage::disk('public')->exists($path)) {
            abort_unless(Storage::disk('public')->delete($path), 500, 'The recording file could not be removed.');
        }

        $title = $liveStream->title;
        $liveStream->delete();

        return redirect()
            ->route('admin.admincenter.live-videos.index')
            ->with('status', "{$title} was removed from saved live videos.");
    }
}
