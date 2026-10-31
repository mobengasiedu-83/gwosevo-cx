<?php

namespace App\Http\Controllers;

use App\Services\Navigation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function index(Request $request, string $portal)
    {
        return Inertia::render('Portal/Index', ['portal' => $portal, 'navigation' => Navigation::catalogue($request->user(), $portal)]);
    }

    public function show(Request $request, string $portal, string $group, string $item)
    {
        $navigation = Navigation::catalogue($request->user(), $portal);
        $category = collect($navigation)->firstWhere('id', $group);
        $selected = collect($category['items'] ?? [])->firstWhere('id', $item);
        abort_unless($selected, 404);
        if (isset($selected['resource'])) {
            return redirect($selected['url']);
        }

        return Inertia::render('Portal/Index', ['portal' => $portal, 'navigation' => $navigation, 'selected' => $selected, 'category' => $category['title']]);
    }
}
