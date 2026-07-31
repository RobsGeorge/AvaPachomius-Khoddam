<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchApplicationRequest;
use App\Models\ChurchApplication;

class ChurchRegistrationController extends Controller
{
    public function create()
    {
        return view('church-registration.create', [
            'countries' => config('countries'),
        ]);
    }

    public function store(StoreChurchApplicationRequest $request)
    {
        // Honeypot: bots fill hidden "website"; real users leave it empty.
        if (filled($request->input('website'))) {
            return redirect()->route('church-registration.thanks');
        }

        $validated = $request->validated();
        unset($validated['website']);

        ChurchApplication::create([
            ...$validated,
            'status' => ChurchApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        return redirect()->route('church-registration.thanks');
    }

    public function thanks()
    {
        return view('church-registration.thanks');
    }
}
