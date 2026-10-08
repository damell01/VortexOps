<?php
namespace App\Http\Controllers;

use App\Support\GuidedHelp;
use Illuminate\Http\Request;

class GuideDocumentController extends Controller
{
    public function __invoke(Request $request, string $guide)
    {
        $doc = GuidedHelp::documents()[$guide] ?? null;
        abort_unless($doc, 403);
        $path = resource_path('guides/' . $doc['file']);
        abort_unless(is_file($path), 404);
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'];
        return $request->boolean('download')
            ? response()->download($path, $doc['file'], $headers)
            : response()->file($path, $headers);
    }
}
