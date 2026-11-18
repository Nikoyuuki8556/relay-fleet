<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    // GET /me
    public function show(Request $request)
    {
        return $request->user();
    }
}
