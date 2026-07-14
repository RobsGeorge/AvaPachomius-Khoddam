<?php

namespace App\Http\Controllers;

/**
 * F-04 — a localized help & FAQ page for applicants and users. Content is
 * driven entirely by the `help` language file (ar + en), so it stays RTL-first
 * and translatable with no code changes.
 */
class HelpController extends Controller
{
    public function faq(): \Illuminate\View\View
    {
        return view('help.faq', [
            'faqs' => (array) __('help.faqs'),
        ]);
    }
}
