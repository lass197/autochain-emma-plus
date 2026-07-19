<?php

namespace App\Services\Web3;

use Elliptic\EC;
use kornrunner\Keccak;
use RuntimeException;

class WalletSignatureVerifier
{
    /**
     * Vérifie une signature personal_sign (EIP-191) et retourne l'adresse récupérée.
     */
    public function recoverAddress(string $message, string $signature): string
    {
        $signature = strtolower(ltrim($signature, '0x'));

        if (strlen($signature) !== 130) {
            throw new RuntimeException('Signature invalide.');
        }

        $r = substr($signature, 0, 64);
        $s = substr($signature, 64, 64);
        $v = hexdec(substr($signature, 128, 2));

        if ($v >= 27) {
            $v -= 27;
        }

        if ($v !== 0 && $v !== 1) {
            throw new RuntimeException('V de signature invalide.');
        }

        $msgHash = $this->hashPersonalMessage($message);
        $ec = new EC('secp256k1');
        $publicKey = $ec->recoverPubKey($msgHash, ['r' => $r, 's' => $s], $v);
        $publicKeyHex = $publicKey->encode('hex');
        // Retirer le préfixe 04 (non compressé)
        $publicKeyHex = substr($publicKeyHex, 2);
        $address = substr(Keccak::hash(hex2bin($publicKeyHex), 256), 24);

        return '0x'.strtolower($address);
    }

    public function verify(string $expectedAddress, string $message, string $signature): bool
    {
        $recovered = $this->recoverAddress($message, $signature);

        return hash_equals(strtolower($expectedAddress), $recovered);
    }

    protected function hashPersonalMessage(string $message): string
    {
        $prefix = "\x19Ethereum Signed Message:\n".strlen($message);

        return Keccak::hash($prefix.$message, 256);
    }
}
