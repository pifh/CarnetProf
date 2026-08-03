<?php

namespace App\Services;

/**
 * Places a pool of students onto the desks of a seating plan, honoring per-student
 * constraints: a set of allowed rows, a set of allowed columns, pair constraints
 * (sit together, never together, or as far apart as possible), manually locked
 * placements, and optional class-wide criteria (gender alternation, avoiding
 * previously-used seats/neighbors, and height-based front/back ordering).
 *
 * Pure — no Eloquent/DB access, only plain arrays keyed by student id and desk id —
 * so it can be unit-tested without a database. Mirrors GroupAssigner's architecture:
 * union-find to merge "next to" pairs into atomic units that must share one desk,
 * greedy initial placement, then bounded hill-climbing that optimizes a single
 * additive weighted cost function combining every active criterion.
 *
 * "Column" throughout means the absolute position of an individual seat in the
 * room (each desk carries a `baseColumn` — the cumulative capacity of every desk
 * before it in its row, blocked slots included — plus the seat's own offset
 * within its desk), not the index of the desk itself: two students sharing a
 * 2-seat desk sit in two different columns.
 */
final class SeatingAssigner
{
    private const W_NOT_NEXT_TO = 300.0;

    private const W_FAR_FROM = 200.0;

    private const W_ALLOWED_ROW = 50.0;

    private const W_ALLOWED_COLUMN = 50.0;

    private const W_SAME_SEX = 40.0;

    private const W_REPEAT_SEAT = 60.0;

    private const W_REPEAT_NEIGHBOR = 5.0;

    private const W_HEIGHT_PER_CM = 8.0;

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
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks  deskId => desk info
     * @param  array<int, int[]>  $allowedRows  studentId => allowed row indexes, empty/absent means no restriction
     * @param  array<int, int[]>  $allowedColumns  studentId => allowed absolute column indexes, empty/absent means no restriction
     * @param  array<int, array{0: int, 1: int}>  $nextToPairs
     * @param  array<int, array{0: int, 1: int}>  $notNextToPairs
     * @param  array<int, array{0: int, 1: int}>  $farFromPairs
     * @param  array<int, int>  $lockedPlacements  studentId => deskId, pinned and never moved
     * @param  array<int, int>  $lockedSeatOffsets  studentId => their existing 0-based seat offset within the locked desk (must be preserved exactly, since that seat is never rewritten)
     * @param  array<int, string|null>  $sexes  studentId => 'f'|'m'|'other'|null
     * @param  array<int, string[]>  $previousSeatPositions  studentId => list of "row-col" desk positions occupied in past plans
     * @param  array<string, int>  $previousNeighborCounts  pairKey => times seated at the same desk in past plans
     * @param  array<int, int|null>  $heights  studentId => height in cm, or null
     */
    public function assign(
        array $studentIds,
        array $desks,
        array $allowedRows = [],
        array $allowedColumns = [],
        array $nextToPairs = [],
        array $notNextToPairs = [],
        array $farFromPairs = [],
        array $lockedPlacements = [],
        array $lockedSeatOffsets = [],
        array $sexes = [],
        bool $avoidSameSexNeighbors = false,
        array $previousSeatPositions = [],
        bool $avoidRepeatSeats = false,
        array $previousNeighborCounts = [],
        bool $avoidRepeatNeighbors = false,
        array $heights = [],
        bool $heightOrdering = false,
        int $heightMarginCm = 10,
    ): SeatingAssignmentResult {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($studentIds === [] || $desks === []) {
            return new SeatingAssignmentResult([], [], [], 0.0);
        }

        $maxDeskCapacity = max(array_column($desks, 'capacity'));

        [$units, $conflicts] = $this->buildUnits($studentIds, $nextToPairs, $notNextToPairs, $farFromPairs, $maxDeskCapacity, $lockedPlacements);

        $remaining = array_map(fn (array $desk): int => $desk['capacity'], $desks);
        $assignment = [];
        $freeUnitIndexes = [];

        foreach ($units as $i => $unit) {
            if ($unit['fixedDesk'] !== null && isset($desks[$unit['fixedDesk']])) {
                $assignment[$i] = $unit['fixedDesk'];
                $remaining[$unit['fixedDesk']] = max(0, $remaining[$unit['fixedDesk']] - $unit['size']);
            } else {
                $assignment[$i] = null;
                $freeUnitIndexes[] = $i;
            }
        }

        $criteria = compact(
            'allowedRows', 'allowedColumns', 'notNextToPairs', 'farFromPairs',
            'sexes', 'avoidSameSexNeighbors', 'previousSeatPositions', 'avoidRepeatSeats',
            'previousNeighborCounts', 'avoidRepeatNeighbors', 'heights', 'heightOrdering', 'heightMarginCm',
            'lockedSeatOffsets',
        );

        $assignment = $this->placeGreedy($units, $freeUnitIndexes, $assignment, $remaining, $desks, $allowedRows, $allowedColumns);

        [$assignment, $cost] = $this->refine($units, $freeUnitIndexes, $assignment, $desks, $criteria);

        $unassigned = [];
        foreach ($assignment as $unitIndex => $deskId) {
            if ($deskId === null) {
                $unassigned = [...$unassigned, ...$units[$unitIndex]['members']];
            }
        }
        if ($unassigned !== []) {
            $conflicts[] = ['type' => 'insufficient_desks', 'members' => $unassigned];
        }

        [$placements, $seatOffsets] = $this->materializePlacements($units, $assignment, $desks, $allowedColumns, $lockedSeatOffsets);

        return new SeatingAssignmentResult($placements, $seatOffsets, $conflicts, $cost);
    }

