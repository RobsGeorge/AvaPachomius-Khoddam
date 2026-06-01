<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $theme = $request->validate(['theme' => 'required|in:light,dark'])['theme'];

        return response()->json(['theme' => $theme])
            ->cookie('theme', $theme, 60 * 24 * 365);
    }
}
