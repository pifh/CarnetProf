<?php

namespace App\Services\BackupProviders;

class FtpProvider implements BackupProviderDefinition
{
    public function key(): string
    {
        return 'ftp';
    }

    public function label(): string
    {
        return 'FTP';
    }

    public function diskConfig(array $credentials): array
    {
        return [
            'driver' => 'ftp',
            'host' => $credentials['host'] ?? null,
            'username' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? null,
            'port' => (int) ($credentials['port'] ?? 21),
            'root' => $credentials['root'] ?? '/',
            'passive' => true,
            'ssl' => false,
            'timeout' => 30,
        ];
    }
}
