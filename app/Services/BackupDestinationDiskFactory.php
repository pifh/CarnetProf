<?php

namespace App\Services;

use App\Models\BackupDestination;
use App\Services\BackupProviders\BackupProviderRegistry;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BackupDestinationDiskFactory
{
    public function make(BackupDestination $destination): Filesystem
    {
        return Storage::build($this->configFor($destination));
    }

    /**
     * @return array<string, mixed>
     */
    public function configFor(BackupDestination $destination): array
    {
        $provider = BackupProviderRegistry::all()[$destination->provider]
            ?? throw new InvalidArgumentException("Fournisseur de sauvegarde inconnu : [{$destination->provider}]");

        return $provider->diskConfig($destination->credentials ?? []);
    }
}
