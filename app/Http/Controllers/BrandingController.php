<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /** The family emblem is the only image served before login; it is not personal data. */
    public function emblem()
    {
        $path = Settings::get('family.emblem_path');
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
