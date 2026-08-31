<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ASSIGNABLE_ROLES = ['customer', 'staff', 'delivery'];

    public function index(Request $request): View
    {
        $users = User::query()
            ->whereIn('role', self::ASSIGNABLE_ROLES)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';

                $q->where(function ($q) use ($term) {
                    $q->whereLike('email', $term)
                        ->orWhereLike('first_name', $term)
                        ->orWhereLike('last_name', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => self::ASSIGNABLE_ROLES,
        ]);
    }

    public function show(User $user): View
    {
        abort_unless($user->isRoleAssignableByOwner(), 404);

        $user->loadCount('orders');

        return view('admin.users.show', [
            'user' => $user,
            'roles' => self::ASSIGNABLE_ROLES,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isRoleAssignableByOwner(), 403);

        if ($user->id === $request->user()->id) {
            abort(403, 'You cannot change your own role.');
        }

        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::ASSIGNABLE_ROLES)],
        ]);

        $user->update(['role' => $data['role']]);

        return back()->with('status', __('Account role updated.'));
    }
}
