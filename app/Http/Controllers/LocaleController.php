<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = in_array($request->input('locale'), config('family.locales'), true) ? $request->input('locale') : 'sw';
        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return back();
    }
}
