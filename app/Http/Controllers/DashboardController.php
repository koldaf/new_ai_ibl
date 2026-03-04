<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function home()
    {
        $user = Auth::user();
        return view('dashboard.home')->with('user', $user);
    }
}
