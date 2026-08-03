<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Extracts embedded raster images from a class photo-sheet PDF via poppler-utils'
 * `pdfimages` binary, then keeps only the ones that plausibly look like individual
 * student photos — filtering out logos, layout lines, and full-page scans.
 */
class ClassPhotoPdfExtractor
{
    private const MIN_DIMENSION = 80;

    private const MAX_DIMENSION = 2000;

    private const MIN_RATIO = 0.6;

    private const MAX_RATIO = 1.35;

    /**
     * Returns candidate file paths sorted in the order they appear in the PDF —
     * for a studio-produced class photo sheet, this is almost always the layout
     * order (top-to-bottom, left-to-right).
     *
     * @return string[]
     */
    public function extract(string $pdfPath, string $outputDir): array
    {
        $binary = config('services.pdf_tools.pdfimages_binary', 'pdfimages');

        $result = Process::path(dirname($pdfPath))
            ->timeout(60)
            ->run([$binary, '-all', $pdfPath, $outputDir.'/img']);

        if ($result->failed()) {
            throw new RuntimeException("pdfimages a échoué : {$result->errorOutput()}");
        }

        $files = glob($outputDir.'/img-*') ?: [];
        sort($files, SORT_NATURAL);

        return array_values(array_filter($files, fn (string $file) => $this->looksLikeAPhoto($file)));
    }

    private function looksLikeAPhoto(string $file): bool
    {
        $size = @getimagesize($file);

        if ($size === false) {
            return false;
        }

        [$width, $height] = $size;

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            return false;
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return false;
        }

        $ratio = $width / $height;

        return $ratio >= self::MIN_RATIO && $ratio <= self::MAX_RATIO;
    }
}
