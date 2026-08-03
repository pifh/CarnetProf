<?php

use App\Services\ClassPhotoPdfExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function makeTestImage(string $path, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    imagejpeg($image, $path);
    imagedestroy($image);
}

beforeEach(function () {
    $this->scratchDir = sys_get_temp_dir().'/pdf-extractor-test-'.uniqid();
    File::makeDirectory($this->scratchDir, recursive: true);
});

afterEach(function () {
    File::deleteDirectory($this->scratchDir);
});

it('keeps portrait-ish images and discards oversized or oddly-shaped ones', function () {
    Process::fake();

    // A plausible student photo.
    makeTestImage("{$this->scratchDir}/img-000.jpg", 200, 266);
    // A decorative divider line (extreme aspect ratio).
    makeTestImage("{$this->scratchDir}/img-001.jpg", 600, 10);
    // A tiny icon.
    makeTestImage("{$this->scratchDir}/img-002.jpg", 20, 20);
    // A full scanned page (both dimensions oversized).
    makeTestImage("{$this->scratchDir}/img-003.jpg", 2100, 2900);
    // Another plausible, near-square photo.
    makeTestImage("{$this->scratchDir}/img-004.jpg", 180, 180);

    $candidates = (new ClassPhotoPdfExtractor)->extract("{$this->scratchDir}/source.pdf", $this->scratchDir);

    expect($candidates)->toBe([
        "{$this->scratchDir}/img-000.jpg",
        "{$this->scratchDir}/img-004.jpg",
    ]);
});

it('returns candidates sorted in extraction order', function () {
    Process::fake();

    makeTestImage("{$this->scratchDir}/img-010.jpg", 150, 200);
    makeTestImage("{$this->scratchDir}/img-002.jpg", 150, 200);
    makeTestImage("{$this->scratchDir}/img-001.jpg", 150, 200);

    $candidates = (new ClassPhotoPdfExtractor)->extract("{$this->scratchDir}/source.pdf", $this->scratchDir);

    expect($candidates)->toBe([
        "{$this->scratchDir}/img-001.jpg",
        "{$this->scratchDir}/img-002.jpg",
        "{$this->scratchDir}/img-010.jpg",
    ]);
});

it('returns an empty array when pdfimages produces nothing exploitable', function () {
    Process::fake();

    $candidates = (new ClassPhotoPdfExtractor)->extract("{$this->scratchDir}/source.pdf", $this->scratchDir);

    expect($candidates)->toBe([]);
});

it('throws when pdfimages fails', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'not a pdf', exitCode: 1),
    ]);

    (new ClassPhotoPdfExtractor)->extract("{$this->scratchDir}/source.pdf", $this->scratchDir);
})->throws(RuntimeException::class);
