<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequestRequest;
use App\Models\Journaliste;
use App\Models\UserAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountRequestController extends Controller
{
    public function create(): View
    {
        $journalistesDisponibles = Journaliste::query()
            ->whereNull('user_id')
            ->where('is_active', true)
            ->orderBy('nom')
            ->get(['id', 'matricule', 'civilite', 'nom', 'prenom', 'fonction', 'email', 'telephone']);

        return view('account-requests.create', [
            'journalistes' => $journalistesDisponibles,
            'submitted' => session('account_request_submitted'),
        ]);
    }

    public function store(StoreAccountRequestRequest $request): RedirectResponse
    {
        UserAccountRequest::create(array_merge(
            $request->validated(),
            [
                'status' => UserAccountRequest::STATUS_PENDING,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]
        ));

        return redirect()
            ->route('account-requests.create')
            ->with('account_request_submitted', true);
    }
}
