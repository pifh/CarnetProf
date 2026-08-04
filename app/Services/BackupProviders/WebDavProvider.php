<?php

namespace App\Services\BackupProviders;

class WebDavProvider implements BackupProviderDefinition
{
    public function key(): string
    {
        return 'webdav';
    }

    public function label(): string
    {
        return 'WebDAV (Nextcloud, ownCloud...)';
    }

    public function diskConfig(array $credentials): array
    {
        return [
            'driver' => 'webdav',
            'baseUri' => $credentials['base_uri'] ?? null,
            'userName' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? null,
        ];
    }
}
