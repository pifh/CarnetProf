<?php

use App\Models\BackupDestination;
use App\Services\BackupDestinationDiskFactory;

it('builds an ftp disk config', function () {
    $destination = BackupDestination::factory()->make([
        'provider' => 'ftp',
        'credentials' => ['host' => 'ftp.example.com', 'username' => 'u', 'password' => 'p'],
    ]);

    $config = app(BackupDestinationDiskFactory::class)->configFor($destination);

    expect($config)->toMatchArray([
        'driver' => 'ftp',
        'host' => 'ftp.example.com',
        'username' => 'u',
        'password' => 'p',
        'port' => 21,
        'root' => '/',
        'passive' => true,
        'ssl' => false,
    ]);
});

it('builds an sftp disk config with default port 22', function () {
    $destination = BackupDestination::factory()->make([
        'provider' => 'sftp',
        'credentials' => ['host' => 'sftp.example.com', 'username' => 'u', 'password' => 'p'],
    ]);

    $config = app(BackupDestinationDiskFactory::class)->configFor($destination);

    expect($config['driver'])->toBe('sftp')
        ->and($config['port'])->toBe(22)
        ->and($config['root'])->toBe('/');
});

it('builds an s3-compatible disk config defaulting to path-style and auto region', function () {
    $destination = BackupDestination::factory()->make([
        'provider' => 's3',
        'credentials' => ['key' => 'k', 'secret' => 's', 'bucket' => 'b'],
    ]);

    $config = app(BackupDestinationDiskFactory::class)->configFor($destination);

    expect($config['driver'])->toBe('s3')
        ->and($config['bucket'])->toBe('b')
        ->and($config['region'])->toBe('auto')
        ->and($config['use_path_style_endpoint'])->toBeTrue();
});

it('builds a webdav disk config', function () {
    $destination = BackupDestination::factory()->make([
        'provider' => 'webdav',
        'credentials' => ['base_uri' => 'https://cloud.example.com/dav', 'username' => 'u', 'password' => 'p'],
    ]);

    $config = app(BackupDestinationDiskFactory::class)->configFor($destination);

    expect($config)->toBe([
        'driver' => 'webdav',
        'baseUri' => 'https://cloud.example.com/dav',
        'userName' => 'u',
        'password' => 'p',
    ]);
});

it('rejects an unknown provider', function () {
    $destination = BackupDestination::factory()->make(['provider' => 'dropbox']);

    app(BackupDestinationDiskFactory::class)->configFor($destination);
})->throws(InvalidArgumentException::class);
