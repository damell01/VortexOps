<?php

namespace App\Http\Controllers;

use App\Models\TeamMessageAttachment;
use App\Services\TeamCommunications;
use Illuminate\Support\Facades\Storage;

class TeamMessageAttachmentController extends Controller
{
    public function __invoke(int $attachment)
    {
        $service = app(TeamCommunications::class);
        $service->authorize(auth()->user());
        $file = TeamMessageAttachment::with('message')->findOrFail($attachment);
        $service->conversation(auth()->user(), $file->message->conversation_id);
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        return Storage::disk('local')->download($file->path, $file->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