    /**
     * Union-find over `nextToPairs` to form atomic clusters (a lone student is a
     * cluster of size 1) that must share one desk. A pair also marked "not next
     * to" or "far from" is a contradiction — skipped and reported instead of
     * applied. A cluster too big for any desk's capacity, or whose members are
     * locked to different desks, is likewise reported and dissolved back into
     * singleton units (each keeping its own lock, if any).
     *
     * @param  int[]  $studentIds
     * @param  array<int, array{0: int, 1: int}>  $nextToPairs
     * @param  array<int, array{0: int, 1: int}>  $notNextToPairs
     * @param  array<int, array{0: int, 1: int}>  $farFromPairs
     * @param  array<int, int>  $lockedPlacements
     * @return array{0: array<int, array{members: int[], size: int, fixedDesk: ?int}>, 1: array<int, array<string, mixed>>}
     */
    private function buildUnits(array $studentIds, array $nextToPairs, array $notNextToPairs, array $farFromPairs, int $maxDeskCapacity, array $lockedPlacements): array
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
                    $units[] = ['members' => [$member], 'size' => 1, 'fixedDesk' => $lockedPlacements[$member] ?? null];
                }

                continue;
            }

            $distinctLocks = [];
            foreach ($members as $m) {
                if (isset($lockedPlacements[$m])) {
                    $distinctLocks[$lockedPlacements[$m]] = true;
                }
            }
            $distinctLocks = array_keys($distinctLocks);

            if (count($distinctLocks) > 1) {
                $conflicts[] = ['type' => 'locked_conflict', 'members' => $members];

                foreach ($members as $m) {
                    $units[] = ['members' => [$m], 'size' => 1, 'fixedDesk' => $lockedPlacements[$m] ?? null];
                }

                continue;
            }

            $units[] = ['members' => $members, 'size' => count($members), 'fixedDesk' => $distinctLocks[0] ?? null];
        }

        return [$units, $conflicts];
    }

    /**
     * Largest-clusters-first greedy placement: each free unit goes to whichever
     * desk with enough free capacity best matches its members' row/column
     * preferences. Only a starting point — refine() does the real optimization
     * across every active criterion.
     *
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  int[]  $freeUnitIndexes
     * @param  array<int, ?int>  $assignment
     * @param  array<int, int>  $remaining  deskId => free capacity
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
     * @param  array<int, int[]>  $allowedRows
     * @param  array<int, int[]>  $allowedColumns
     * @return array<int, ?int>
     */
    private function placeGreedy(array $units, array $freeUnitIndexes, array $assignment, array $remaining, array $desks, array $allowedRows, array $allowedColumns): array
    {
        $order = $freeUnitIndexes;
        usort($order, fn (int $a, int $b) => $units[$b]['size'] <=> $units[$a]['size']);

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
     * @param  array{row: int, col: int, capacity: int, baseColumn?: int}  $desk
     * @param  int[]  $members
     * @param  array<int, int[]>  $allowedRows
     * @param  array<int, int[]>  $allowedColumns
     */
    private function deskScore(array $desk, array $members, array $allowedRows, array $allowedColumns): float
    {
        $score = 0.0;
        $baseColumn = $desk['baseColumn'] ?? $desk['col'];

        foreach ($members as $offset => $studentId) {
            $rows = $allowedRows[$studentId] ?? [];
            if ($rows !== [] && ! in_array($desk['row'], $rows, true)) {
                $score += self::W_ALLOWED_ROW;
            }

            $columns = $allowedColumns[$studentId] ?? [];
            if ($columns !== [] && ! in_array($baseColumn + $offset, $columns, true)) {
                $score += self::W_ALLOWED_COLUMN;
            }
        }

        return $score;
    }

    /**
     * Bounded hill-climbing over two move types, keeping a trial only if it
     * doesn't worsen the weighted cost of every active criterion:
     * - relocate: move one free unit to any desk with enough free capacity for
     *   it (the move that lets units reach desks the greedy phase left empty —
     *   a same-size swap alone can never discover an untouched desk, since
     *   there's nothing there to swap with);
     * - swap: exchange the desks of two same-size free units (a hard capacity
     *   guarantee, since swapping equal sizes can never overflow either desk).
     * Locked units are never chosen as movers and never appear as swap targets.
     *
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  int[]  $freeUnitIndexes
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
     * @param  array<string, mixed>  $criteria
     * @return array{0: array<int, ?int>, 1: float}
     */
    private function refine(array $units, array $freeUnitIndexes, array $assignment, array $desks, array $criteria): array
    {
        $cost = $this->cost($units, $assignment, $desks, $criteria);

        if (count($freeUnitIndexes) < 2) {
            return [$assignment, $cost];
        }

        $usage = $this->deskUsage($units, $assignment, $desks);
        $deskIds = array_keys($desks);

        $stale = 0;
        for ($iteration = 0; $iteration < $this->maxIterations && $stale < $this->staleLimit; $iteration++) {
            if (mt_rand(0, 1) === 0) {
                $i = $freeUnitIndexes[array_rand($freeUnitIndexes)];
                $targetDesk = $deskIds[array_rand($deskIds)];
                $currentDesk = $assignment[$i];

                if ($targetDesk === $currentDesk || ($desks[$targetDesk]['capacity'] - $usage[$targetDesk]) < $units[$i]['size']) {
                    $stale++;

                    continue;
                }

                $trial = $assignment;
                $trial[$i] = $targetDesk;

                $newCost = $this->cost($units, $trial, $desks, $criteria);

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

            $i = $freeUnitIndexes[array_rand($freeUnitIndexes)];
            $j = $freeUnitIndexes[array_rand($freeUnitIndexes)];

            if ($i === $j || $assignment[$i] === $assignment[$j] || $units[$i]['size'] !== $units[$j]['size']) {
                $stale++;

                continue;
            }

            $trial = $assignment;
            [$trial[$i], $trial[$j]] = [$trial[$j], $trial[$i]];

            $newCost = $this->cost($units, $trial, $desks, $criteria);

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
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
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
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
     * @param  array<string, mixed>  $criteria
     */
    private function cost(array $units, array $assignment, array $desks, array $criteria): float
    {
        $placements = $this->studentPlacements($units, $assignment, $desks, $criteria['allowedColumns'], $criteria['lockedSeatOffsets']);

        $cost = 0.0;

        foreach ($placements as $studentId => $info) {
            $rows = $criteria['allowedRows'][$studentId] ?? [];
            if ($rows !== [] && ! in_array($info['row'], $rows, true)) {
                $cost += self::W_ALLOWED_ROW;
            }

            $columns = $criteria['allowedColumns'][$studentId] ?? [];
            if ($columns !== [] && ! in_array($info['col'], $columns, true)) {
                $cost += self::W_ALLOWED_COLUMN;
            }
        }

        $studentDesk = array_map(fn (array $info) => $info['deskId'], $placements);

        foreach ($criteria['notNextToPairs'] as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;
            if (isset($studentDesk[$a], $studentDesk[$b]) && $studentDesk[$a] === $studentDesk[$b]) {
                $cost += self::W_NOT_NEXT_TO;
            }
        }

        foreach ($criteria['farFromPairs'] as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;
            if (! isset($placements[$a], $placements[$b])) {
                continue;
            }

            $distance = abs($placements[$a]['row'] - $placements[$b]['row']) + abs($placements[$a]['col'] - $placements[$b]['col']);
            $cost += self::W_FAR_FROM / (1 + $distance);
        }

        if ($criteria['avoidSameSexNeighbors'] || $criteria['avoidRepeatNeighbors']) {
            $byDesk = [];
            foreach ($placements as $studentId => $info) {
                $byDesk[$info['deskId']][] = $studentId;
            }

            foreach ($byDesk as $members) {
                $n = count($members);
                for ($x = 0; $x < $n; $x++) {
                    for ($y = $x + 1; $y < $n; $y++) {
                        if ($criteria['avoidSameSexNeighbors']) {
                            $sexA = $criteria['sexes'][$members[$x]] ?? null;
                            $sexB = $criteria['sexes'][$members[$y]] ?? null;
                            if ($sexA !== null && $sexA === $sexB) {
                                $cost += self::W_SAME_SEX;
                            }
                        }

                        if ($criteria['avoidRepeatNeighbors']) {
                            $key = self::pairKey($members[$x], $members[$y]);
                            if (isset($criteria['previousNeighborCounts'][$key])) {
                                $cost += self::W_REPEAT_NEIGHBOR * $criteria['previousNeighborCounts'][$key];
                            }
                        }
                    }
                }
            }
        }

        if ($criteria['avoidRepeatSeats']) {
            foreach ($placements as $studentId => $info) {
                $desk = $desks[$info['deskId']];
                $key = $desk['row'].'-'.$desk['col'];
                if (in_array($key, $criteria['previousSeatPositions'][$studentId] ?? [], true)) {
                    $cost += self::W_REPEAT_SEAT;
                }
            }
        }

        if ($criteria['heightOrdering']) {
            $ids = array_keys($placements);
            $n = count($ids);

            for ($x = 0; $x < $n; $x++) {
                $heightA = $criteria['heights'][$ids[$x]] ?? null;
                if ($heightA === null) {
                    continue;
                }

                for ($y = 0; $y < $n; $y++) {
                    if ($x === $y) {
                        continue;
                    }

                    $heightB = $criteria['heights'][$ids[$y]] ?? null;
                    if ($heightB === null) {
                        continue;
                    }

                    $infoA = $placements[$ids[$x]];
                    $infoB = $placements[$ids[$y]];

                    // A taller student only blocks a shorter one's view of a
                    // centered board if they sit closer to it (smaller row)
                    // and roughly in the same sightline (adjacent column).
                    if ($infoA['row'] >= $infoB['row'] || abs($infoA['col'] - $infoB['col']) > 1) {
                        continue;
                    }

                    $excess = $heightA - $heightB - $criteria['heightMarginCm'];
                    if ($excess > 0) {
                        $cost += self::W_HEIGHT_PER_CM * $excess;
                    }
                }
            }
        }

        return $cost;
    }

    /**
     * Groups every placed student by desk. Several independent units — say a
     * locked singleton and a freely-placed pair — can end up sharing the same
     * desk, so seat offsets must be sequential across *all* of a desk's
     * occupants, not restarted at 0 within each unit (which would collide).
     *
     * A locked student's offset is pinned to `lockedSeatOffsets` — their row
     * in the database is never rewritten, so the model must never "want" to
     * put someone else in that exact slot. Remaining slots at a shared desk
     * are filled (each free unit staying internally contiguous) to best
     * satisfy allowed-columns constraints — otherwise which unit is "first"
     * would be fixed by insertion order alone, making some students
     * structurally unable to ever land on the seat they actually asked for.
     *
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
     * @param  array<int, int[]>  $allowedColumns
     * @param  array<int, int>  $lockedSeatOffsets
     * @return array<int, int[]> deskId => ordered student ids (index = seat offset)
     */
    private function deskOccupants(array $units, array $assignment, array $desks, array $allowedColumns, array $lockedSeatOffsets): array
    {
        $blocksByDesk = [];

        foreach ($assignment as $unitIndex => $deskId) {
            if ($deskId === null) {
                continue;
            }

            $members = $units[$unitIndex]['members'];
            $lockedOffset = count($members) === 1 ? ($lockedSeatOffsets[$members[0]] ?? null) : null;

            $blocksByDesk[$deskId][] = ['members' => $members, 'lockedOffset' => $lockedOffset];
        }

        $byDesk = [];

        foreach ($blocksByDesk as $deskId => $blocks) {
            if (count($blocks) <= 1) {
                $byDesk[$deskId] = $blocks[0]['members'] ?? [];

                continue;
            }

            $baseColumn = $desks[$deskId]['baseColumn'] ?? $desks[$deskId]['col'];
            $byDesk[$deskId] = $this->bestBlockOrder($blocks, $baseColumn, $allowedColumns);
        }

        return $byDesk;
    }

    /**
     * @param  array<int, array{members: int[], lockedOffset: ?int}>  $blocks
     * @param  array<int, int[]>  $allowedColumns
     * @return int[]
     */
    private function bestBlockOrder(array $blocks, int $baseColumn, array $allowedColumns): array
    {
        $capacity = array_sum(array_map(fn (array $block) => count($block['members']), $blocks));
        $slots = array_fill(0, $capacity, null);

        $freeBlocks = [];
        foreach ($blocks as $block) {
            if ($block['lockedOffset'] !== null && $block['lockedOffset'] >= 0 && $block['lockedOffset'] < $capacity) {
                $slots[$block['lockedOffset']] = $block['members'][0];
            } else {
                $freeBlocks[] = $block['members'];
            }
        }

        $freeSlotIndexes = array_keys(array_filter($slots, fn ($studentId) => $studentId === null));

        $bestSlots = null;
        $bestMismatches = null;

        foreach ($this->permutations(array_keys($freeBlocks)) as $order) {
            $flatFree = [];
            foreach ($order as $blockIndex) {
                array_push($flatFree, ...$freeBlocks[$blockIndex]);
            }

            if (count($flatFree) !== count($freeSlotIndexes)) {
                continue;
            }

            $trialSlots = $slots;
            foreach ($freeSlotIndexes as $i => $slotIndex) {
                $trialSlots[$slotIndex] = $flatFree[$i];
            }

            $mismatches = 0;
            foreach ($trialSlots as $offset => $studentId) {
                $columns = $allowedColumns[$studentId] ?? [];
                if ($columns !== [] && ! in_array($baseColumn + $offset, $columns, true)) {
                    $mismatches++;
                }
            }

            if ($bestMismatches === null || $mismatches < $bestMismatches) {
                $bestMismatches = $mismatches;
                $bestSlots = $trialSlots;

                if ($mismatches === 0) {
                    break;
                }
            }
        }

        if ($bestSlots !== null) {
            return $bestSlots;
        }

        // No free blocks (every occupant is locked) or the search found
        // nothing better: fall back to the locked slots as-is, in order.
        return array_values(array_filter($slots, fn ($studentId) => $studentId !== null));
    }

    /**
     * @param  int[]  $items
     * @return array<int, int[]>
     */
    private function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $result = [];

        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);

            foreach ($this->permutations(array_values($rest)) as $permutation) {
                $result[] = [$item, ...$permutation];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
     * @param  array<int, int[]>  $allowedColumns
     * @param  array<int, int>  $lockedSeatOffsets
     * @return array<int, array{deskId: int, row: int, col: int}> studentId => placement info, absolute column
     */
    private function studentPlacements(array $units, array $assignment, array $desks, array $allowedColumns, array $lockedSeatOffsets): array
    {
        $placements = [];

        foreach ($this->deskOccupants($units, $assignment, $desks, $allowedColumns, $lockedSeatOffsets) as $deskId => $members) {
            $desk = $desks[$deskId];
            $baseColumn = $desk['baseColumn'] ?? $desk['col'];

            foreach ($members as $offset => $studentId) {
                $placements[$studentId] = [
                    'deskId' => $deskId,
                    'row' => $desk['row'],
                    'col' => $baseColumn + $offset,
                ];
            }
        }

        return $placements;
    }

    /**
     * @param  array<int, array{members: int[], size: int, fixedDesk: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, array{row: int, col: int, capacity: int, baseColumn?: int}>  $desks
     * @param  array<int, int[]>  $allowedColumns
     * @param  array<int, int>  $lockedSeatOffsets
     * @return array{0: array<int, int>, 1: array<int, int>} studentId => deskId, studentId => seat offset within its desk
     */
    private function materializePlacements(array $units, array $assignment, array $desks, array $allowedColumns, array $lockedSeatOffsets): array
    {
        $placements = [];
        $offsets = [];

        foreach ($this->deskOccupants($units, $assignment, $desks, $allowedColumns, $lockedSeatOffsets) as $deskId => $members) {
            foreach ($members as $offset => $studentId) {
                $placements[$studentId] = $deskId;
                $offsets[$studentId] = $offset;
            }
        }

        return [$placements, $offsets];
    }
}
