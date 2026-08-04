<?php

namespace App\Services\BackupProviders;

class BackupProviderRegistry
{
    /**
     * @return array<string, BackupProviderDefinition>
     */
    public static function all(): array
    {
        $providers = [
            new FtpProvider,
            new SftpProvider,
            new S3Provider,
            new WebDavProvider,
        ];

        return collect($providers)->keyBy(fn (BackupProviderDefinition $provider) => $provider->key())->all();
    }
}
