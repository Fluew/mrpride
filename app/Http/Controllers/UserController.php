<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of team users.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $search = $request->input('search');
        $roleFilter = $request->input('role', 'All Roles');

        $query = $team->members()->withPivot('role');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter && $roleFilter !== 'All Roles') {
            $query->wherePivot('role', strtolower($roleFilter));
        }

        $users = $query->orderBy('name')->get()->map(function (User $user) use ($request) {
            $rawRole = $user->pivot->role ?? 'member';
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $rawRole === 'owner' ? 'Admin' : ucfirst($rawRole),
                'raw_role' => $rawRole,
                'status' => $user->email_verified_at ? 'Active' : 'Pending',
                'created_at' => $user->created_at?->format('M d, Y') ?? 'N/A',
                'is_self' => $user->id === $request->user()->id,
            ];
        });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search ?? '',
                'role' => $roleFilter,
            ],
            'roles' => ['Admin', 'Staff', 'Member'],
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['Admin', 'Staff', 'Member', 'admin', 'member', 'owner'])],
            'status' => ['nullable', 'string', Rule::in(['Active', 'Inactive', 'Pending'])],
        ]);

        $dbRole = strtolower($validated['role']) === 'admin' ? 'admin' : 'member';

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => ($validated['status'] ?? 'Active') === 'Active' ? now() : null,
            'current_team_id' => $team->id,
        ]);

        $team->members()->attach($newUser->id, ['role' => $dbRole]);

        return back()->with('success', 'User created and added to workspace successfully.');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team || ! $team->members()->where('users.id', $user->id)->exists()) {
            abort(403, 'User does not belong to this team.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['Admin', 'Staff', 'Member', 'admin', 'member', 'owner'])],
            'status' => ['nullable', 'string', Rule::in(['Active', 'Inactive', 'Pending'])],
        ]);

        $dbRole = strtolower($validated['role']) === 'admin' ? 'admin' : 'member';

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        if (isset($validated['status'])) {
            $userData['email_verified_at'] = $validated['status'] === 'Active' ? ($user->email_verified_at ?: now()) : null;
        }

        $user->update($userData);
        $team->members()->updateExistingPivot($user->id, ['role' => $dbRole]);

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        if (! $team) {
            abort(403, 'No active team selected.');
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete your own active account.']);
        }

        $team->members()->detach($user->id);

        // If the user has no remaining teams, remove the record
        if ($user->teams()->count() === 0) {
            $user->delete();
        }

        return back()->with('success', 'User removed from workspace successfully.');
    }
}
