<?php

namespace App\Services;

use App\Models\BackupDestination;
use App\Models\Concerns\BelongsToTeacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;
use ZipArchive;

/**
 * Builds a zip of the currently authenticated teacher's own data (never
 * takes a $teacher argument — always Auth::user() — so it can never
 * accidentally export the wrong account) and pushes it to each of that
 * teacher's active personal BackupDestinations.
 */
class PersonalDataExporter
{
    public function __construct(private readonly BackupDestinationDiskFactory $diskFactory) {}

    /**
     * @return array<int, bool> keyed by BackupDestination id
     */
    public function export(): array
    {
        $teacher = Auth::user();
        $zipPath = $this->buildZip($teacher);

        $results = [];

        foreach (BackupDestination::query()->where('user_id', $teacher->id)->where('is_active', true)->get() as $destination) {
            try {
                $disk = $this->diskFactory->make($destination);
                $disk->put(basename($zipPath), file_get_contents($zipPath));
                $destination->update(['last_used_at' => now()]);
                $results[$destination->id] = true;
            } catch (Throwable $e) {
                report($e);
                $results[$destination->id] = false;
            }
        }

        File::delete($zipPath);

        return $results;
    }

    private function buildZip(User $teacher): string
    {
        $tempDir = Storage::disk('local')->path('personal-backup-temp');
        File::ensureDirectoryExists($tempDir);

        $zipPath = $tempDir.'/carnetprof-'.$teacher->id.'-'.now()->format('Y-m-d-His').'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $zip->addFromString('data/profile.json', json_encode($teacher->only(['name', 'email', 'avatar']), JSON_PRETTY_PRINT));

        if ($teacher->avatar) {
            $zip->addFromString('files/'.$teacher->avatar, Storage::disk('public')->get($teacher->avatar));
        }

        foreach ($this->discoverRootModelClasses() as $modelClass) {
            $records = $modelClass::query()->with($this->discoverChildRelationNames($modelClass))->get();

            $zip->addFromString('data/'.Str::snake(class_basename($modelClass)).'.json', $records->toJson(JSON_PRETTY_PRINT));

            $this->addReferencedFiles($zip, $records->toArray());
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function discoverRootModelClasses(): array
    {
        return collect(File::files(app_path('Models')))
            ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension())
            ->filter(fn ($class) => class_exists($class) && in_array(BelongsToTeacher::class, class_uses_recursive($class), true))
            ->values()
            ->all();
    }

    /**
     * Reflects a model's own public, zero-argument, child-ward relation
     * methods (HasMany/HasOne/MorphMany/MorphOne) so records like ImportRow
     * or StudentEventAttachment — which don't use BelongsToTeacher
     * themselves, only reached via a parent relation — aren't silently
     * dropped from the export. BelongsTo/BelongsToMany/MorphTo are
     * deliberately excluded to stay a single level deep with no cycle risk.
     *
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private function discoverChildRelationNames(string $modelClass): array
    {
        return collect((new ReflectionClass($modelClass))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) {
                if ($method->getNumberOfParameters() !== 0 || ! $method->hasReturnType()) {
                    return false;
                }

                $returnType = (string) $method->getReturnType();

                return is_a($returnType, Relation::class, true)
                    && ! is_a($returnType, BelongsTo::class, true)
                    && ! is_a($returnType, BelongsToMany::class, true)
                    && ! is_a($returnType, MorphTo::class, true);
            })
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->values()
            ->all();
    }

    /**
     * Walks every serialized record (root + eager-loaded children) looking
     * for an attribute literally named "path" with a truthy string value —
     * the convention every file-backed model in this app (StudentPhoto,
     * StudentEventAttachment) already uses, always on the "public" disk.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    private function addReferencedFiles(ZipArchive $zip, array $records): void
    {
        foreach ($records as $record) {
            foreach ($record as $key => $value) {
                if ($key === 'path' && is_string($value) && $value !== '' && Storage::disk('public')->exists($value)) {
                    $zip->addFromString('files/'.$value, Storage::disk('public')->get($value));
                } elseif (is_array($value)) {
                    $this->addReferencedFiles($zip, array_is_list($value) ? $value : [$value]);
                }
            }
        }
    }
}
