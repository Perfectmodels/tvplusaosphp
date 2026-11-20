<?php

namespace App\Http\Controllers;

use App\Services\RoleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class UtilisateurPermissionController extends Controller
{
    public function __construct(
        private readonly RoleAccessService $roleAccessService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->roleAccessService->canManage($request->user()), 403);

        return view('utilisateurs_permissions', [
            'modules' => $this->roleAccessService->modules(),
            'roles' => $this->roleAccessService->matrix(),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->roleAccessService->assertCanEditRole($request->user(), $role);

        $valid = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        /** @var list<string> $permissions */
        $permissions = collect($valid['permissions'] ?? [])
            ->map(static fn ($permission): string => trim((string) $permission))
            ->filter(static fn (string $permission): bool => $permission !== '')
            ->values()
            ->all();

        $this->roleAccessService->updateRole($role, $permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('utilisateurs.permissions')
            ->with('swal', [
                'type' => 'success',
                'title' => 'Droits par rôle mis à jour',
                'text' => "Les permissions du rôle « {$role} » ont bien été enregistrées.",
                'timer' => 2600,
            ]);
    }
}
