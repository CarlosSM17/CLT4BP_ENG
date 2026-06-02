<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Create instructor (admins only)
     */
    public function createInstructor(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'instructor',
        ]);

        return response()->json([
            'message' => 'Instructor created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    /**
     * List users (filter by role)
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->byRole($request->role);
        }

        $users = $query->active()->get(['id', 'name', 'email', 'role', 'last_login']);

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Get a specific user
     */
    public function show(User $user)
    {
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'last_login' => $user->last_login,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Deactivate user (soft delete)
     */
    public function deactivate(User $user)
    {
        $this->authorize('delete', $user);

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deactivated successfully',
        ]);
    }
}
