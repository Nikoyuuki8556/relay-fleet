<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $memberships = $request->user()->memberships()->with('community')->get();

        return view('dashboard', ['memberships' => $memberships]);
    }
}
