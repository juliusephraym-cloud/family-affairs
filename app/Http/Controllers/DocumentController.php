<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Meeting;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(Document $document)
    {
        $parent = $document->attachable;
        $allowed = match (true) {
            $parent instanceof Meeting => true,
            default => request()->user()->hasPermission('governance.manage_meetings'),
        };
        abort_unless($allowed && Storage::disk('private')->exists($document->path), 404);

        return Storage::disk('private')->download($document->path, $document->original_name);
    }
}
