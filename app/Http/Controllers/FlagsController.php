<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class FlagsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(config('cricketamigo.flags'));
    }
}
