<?php

namespace App\Services\BackupProviders;

class S3Provider implements BackupProviderDefinition
{
    public function key(): string
    {
        return 's3';
    }

    public function label(): string
    {
        return 'S3 (ou compatible : OVH, Scaleway, Backblaze...)';
    }

    public function diskConfig(array $credentials): array
    {
        return [
            'driver' => 's3',
            'key' => $credentials['key'] ?? null,
            'secret' => $credentials['secret'] ?? null,
            'region' => ($credentials['region'] ?? null) ?: 'auto',
            'bucket' => $credentials['bucket'] ?? null,
            'endpoint' => $credentials['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($credentials['use_path_style'] ?? true),
            'root' => $credentials['root'] ?? null,
            'throw' => false,
        ];
    }
}
