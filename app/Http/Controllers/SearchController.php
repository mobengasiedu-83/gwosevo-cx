<?php

namespace App\Http\Controllers;

use App\Services\Navigation;
use App\Services\UnifiedSearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        abort_unless($user && Navigation::portals($user), 403);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json([
            'query' => $validated['q'],
            'records' => UnifiedSearch::records($user, trim($validated['q'])),
        ]);
    }
}
