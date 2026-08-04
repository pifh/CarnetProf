<?php

namespace App\Services\BackupProviders;

interface BackupProviderDefinition
{
    public function key(): string;

    public function label(): string;

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public function diskConfig(array $credentials): array;
}
