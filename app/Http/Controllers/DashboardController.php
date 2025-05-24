<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Show the dashboard page
    public function index()
    {
        // Here you can pass data from DB if needed
        return view('dashboard');
    }
}

?>