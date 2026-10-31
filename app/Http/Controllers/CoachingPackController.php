<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class CoachingPackController extends Controller
{
    /**
     * Public, unauthenticated — packs are static rule data the app may fetch
     * before login. Latest version per discipline is denormalized so clients
     * can cheaply compare against their bundled copies.
     */
    public function index(): JsonResponse
    {
        $packs = collect(config('coaching_packs.packs'));

        return response()->json([
            'packs' => $packs,
            'latest' => $packs->pluck('version', 'discipline'),
        ])->setEtag(md5(json_encode($packs)));
    }
}
