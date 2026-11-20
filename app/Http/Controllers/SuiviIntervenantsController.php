<?php

namespace App\Http\Controllers;

use App\Models\Intervenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SuiviIntervenantsController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Intervenant::class);

        return redirect()->route('parcours-intervenants.index', $request->query());
    }
}
