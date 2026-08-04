<?php

namespace App\Services\BackupProviders;

class SftpProvider implements BackupProviderDefinition
{
    public function key(): string
    {
        return 'sftp';
    }

    public function label(): string
    {
        return 'SFTP';
    }

    public function diskConfig(array $credentials): array
    {
        return [
            'driver' => 'sftp',
            'host' => $credentials['host'] ?? null,
            'username' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? null,
            'privateKey' => $credentials['private_key'] ?? null,
            'passphrase' => $credentials['passphrase'] ?? null,
            'port' => (int) ($credentials['port'] ?? 22),
            'root' => $credentials['root'] ?? '/',
        ];
    }
}
