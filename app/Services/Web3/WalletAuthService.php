<?php

namespace App\Services\Web3;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class WalletAuthService
{
    public function __construct(
        protected WalletSignatureVerifier $verifier,
    ) {}

    public function issueNonce(string $walletAddress): array
    {
        $address = strtolower($walletAddress);
        $nonce = Str::random(24);
        $message = $this->buildMessage($address, $nonce);

        Cache::put($this->cacheKey($address), $nonce, now()->addMinutes(10));

        return [
            'address' => $address,
            'nonce' => $nonce,
            'message' => $message,
            'expires_in' => 600,
        ];
    }

    public function assertValidSignature(string $walletAddress, string $message, string $signature): void
    {
        $address = strtolower($walletAddress);
        $nonce = Cache::get($this->cacheKey($address));

        if (! $nonce) {
            throw new RuntimeException('Nonce expiré. Redemandez un challenge wallet.');
        }

        $expected = $this->buildMessage($address, $nonce);

        if (! hash_equals($expected, $message)) {
            throw new RuntimeException('Message de signature invalide.');
        }

        if (! $this->verifier->verify($address, $message, $signature)) {
            throw new RuntimeException('Signature wallet invalide.');
        }

        Cache::forget($this->cacheKey($address));
    }

    protected function buildMessage(string $address, string $nonce): string
    {
        $app = config('autochain.name', 'Autochain Emma+');

        return "{$app} — Connexion Web3\nAdresse: {$address}\nNonce: {$nonce}\n";
    }

    protected function cacheKey(string $address): string
    {
        return 'wallet_nonce_'.$address;
    }
}
