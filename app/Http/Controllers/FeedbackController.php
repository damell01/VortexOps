<?php

namespace App\Http\Controllers;

use App\Models\FeedbackTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:5000',
            'priority'        => 'in:low,medium,high',
            'type'            => 'nullable|in:bug,suggestion,question,other',
            'screenshot'      => 'nullable|string',
            'annotation_json' => 'nullable|string',
            'page_url'        => 'nullable|string|max:2000',
            'resource_type'   => 'nullable|string|max:100',
            'resource_id'     => 'nullable|integer',
        ]);

        $screenshotPath = null;

        if ($request->screenshot && str_starts_with($request->screenshot, 'data:image/')) {
            $commaPos = strpos($request->screenshot, ',');
            $data     = $commaPos !== false ? substr($request->screenshot, $commaPos + 1) : '';
            $bytes    = base64_decode($data, strict: true);

            if ($bytes !== false && in_array(
                finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $bytes),
                ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
                true
            )) {
                $filename = 'feedback/screenshot_' . uniqid('', true) . '.png';
                Storage::disk('public')->makeDirectory('feedback');
                Storage::disk('public')->put($filename, $bytes);
                $screenshotPath = $filename;
            }
        }

        $user = auth()->user();

        FeedbackTicket::create([
            'title'           => $request->title,
            'description'     => $request->description,
            'priority'        => $request->priority ?? 'medium',
            'type'            => $request->type ?? 'bug',
            'screenshot_path' => $screenshotPath,
            'annotation_json' => $request->annotation_json,
            'page_url'        => $request->page_url,
            'resource_type'   => $request->resource_type,
            'resource_id'     => $request->resource_id,
            'submitted_by'    => $user?->id,
            'submitted_name'  => $user?->name,
            'submitted_email' => $user?->email,
        ]);

        return response()->json(['success' => true]);
    }
}
