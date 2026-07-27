<?php

namespace App\Http\Controllers;

use App\Support\NavigationHub;

class HubController extends Controller
{
    public function academic()
    {
        $user = auth()->user();
        $links = NavigationHub::academicLinks($user);

        return view('hubs.academic', compact('links'));
    }

    public function service()
    {
        $user = auth()->user();

        if (! NavigationHub::hasService($user)) {
            abort(403);
        }

        $links = NavigationHub::serviceLinks($user);
        $currentService = current_service();

        return view('hubs.service', compact('links', 'currentService'));
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
