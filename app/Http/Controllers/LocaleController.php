<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocalePreferenceRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /**
     * Update the current visitor's locale preference in the session.
     */
    public function update(LocalePreferenceRequest $request): RedirectResponse
    {
        $request->session()->put('locale', $request->validated('locale'));

        return back();
    }
}
