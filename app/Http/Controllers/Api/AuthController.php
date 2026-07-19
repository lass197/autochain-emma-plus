<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Web3\WalletAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function walletNonce(Request $request, WalletAuthService $walletAuth): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:100'],
        ]);

        return response()->json($walletAuth->issueNonce($data['address']));
    }

    /**
     * Authentification Web3 : nonce + personal_sign MetaMask vérifié côté serveur.
     */
    public function loginWallet(Request $request, WalletAuthService $walletAuth): JsonResponse
    {
        $data = $request->validate([
            'wallet_address' => ['required', 'string', 'max:100'],
            'signature' => ['required', 'string'],
            'message' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $walletAuth->assertValidSignature(
                $data['wallet_address'],
                $data['message'],
                $data['signature'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $wallet = strtolower($data['wallet_address']);
        $user = User::query()->whereRaw('LOWER(wallet_address) = ?', [$wallet])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Portefeuille non reconnu. Liez d\'abord cette adresse à un compte.',
            ], 404);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'web3')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
            'auth_method' => 'wallet',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'role_label' => $user->role?->label(),
            'wallet_address' => $user->wallet_address,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
        ];
    }
}
