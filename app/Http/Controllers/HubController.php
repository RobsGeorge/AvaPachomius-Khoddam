<?php

namespace App\Http\Controllers;

use App\Support\NavigationHub;

class HubController extends Controller
{
    public function academic()
    {
        $links = NavigationHub::academicLinks(auth()->user());

        return view('hubs.academic', compact('links'));
    }

    public function system()
    {
        $user = auth()->user();

        if (! NavigationHub::hasSystem($user)) {
            abort(403);
        }

        $links = NavigationHub::systemLinks($user);

        return view('hubs.system', compact('links'));
    }
}
