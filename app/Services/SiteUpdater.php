<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Backs the "Mises à jour" admin page: checks GitHub for commits ahead of
 * the deployed HEAD, and triggers deploy/update.sh (git pull, composer,
 * npm build, migrate) as a subprocess. Every git/shell call is wrapped so a
 * missing binary, offline server, or non-git checkout degrades to a
 * reported error instead of a crash — this runs from a web request.
 */
class SiteUpdater
{
    /**
     * @return array{hash: ?string, summary: ?string, date: ?string}
     */
    public function currentCommit(): array
    {
        $result = Process::path(base_path())->run(['git', 'log', '-1', '--format=%h|%s|%ci']);

        if (! $result->successful()) {
            return ['hash' => null, 'summary' => null, 'date' => null];
        }

        [$hash, $summary, $date] = array_pad(explode('|', trim($result->output()), 3), 3, null);

        return ['hash' => $hash, 'summary' => $summary, 'date' => $date];
    }

    /**
     * @return array{error: ?string, behind_by: int, commits: array<int, string>}
     */
    public function checkForUpdates(): array
    {
        $remote = config('deploy.remote');
        $branch = config('deploy.branch');

        $fetch = Process::path(base_path())->timeout(30)->run(['git', 'fetch', '--quiet', $remote, $branch]);

        if (! $fetch->successful()) {
            return [
                'error' => trim($fetch->errorOutput()) ?: 'Impossible de contacter le dépôt GitHub.',
                'behind_by' => 0,
                'commits' => [],
            ];
        }

        $remoteRef = "{$remote}/{$branch}";

        $count = Process::path(base_path())->run(['git', 'rev-list', '--count', "HEAD..{$remoteRef}"]);
        $log = Process::path(base_path())->run(['git', 'log', "HEAD..{$remoteRef}", '--pretty=format:%h %s']);

        if (! $count->successful()) {
            return [
                'error' => trim($count->errorOutput()) ?: "Impossible de comparer avec {$remoteRef}.",
                'behind_by' => 0,
                'commits' => [],
            ];
        }

        return [
            'error' => null,
            'behind_by' => (int) trim($count->output()),
            'commits' => $log->successful() ? array_values(array_filter(explode("\n", trim($log->output())))) : [],
        ];
    }

    /**
     * @return array{successful: bool, output: string, exit_code: ?int}
     */
    public function runUpdate(): array
    {
        $logPath = storage_path('logs/deploy-update.log');
        $offset = is_file($logPath) ? filesize($logPath) : 0;

        $result = Process::path(base_path())
            ->timeout(config('deploy.timeout'))
            ->run(['bash', 'deploy/update.sh']);

        // deploy/update.sh redirects its own output straight to that log
        // file rather than back to us (see the script for why), so read
        // back whatever it appended during this run instead of
        // $result->output(), which is empty by design. Falls back to the
        // captured process output for a failure so early that the script
        // never got to open the log itself (e.g. "bash" not found).
        $output = is_file($logPath) ? trim(substr(file_get_contents($logPath), $offset)) : '';

        return [
            'successful' => $result->successful(),
            'output' => $output !== '' ? $output : trim($result->output()."\n".$result->errorOutput()),
            'exit_code' => $result->exitCode(),
        ];
    }
}
