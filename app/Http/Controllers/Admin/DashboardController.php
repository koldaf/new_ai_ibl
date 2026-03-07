<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // simple response for tests and basic functionality
        return view('dashboard.home');
    }
}
