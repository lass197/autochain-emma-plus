<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($users);
    }

    public function drivers(): JsonResponse
    {
        $drivers = User::query()
            ->where('role', UserRole::Chauffeur)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json(['data' => $drivers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'wallet_address' => ['nullable', 'string', 'max:100', 'unique:users,wallet_address'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $user = User::query()->create($data);

        return response()->json(['message' => 'Compte créé.', 'user' => $user], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => $user]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'wallet_address' => ['nullable', 'string', 'max:100', Rule::unique('users', 'wallet_address')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(['message' => 'Compte mis à jour.', 'user' => $user->fresh()]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->isSuperAdmin() && User::query()->where('role', UserRole::SuperAdmin)->count() <= 1) {
            return response()->json(['message' => 'Impossible de supprimer le dernier super admin.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Compte archivé/supprimé.']);
    }
}
