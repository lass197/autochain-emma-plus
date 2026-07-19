<?php

namespace App\Services\Web3;

use kornrunner\Keccak;

class AbiEncoder
{
    public static function methodId(string $signature): string
    {
        return substr(Keccak::hash($signature, 256), 0, 8);
    }

    public static function encodeUint256(int|string $value): string
    {
        $hex = is_string($value) && str_starts_with($value, '0x')
            ? substr($value, 2)
            : dechex((int) $value);

        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    public static function encodeAddress(string $address): string
    {
        return str_pad(strtolower(ltrim($address, '0x')), 64, '0', STR_PAD_LEFT);
    }

    public static function encodeBytes32(string $hexOrText): string
    {
        $hex = str_starts_with($hexOrText, '0x')
            ? substr($hexOrText, 2)
            : Keccak::hash($hexOrText, 256);

        $hex = strtolower($hex);

        if (strlen($hex) > 64) {
            $hex = substr($hex, 0, 64);
        }

        return str_pad($hex, 64, '0', STR_PAD_RIGHT);
    }

    public static function encodeCall(string $signature, array $args): string
    {
        $data = self::methodId($signature);

        foreach ($args as $arg) {
            if (is_array($arg) && ($arg['type'] ?? null) === 'address') {
                $data .= self::encodeAddress($arg['value']);
            } elseif (is_array($arg) && ($arg['type'] ?? null) === 'uint256') {
                $data .= self::encodeUint256($arg['value']);
            } else {
                $data .= self::encodeBytes32((string) $arg);
            }
        }

        return '0x'.$data;
    }

    public static function technicalIdToBytes32(string $technicalId): string
    {
        return '0x'.Keccak::hash($technicalId, 256);
    }

    public static function hashToBytes32(string $hash): string
    {
        $hex = str_starts_with($hash, '0x') ? substr($hash, 2) : $hash;

        return '0x'.str_pad(strtolower($hex), 64, '0', STR_PAD_LEFT);
    }
}
