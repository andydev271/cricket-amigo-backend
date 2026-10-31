<?php

namespace App\Http\Controllers;

use App\Models\ClipUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClipUploadController extends Controller
{
    /**
     * Cloud clip storage — flag `features.cloud_upload`, bypassed in free_mode
     * so the whole path is testable during the Brazil window. The media disk
     * is 'public' today; pointing media-library's disk at R2 later is a
     * config-only change.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->uploadAllowed(), 403, 'cloud upload is not enabled');

        $data = $request->validate([
            'clip' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:307200'],
            'discipline' => ['nullable', Rule::in(['batting', 'bowling', 'fielding', 'keeping'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $upload = ClipUpload::create([
            'user_id' => $request->user()->id,
            'discipline' => $data['discipline'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $upload->addMediaFromRequest('clip')->toMediaCollection('clip');

        return response()->json($this->payload($upload->refresh()), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $request->user()
            ->clipUploads()
            ->with('media')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ClipUpload $u) => $this->payload($u));

        return response()->json($rows);
    }

    private function uploadAllowed(): bool
    {
        return config('cricketamigo.flags.free_mode')
            || config('cricketamigo.flags.features.cloud_upload');
    }

    private function payload(ClipUpload $u): array
    {
        return [
            'id' => $u->id,
            'discipline' => $u->discipline,
            'notes' => $u->notes,
            'url' => $u->getFirstMediaUrl('clip') ?: null,
            'size' => $u->getFirstMedia('clip')?->size,
            'created_at' => $u->created_at,
        ];
    }
}
