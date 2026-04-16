<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $users = User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->get(['id', 'name', 'email', 'role']);

        return view('users.index', [
            'users' => $users,
            'roles' => User::allowedRoles(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', User::allowedRoles())],
        ]);

        if ($request->user()->id === $user->id && $data['role'] !== User::ROLE_ADMIN) {
            return redirect()
                ->route('users.index')
                ->with('status', 'You cannot remove your own admin role.');
        }

        $user->role = $data['role'];
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('status', 'User role updated.');
    }

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
    }
}
