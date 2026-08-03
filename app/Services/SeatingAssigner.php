<?php

namespace App\Services;

/**
 * Places a pool of students onto the desks of a seating plan, honoring per-student
 * constraints: a set of allowed rows, a set of allowed columns, and pair
 * constraints (sit together, never together, or as far apart as possible).
 *
 * Pure — no Eloquent/DB access, only plain arrays keyed by student id and desk id —
 * so it can be unit-tested without a database. Mirrors GroupAssigner's architecture:
 * union-find to merge "next to" pairs into atomic units that must share one desk,
 * greedy initial placement, then bounded hill-climbing that optimizes a single
 * additive weighted cost function combining every active criterion.
 */
final class SeatingAssigner
{
    private const W_NOT_NEXT_TO = 300.0;

    private const W_FAR_FROM = 200.0;

    private const W_ALLOWED_ROW = 50.0;

    private const W_ALLOWED_COLUMN = 50.0;

    public function __construct(
        private readonly int $maxIterations = 2000,
        private readonly int $staleLimit = 300,
    ) {}

    public static function pairKey(int $a, int $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    /**
     * @param  int[]  $studentIds  pool to seat, no duplicates required
     * @param  array<int, array{row: int, col: int, capacity: int}>  $desks  deskId => desk info
     * @param  array<int, int[]>  $allowedRows  studentId => allowed row indexes, empty/absent means no restriction
     * @param  array<int, int[]>  $allowedColumns  studentId => allowed column indexes, empty/absent means no restriction
     * @param  array<int, array{0: int, 1: int}>  $nextToPairs
     * @param  array<int, array{0: int, 1: int}>  $notNextToPairs
     * @param  array<int, array{0: int, 1: int}>  $farFromPairs
     */
    public function assign(
        array $studentIds,
        array $desks,
        array $allowedRows = [],
        array $allowedColumns = [],
        array $nextToPairs = [],
        array $notNextToPairs = [],
        array $farFromPairs = [],
    ): SeatingAssignmentResult {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($studentIds === [] || $desks === []) {
            return new SeatingAssignmentResult([], [], 0.0);
        }

        $maxDeskCapacity = max(array_column($desks, 'capacity'));

        [$units, $conflicts] = $this->buildUnits($studentIds, $nextToPairs, $notNextToPairs, $farFromPairs, $maxDeskCapacity);

        $assignment = $this->placeGreedy($units, $desks, $allowedRows, $allowedColumns);

        [$assignment, $cost] = $this->refine($units, $assignment, $desks, $allowedRows, $allowedColumns, $notNextToPairs, $farFromPairs);

        $unassigned = [];
        foreach ($assignment as $unitIndex => $deskId) {
            if ($deskId === null) {
                $unassigned = [...$unassigned, ...$units[$unitIndex]['members']];
            }
        }
        if ($unassigned !== []) {
            $conflicts[] = ['type' => 'insufficient_desks', 'members' => $unassigned];
        }

        return new SeatingAssignmentResult($this->materializePlacements($units, $assignment), $conflicts, $cost);
    }

    /**
     * Union-find over `nextToPairs` to form atomic clusters (a lone student is a
     * cluster of size 1) that must share one desk. A pair also marked "not next
     * to" or "far from" is a contradiction — skipped and reported instead of
     * applied. A cluster too big for any desk's capacity is likewise reported
     * and dissolved back into singleton units.
     *
     * @param  int[]  $studentIds
     * @param  array<int, array{0: int, 1: int}>  $nextToPairs
     * @param  array<int, array{0: int, 1: int}>  $notNextToPairs
     * @param  array<int, array{0: int, 1: int}>  $farFromPairs
     * @return array{0: array<int, array{members: int[], size: int}>, 1: array<int, array<string, mixed>>}
     */
    private function buildUnits(array $studentIds, array $nextToPairs, array $notNextToPairs, array $farFromPairs, int $maxDeskCapacity): array
    {
        $parent = array_combine($studentIds, $studentIds);

        $find = function (int $id) use (&$parent, &$find): int {
            while ($parent[$id] !== $id) {
                $parent[$id] = $parent[$parent[$id]];
                $id = $parent[$id];
            }

            return $id;
        };

        $union = function (int $a, int $b) use (&$parent, $find): void {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootB] = $rootA;
            }
        };

        $conflictKeys = [];
        foreach ([...$notNextToPairs, ...$farFromPairs] as [$a, $b]) {
            $conflictKeys[self::pairKey((int) $a, (int) $b)] = true;
        }

        $conflicts = [];

        foreach ($nextToPairs as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;

            if (! in_array($a, $studentIds, true) || ! in_array($b, $studentIds, true)) {
                continue;
            }

            if (isset($conflictKeys[self::pairKey($a, $b)])) {
                $conflicts[] = ['type' => 'next_to_vs_conflict', 'pair' => [$a, $b]];

                continue;
            }

            $union($a, $b);
        }

