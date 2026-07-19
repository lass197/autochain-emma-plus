<?php

namespace App\Services\Web3;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EthereumRpcClient
{
    public function __construct(
        protected string $rpcUrl,
    ) {}

    public function call(string $method, array $params = []): mixed
    {
        $response = Http::timeout(15)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('RPC inaccessible : '.$this->rpcUrl);
        }

        $json = $response->json();

        if (isset($json['error'])) {
            $message = $json['error']['message'] ?? 'Erreur RPC';
            throw new RuntimeException($message);
        }

        return $json['result'] ?? null;
    }

    public function chainId(): int
    {
        return hexdec($this->call('eth_chainId') ?? '0x0');
    }

    public function blockNumber(): int
    {
        return hexdec($this->call('eth_blockNumber') ?? '0x0');
    }

    public function getTransactionCount(string $address): int
    {
        return hexdec($this->call('eth_getTransactionCount', [$address, 'latest']) ?? '0x0');
    }

    public function gasPrice(): string
    {
        return $this->call('eth_gasPrice') ?? '0x3b9aca00';
    }

    public function sendRawTransaction(string $rawTx): string
    {
        return (string) $this->call('eth_sendRawTransaction', [$rawTx]);
    }

    public function getTransactionReceipt(string $txHash): ?array
    {
        $result = $this->call('eth_getTransactionReceipt', [$txHash]);

        return is_array($result) ? $result : null;
    }

    public function isReachable(): bool
    {
        try {
            $this->chainId();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