        $clusters = [];
        foreach ($studentIds as $id) {
            $clusters[$find($id)][] = $id;
        }

        $units = [];
        foreach ($clusters as $members) {
            if ($maxDeskCapacity > 0 && count($members) > $maxDeskCapacity) {
                $conflicts[] = ['type' => 'next_to_too_large', 'members' => $members];

                foreach ($members as $member) {
                    $units[] = ['members' => [$member], 'size' => 1];
                }

                continue;
            }

            $units[] = ['members' => $members, 'size' => count($members)];
        }

        return [$units, $conflicts];
    }

    /**
     * Largest-clusters-first greedy placement: each unit goes to whichever desk
     * with enough free capacity best matches its members' row/column preferences.
     * Only a starting point — refine() does the real optimization across every
     * active criterion.
     *
     * @param  array<int, array{members: int[], size: int}>  $units
     * @param  array<int, array{row: int, col: int, capacity: int}>  $desks
     * @param  array<int, int[]>  $allowedRows
     * @param  array<int, int[]>  $allowedColumns
     * @return array<int, ?int> unitIndex => deskId, or null if it couldn't fit anywhere
     */
    private function placeGreedy(array $units, array $desks, array $allowedRows, array $allowedColumns): array
    {
        $remaining = array_map(fn (array $desk): int => $desk['capacity'], $desks);

        $order = array_keys($units);
        usort($order, fn (int $a, int $b) => $units[$b]['size'] <=> $units[$a]['size']);

        $assignment = [];

        foreach ($order as $unitIndex) {
            $unit = $units[$unitIndex];
            $eligible = array_keys(array_filter($remaining, fn (int $capacity) => $capacity >= $unit['size']));

            if ($eligible === []) {
                $assignment[$unitIndex] = null;

                continue;
            }

            $bestDesk = $eligible[0];
            $bestScore = null;

            foreach ($eligible as $deskId) {
                $score = $this->deskScore($desks[$deskId], $unit['members'], $allowedRows, $allowedColumns);

                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestDesk = $deskId;
                }
            }

            $assignment[$unitIndex] = $bestDesk;
            $remaining[$bestDesk] -= $unit['size'];
        }

        return $assignment;
    }

    /**
     * @param  array{row: int, col: int, capacity: int}  $desk
     * @param  int[]  $members
     * @param  array<int, int[]>  $allowedRows
     * @param  array<int, int[]>  $allowedColumns
     */
    private function deskScore(array $desk, array $members, array $allowedRows, array $allowedColumns): float
    {
        $score = 0.0;

        foreach ($members as $studentId) {
            $rows = $allowedRows[$studentId] ?? [];
            if ($rows !== [] && ! in_array($desk['row'], $rows, true)) {
                $score += self::W_ALLOWED_ROW;
            }

            $columns = $allowedColumns[$studentId] ?? [];
            if ($columns !== [] && ! in_array($desk['col'], $columns, true)) {
                $score += self::W_ALLOWED_COLUMN;
            }
        }

        return $score;
    }

    /**
     * Bounded hill-climbing over two move types, keeping a trial only if it
     * doesn't worsen the weighted cost of every active criterion:
     * - relocate: move one unit to any desk with enough free capacity for it
     *   (the move that lets units reach desks the greedy phase left empty —
     *   a same-size swap alone can never discover an untouched desk, since
     *   there's nothing there to swap with);
     * - swap: exchange the desks of two same-size units (a hard capacity
     *   guarantee, since swapping equal sizes can never overflow either desk).
     *
     * @param  array<int, array{members: int[], size: int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int}>  $desks
     * @param  array<int, int[]>  $allowedRows
     * @param  array<int, int[]>  $allowedColumns
     * @param  array<int, array{0: int, 1: int}>  $notNextToPairs
     * @param  array<int, array{0: int, 1: int}>  $farFromPairs
     * @return array{0: array<int, ?int>, 1: float}
     */
    private function refine(
        array $units,
        array $assignment,
        array $desks,
        array $allowedRows,
        array $allowedColumns,
        array $notNextToPairs,
        array $farFromPairs,
    ): array {
        $cost = $this->cost($units, $assignment, $desks, $allowedRows, $allowedColumns, $notNextToPairs, $farFromPairs);

        $unitIndexes = array_keys($units);

        if (count($unitIndexes) < 2) {
            return [$assignment, $cost];
        }

        $usage = $this->deskUsage($units, $assignment, $desks);
        $deskIds = array_keys($desks);

        $stale = 0;
        for ($iteration = 0; $iteration < $this->maxIterations && $stale < $this->staleLimit; $iteration++) {
            if (mt_rand(0, 1) === 0) {
                $i = $unitIndexes[array_rand($unitIndexes)];
                $targetDesk = $deskIds[array_rand($deskIds)];
                $currentDesk = $assignment[$i];

                if ($targetDesk === $currentDesk || ($desks[$targetDesk]['capacity'] - $usage[$targetDesk]) < $units[$i]['size']) {
                    $stale++;

                    continue;
                }

                $trial = $assignment;
                $trial[$i] = $targetDesk;

                $newCost = $this->cost($units, $trial, $desks, $allowedRows, $allowedColumns, $notNextToPairs, $farFromPairs);

                if ($newCost > $cost) {
                    $stale++;

                    continue;
                }

                $assignment = $trial;
                $cost = $newCost;
                $stale = 0;

                if ($currentDesk !== null) {
                    $usage[$currentDesk] -= $units[$i]['size'];
                }
                $usage[$targetDesk] += $units[$i]['size'];

                continue;
            }

            $i = $unitIndexes[array_rand($unitIndexes)];
            $j = $unitIndexes[array_rand($unitIndexes)];

            if ($i === $j || $assignment[$i] === $assignment[$j] || $units[$i]['size'] !== $units[$j]['size']) {
                $stale++;

                continue;
            }

            $trial = $assignment;
            [$trial[$i], $trial[$j]] = [$trial[$j], $trial[$i]];

            $newCost = $this->cost($units, $trial, $desks, $allowedRows, $allowedColumns, $notNextToPairs, $farFromPairs);

            if ($newCost <= $cost) {
                $assignment = $trial;
                $cost = $newCost;
                $stale = 0;
            } else {
                $stale++;
            }
        }

        return [$assignment, $cost];
    }

    /**
     * @param  array<int, array{members: int[], size: int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int}>  $desks
     * @return array<int, int> deskId => used capacity
     */
    private function deskUsage(array $units, array $assignment, array $desks): array
    {
        $usage = array_fill_keys(array_keys($desks), 0);

        foreach ($assignment as $unitIndex => $deskId) {
            if ($deskId !== null) {
                $usage[$deskId] += $units[$unitIndex]['size'];
            }
        }

        return $usage;
    }

    /**
     * @param  array<int, array{members: int[], size: int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int}>  $desks
     * @param  array<int, int[]>  $allowedRows
     * @param  array<int, int[]>  $allowedColumns
     * @param  array<int, array{0: int, 1: int}>  $notNextToPairs
     * @param  array<int, array{0: int, 1: int}>  $farFromPairs
     */
    private function cost(
        array $units,
        array $assignment,
        array $desks,
        array $allowedRows,
        array $allowedColumns,
        array $notNextToPairs,
        array $farFromPairs,
    ): float {
        $studentDesk = $this->studentDeskMap($units, $assignment);

        $cost = 0.0;

        foreach ($studentDesk as $studentId => $deskId) {
            $desk = $desks[$deskId];

            $rows = $allowedRows[$studentId] ?? [];
            if ($rows !== [] && ! in_array($desk['row'], $rows, true)) {
                $cost += self::W_ALLOWED_ROW;
            }

            $columns = $allowedColumns[$studentId] ?? [];
            if ($columns !== [] && ! in_array($desk['col'], $columns, true)) {
                $cost += self::W_ALLOWED_COLUMN;
            }
        }

        foreach ($notNextToPairs as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;
            if (isset($studentDesk[$a], $studentDesk[$b]) && $studentDesk[$a] === $studentDesk[$b]) {
                $cost += self::W_NOT_NEXT_TO;
            }
        }

        foreach ($farFromPairs as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;
            if (! isset($studentDesk[$a], $studentDesk[$b])) {
                continue;
            }

            $deskA = $desks[$studentDesk[$a]];
            $deskB = $desks[$studentDesk[$b]];
            $distance = abs($deskA['row'] - $deskB['row']) + abs($deskA['col'] - $deskB['col']);

            $cost += self::W_FAR_FROM / (1 + $distance);
        }

        return $cost;
    }

    /**
     * @param  array<int, array{members: int[], size: int}>  $units
     * @param  array<int, ?int>  $assignment
     * @return array<int, int> studentId => deskId
     */
    private function studentDeskMap(array $units, array $assignment): array
    {
        $map = [];

        foreach ($assignment as $unitIndex => $deskId) {
            if ($deskId === null) {
                continue;
            }

            foreach ($units[$unitIndex]['members'] as $studentId) {
                $map[$studentId] = $deskId;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{members: int[], size: int}>  $units
     * @param  array<int, ?int>  $assignment
     * @return array<int, int> studentId => deskId
     */
    private function materializePlacements(array $units, array $assignment): array
    {
        return $this->studentDeskMap($units, $assignment);
    }
}
