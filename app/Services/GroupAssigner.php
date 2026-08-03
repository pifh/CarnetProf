<?php

namespace App\Services;

/**
 * Splits a pool of students into a fixed number of groups, optionally combining
 * several criteria at once: level (balanced averages or homogeneous bands), sex
 * (balanced mix or single-sex groups), avoiding pairings already seen in past
 * generations, manual pre-placements, and "keep together"/"keep apart" pairs.
 *
 * Pure — no Eloquent/DB access, only plain arrays keyed by student id — so it can
 * be unit-tested without a database.
 */
final class GroupAssigner
{
    private const W_KEEP_APART = 1000.0;

    private const W_KEEP_TOGETHER = 500.0;

    private const W_SIZE_FAIRNESS = 100.0;

    private const W_GENDER_SINGLE = 30.0;

    private const W_GENDER_MIXED = 200.0;

    private const W_LEVEL_BALANCE = 10.0;

    private const W_LEVEL_HOMOGENEOUS = 10.0;

    private const W_REPEAT_PAIR = 5.0;

    public function __construct(
        private readonly int $maxIterations = 2000,
        private readonly int $staleLimit = 300,
    ) {}

    public static function pairKey(int $a, int $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    /**
     * Builds a "how many times were A and B already grouped together" map from
     * archived `GroupGeneration.groups` snapshots.
     *
     * @param  array<int, array<int, int[]>>  $pastGroupsSnapshots  each item is a `groups` blob: array<int, int[]>
     * @return array<string, int> pairKey => count
     */
    public static function pairCountsFromHistory(array $pastGroupsSnapshots): array
    {
        $counts = [];

        foreach ($pastGroupsSnapshots as $groups) {
            foreach ($groups as $studentIds) {
                $ids = array_values(array_map('intval', $studentIds));
                $n = count($ids);

                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $key = self::pairKey($ids[$i], $ids[$j]);
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * @param  int[]  $studentIds  pool to place (already excludes absentees), no duplicates required
     * @param  array<int, float|null>  $averages  studentId => average /20, or null if ungraded
     * @param  array<int, string|null>  $sexes  studentId => 'f'|'m'|'other'|null
     * @param  'none'|'balance'|'homogeneous'  $levelMode
     * @param  'none'|'mixed_balanced'|'single_sex'  $genderMode
     * @param  array<string, int>  $previousPairCounts  pairKey => times paired before (see pairCountsFromHistory())
     * @param  array<int, int>  $lockedPlacements  studentId => target 0-based group index
     * @param  array<int, array{0: int, 1: int}>  $keepTogetherPairs
     * @param  array<int, array{0: int, 1: int}>  $keepApartPairs
     */
    public function assign(
        array $studentIds,
        int $groupCount,
        array $averages = [],
        array $sexes = [],
        string $levelMode = 'none',
        string $genderMode = 'none',
        bool $avoidRepeats = false,
        array $previousPairCounts = [],
        array $lockedPlacements = [],
        array $keepTogetherPairs = [],
        array $keepApartPairs = [],
    ): GroupAssignmentResult {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($studentIds === []) {
            return new GroupAssignmentResult(array_fill(0, max(1, $groupCount), []), [], 0.0);
        }

        $groupCount = max(1, min($groupCount, count($studentIds)));

        $lockedPlacements = $this->sanitizeLocks($lockedPlacements, $studentIds, $groupCount);

        [$units, $conflicts] = $this->buildUnits($studentIds, $averages, $sexes, $lockedPlacements, $keepTogetherPairs, $keepApartPairs);

        if ($genderMode === 'single_sex') {
            foreach ($units as $unit) {
                if ($this->isMixedSex($unit['sexCounts'])) {
                    $conflicts[] = ['type' => 'mixed_cluster_vs_single_sex', 'members' => $unit['members']];
                }
            }
        }

        $capacity = $this->fairCapacities(count($studentIds), $groupCount);
        $assignment = [];
        $freeIndexes = [];

        foreach ($units as $i => $unit) {
            if ($unit['fixedIndex'] !== null) {
                $assignment[$i] = $unit['fixedIndex'];
                $capacity[$unit['fixedIndex']] = max(0, $capacity[$unit['fixedIndex']] - $unit['size']);
            } else {
                $assignment[$i] = null;
                $freeIndexes[] = $i;
            }
        }

        $assignment = $levelMode === 'homogeneous'
            ? $this->placeHomogeneousBands($units, $freeIndexes, $assignment, $capacity, $groupCount)
            : $this->placeGreedyLeastLoaded($units, $freeIndexes, $assignment, $capacity, $groupCount, $genderMode, $levelMode);

        [$assignment, $cost] = $this->refine(
            $units, $freeIndexes, $assignment, $groupCount,
            $levelMode, $genderMode, $avoidRepeats, $previousPairCounts,
            $keepApartPairs, $keepTogetherPairs,
        );

        return new GroupAssignmentResult($this->materializeGroups($units, $assignment, $groupCount), $conflicts, $cost);
    }

    /**
     * @param  int[]  $studentIds
     * @param  array<int, int>  $lockedPlacements
     * @return array<int, int>
     */
    private function sanitizeLocks(array $lockedPlacements, array $studentIds, int $groupCount): array
    {
        $sanitized = [];

        foreach ($lockedPlacements as $studentId => $index) {
            $studentId = (int) $studentId;
            $index = (int) $index;

            if (in_array($studentId, $studentIds, true) && $index >= 0 && $index < $groupCount) {
                $sanitized[$studentId] = $index;
            }
        }

        return $sanitized;
    }

    /**
     * Union-find over `keepTogetherPairs` to form atomic clusters (a lone student is
     * a cluster of size 1). Contradictions (a keep-together pair also marked apart,
     * or locked to different groups) are skipped and reported instead of applied.
     *
     * @param  int[]  $studentIds
     * @param  array<int, float|null>  $averages
     * @param  array<int, string|null>  $sexes
     * @param  array<int, int>  $lockedPlacements
     * @param  array<int, array{0: int, 1: int}>  $keepTogetherPairs
     * @param  array<int, array{0: int, 1: int}>  $keepApartPairs
     * @return array{0: array<int, array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}>, 1: array<int, array<string, mixed>>}
     */
    private function buildUnits(array $studentIds, array $averages, array $sexes, array $lockedPlacements, array $keepTogetherPairs, array $keepApartPairs): array
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

        $apartKeys = [];
        foreach ($keepApartPairs as [$a, $b]) {
            $apartKeys[self::pairKey((int) $a, (int) $b)] = true;
        }

        $conflicts = [];

        foreach ($keepTogetherPairs as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;

            if (! in_array($a, $studentIds, true) || ! in_array($b, $studentIds, true)) {
                continue;
            }

            if (isset($apartKeys[self::pairKey($a, $b)])) {
                $conflicts[] = ['type' => 'keep_together_vs_conflict', 'pair' => [$a, $b]];

                continue;
            }

            $lockA = $lockedPlacements[$a] ?? null;
            $lockB = $lockedPlacements[$b] ?? null;

            if ($lockA !== null && $lockB !== null && $lockA !== $lockB) {
                $conflicts[] = ['type' => 'keep_together_vs_conflict', 'pair' => [$a, $b]];

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
            $distinctLocks = [];
            foreach ($members as $m) {
                if (isset($lockedPlacements[$m])) {
                    $distinctLocks[$lockedPlacements[$m]] = true;
                }
            }
            $distinctLocks = array_keys($distinctLocks);

            if (count($distinctLocks) <= 1) {
                $units[] = $this->makeUnit($members, $averages, $sexes, $distinctLocks[0] ?? null);

                continue;
            }

            // A transitive keep-together chain (A-B, B-C) reached members locked to
            // different groups: split it instead of silently dropping a constraint.
            $conflicts[] = ['type' => 'together_split', 'members' => $members];

            $byLock = [];
            $unlocked = [];
            foreach ($members as $m) {
                $lock = $lockedPlacements[$m] ?? null;
                if ($lock === null) {
                    $unlocked[] = $m;
                } else {
                    $byLock[$lock][] = $m;
                }
            }
            foreach ($byLock as $lock => $subMembers) {
                $units[] = $this->makeUnit($subMembers, $averages, $sexes, $lock);
            }
            if ($unlocked !== []) {
                $units[] = $this->makeUnit($unlocked, $averages, $sexes, null);
            }
        }

        // A keep-apart pair that ended up in the same cluster anyway (a 3+-hop
        // keep-together chain forced them together) can no longer be honored —
        // report it instead of silently ignoring.
        $memberUnit = [];
        foreach ($units as $unitIndex => $unit) {
            foreach ($unit['members'] as $m) {
                $memberUnit[$m] = $unitIndex;
            }
        }
        foreach ($keepApartPairs as [$a, $b]) {
            $a = (int) $a;
            $b = (int) $b;
            if (! isset($memberUnit[$a], $memberUnit[$b])) {
                continue;
            }
            if ($memberUnit[$a] === $memberUnit[$b] && count($units[$memberUnit[$a]]['members']) > 1) {
                $conflicts[] = ['type' => 'keep_apart_unresolvable', 'pair' => [$a, $b]];
            }
        }

        return [$units, $conflicts];
    }

    /**
     * @param  int[]  $members
     * @param  array<int, float|null>  $averages
     * @param  array<int, string|null>  $sexes
     * @return array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}
     */
    private function makeUnit(array $members, array $averages, array $sexes, ?int $fixedIndex): array
    {
        $known = [];
        foreach ($members as $m) {
            if (($averages[$m] ?? null) !== null) {
                $known[] = $averages[$m];
            }
        }

        $sexCounts = ['f' => 0, 'm' => 0, 'other' => 0, 'null' => 0];
        foreach ($members as $m) {
            $sex = $sexes[$m] ?? null;
            $sexCounts[$sex !== null && isset($sexCounts[$sex]) ? $sex : 'null']++;
        }

        return [
            'members' => $members,
            'size' => count($members),
            'avg' => $known === [] ? null : array_sum($known) / count($known),
            'sexCounts' => $sexCounts,
            'fixedIndex' => $fixedIndex,
        ];
    }

    /**
     * @return array<int, int> group index => fair target size (max-min diff of 1, like the previous round-robin behaviour)
     */
    private function fairCapacities(int $total, int $groupCount): array
    {
        $base = intdiv($total, $groupCount);
        $remainder = $total % $groupCount;
        $capacity = [];

        for ($g = 0; $g < $groupCount; $g++) {
            $capacity[$g] = $base + ($g < $remainder ? 1 : 0);
        }

        return $capacity;
    }

    /**
     * Sorts free units by average (descending) and chunks them into contiguous
     * bands — group 0 ends up the strongest band, the last group the weakest.
     *
     * @param  array<int, array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}>  $units
     * @param  int[]  $freeIndexes
     * @param  array<int, ?int>  $assignment
     * @param  array<int, int>  $capacity
     * @return array<int, ?int>
     */
    private function placeHomogeneousBands(array $units, array $freeIndexes, array $assignment, array $capacity, int $groupCount): array
    {
        if ($freeIndexes === []) {
            return $assignment;
        }

        $known = [];
        foreach ($freeIndexes as $i) {
            if ($units[$i]['avg'] !== null) {
                $known[] = $units[$i]['avg'];
            }
        }
        $fallback = $known === [] ? 10.0 : array_sum($known) / count($known);

        usort($freeIndexes, function (int $a, int $b) use ($units, $fallback) {
            $avgA = $units[$a]['avg'] ?? $fallback;
            $avgB = $units[$b]['avg'] ?? $fallback;

            return $avgB <=> $avgA;
        });

        $group = 0;
        foreach ($freeIndexes as $unitIndex) {
            $size = $units[$unitIndex]['size'];

            while ($group < $groupCount - 1 && $capacity[$group] < $size) {
                $group++;
            }

            $assignment[$unitIndex] = $group;
            $capacity[$group] = max(0, $capacity[$group] - $size);
        }

        return $assignment;
    }

    /**
     * Longest-processing-time-first greedy placement: the biggest/most decisive
     * units are placed first, each going to whichever eligible group currently
     * minimizes a warm-start score (load, level, sex). Only a starting point —
     * refine() does the real balancing work across every active criterion.
     *
     * @param  array<int, array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}>  $units
     * @param  int[]  $freeIndexes
     * @param  array<int, ?int>  $assignment
     * @param  array<int, int>  $capacity
     * @return array<int, ?int>
     */
    private function placeGreedyLeastLoaded(array $units, array $freeIndexes, array $assignment, array $capacity, int $groupCount, string $genderMode, string $levelMode): array
    {
        if ($freeIndexes === []) {
            return $assignment;
        }

        $stats = [];
        for ($g = 0; $g < $groupCount; $g++) {
            $stats[$g] = ['size' => 0, 'avgSum' => 0.0, 'avgCount' => 0, 'sexCounts' => ['f' => 0, 'm' => 0, 'other' => 0, 'null' => 0]];
        }
        foreach ($assignment as $unitIndex => $g) {
            if ($g === null) {
                continue;
            }
            $unit = $units[$unitIndex];
            $stats[$g]['size'] += $unit['size'];
            if ($unit['avg'] !== null) {
                $stats[$g]['avgSum'] += $unit['avg'] * $unit['size'];
                $stats[$g]['avgCount'] += $unit['size'];
            }
            foreach ($unit['sexCounts'] as $sex => $n) {
                $stats[$g]['sexCounts'][$sex] += $n;
            }
        }

        usort($freeIndexes, fn (int $a, int $b) => $units[$b]['size'] <=> $units[$a]['size']);

        $classAvg = null;
        $knownAverages = [];
        foreach ($units as $unit) {
            if ($unit['avg'] !== null) {
                $knownAverages[] = $unit['avg'];
            }
        }
        if ($knownAverages !== []) {
            $classAvg = array_sum($knownAverages) / count($knownAverages);
        }

        foreach ($freeIndexes as $unitIndex) {
            $unit = $units[$unitIndex];
            $unitSex = $this->dominantSex($unit['sexCounts']);
            $unitMixed = $this->isMixedSex($unit['sexCounts']);

            $eligible = [];
            for ($g = 0; $g < $groupCount; $g++) {
                if ($capacity[$g] > 0) {
                    $eligible[] = $g;
                }
            }

            if ($genderMode === 'single_sex' && ! $unitMixed && $unitSex !== null) {
                $restricted = array_values(array_filter($eligible, function (int $g) use ($stats, $unitSex) {
                    $groupSex = $this->dominantSex($stats[$g]['sexCounts']);

                    return $groupSex === null || $groupSex === $unitSex;
                }));
                if ($restricted !== []) {
                    $eligible = $restricted;
                }
            }

            if ($eligible === []) {
                $eligible = range(0, $groupCount - 1);
            }

            $bestGroup = $eligible[0];
            $bestScore = null;
            foreach ($eligible as $g) {
                $score = $stats[$g]['size'] * 3.0;

                if ($levelMode === 'balance' && $unit['avg'] !== null) {
                    $groupAvg = $stats[$g]['avgCount'] > 0 ? $stats[$g]['avgSum'] / $stats[$g]['avgCount'] : ($classAvg ?? $unit['avg']);
                    $score += $groupAvg;
                }

                if ($genderMode === 'mixed_balanced') {
                    $score += $stats[$g]['sexCounts'][$unitSex ?? 'null'] * 5.0;
                }

                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestGroup = $g;
                }
            }

            $assignment[$unitIndex] = $bestGroup;
            $capacity[$bestGroup] = max(0, $capacity[$bestGroup] - $unit['size']);
            $stats[$bestGroup]['size'] += $unit['size'];
            if ($unit['avg'] !== null) {
                $stats[$bestGroup]['avgSum'] += $unit['avg'] * $unit['size'];
                $stats[$bestGroup]['avgCount'] += $unit['size'];
            }
            foreach ($unit['sexCounts'] as $sex => $n) {
                $stats[$bestGroup]['sexCounts'][$sex] += $n;
            }
        }

        return $assignment;
    }

    /**
     * @param  array<string, int>  $sexCounts
     */
    private function dominantSex(array $sexCounts): ?string
    {
        $best = null;
        $bestCount = 0;

        foreach (['f', 'm', 'other'] as $sex) {
            if ($sexCounts[$sex] > $bestCount) {
                $bestCount = $sexCounts[$sex];
                $best = $sex;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, int>  $sexCounts
     */
    private function isMixedSex(array $sexCounts): bool
    {
        $present = 0;
        foreach (['f', 'm', 'other'] as $sex) {
            if ($sexCounts[$sex] > 0) {
                $present++;
            }
        }

        return $present > 1;
    }

    /**
     * Bounded hill-climbing: repeatedly swap two free units between their groups,
     * keeping the swap only if it doesn't worsen the weighted cost of every active
     * criterion. This is what makes the criteria combinable — each enabled one adds
     * a term to the same cost function, optimized jointly.
     *
     * @param  array<int, array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}>  $units
     * @param  int[]  $freeIndexes
     * @param  array<int, ?int>  $assignment
     * @param  array<string, int>  $previousPairCounts
     * @param  array<int, array{0: int, 1: int}>  $keepApartPairs
     * @param  array<int, array{0: int, 1: int}>  $keepTogetherPairs
     * @return array{0: array<int, ?int>, 1: float}
     */
    private function refine(
        array $units,
        array $freeIndexes,
        array $assignment,
        int $groupCount,
        string $levelMode,
        string $genderMode,
        bool $avoidRepeats,
        array $previousPairCounts,
        array $keepApartPairs,
        array $keepTogetherPairs,
    ): array {
        $totalSize = 0;
        foreach ($units as $unit) {
            $totalSize += $unit['size'];
        }
        $capacityTarget = $this->fairCapacities($totalSize, $groupCount);

        $cost = $this->cost($units, $assignment, $groupCount, $capacityTarget, $levelMode, $genderMode, $avoidRepeats, $previousPairCounts, $keepApartPairs, $keepTogetherPairs);

        if (count($freeIndexes) < 2) {
            return [$assignment, $cost];
        }

        $stale = 0;
        for ($iteration = 0; $iteration < $this->maxIterations && $stale < $this->staleLimit; $iteration++) {
            $i = $freeIndexes[array_rand($freeIndexes)];
            $j = $freeIndexes[array_rand($freeIndexes)];

            if ($i === $j || $assignment[$i] === $assignment[$j]) {
                $stale++;

                continue;
            }

            $trial = $assignment;
            [$trial[$i], $trial[$j]] = [$trial[$j], $trial[$i]];

            $newCost = $this->cost($units, $trial, $groupCount, $capacityTarget, $levelMode, $genderMode, $avoidRepeats, $previousPairCounts, $keepApartPairs, $keepTogetherPairs);

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
     * @param  array<int, array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @param  array<int, int>  $capacityTarget
     * @param  array<string, int>  $previousPairCounts
     * @param  array<int, array{0: int, 1: int}>  $keepApartPairs
     * @param  array<int, array{0: int, 1: int}>  $keepTogetherPairs
     */
    private function cost(
        array $units,
        array $assignment,
        int $groupCount,
        array $capacityTarget,
        string $levelMode,
        string $genderMode,
        bool $avoidRepeats,
        array $previousPairCounts,
        array $keepApartPairs,
        array $keepTogetherPairs,
    ): float {
        $groupsOfUnits = array_fill(0, $groupCount, []);
        foreach ($assignment as $unitIndex => $g) {
            if ($g !== null) {
                $groupsOfUnits[$g][] = $unitIndex;
            }
        }

        $groupMembers = [];
        $groupSize = array_fill(0, $groupCount, 0);
        $groupAvgSum = array_fill(0, $groupCount, 0.0);
        $groupAvgCount = array_fill(0, $groupCount, 0);
        $groupSexCounts = [];

        for ($g = 0; $g < $groupCount; $g++) {
            $groupSexCounts[$g] = ['f' => 0, 'm' => 0, 'other' => 0, 'null' => 0];
            $members = [];
            foreach ($groupsOfUnits[$g] as $unitIndex) {
                $unit = $units[$unitIndex];
                $members = [...$members, ...$unit['members']];
                $groupSize[$g] += $unit['size'];
                if ($unit['avg'] !== null) {
                    $groupAvgSum[$g] += $unit['avg'] * $unit['size'];
                    $groupAvgCount[$g] += $unit['size'];
                }
                foreach ($unit['sexCounts'] as $sex => $n) {
                    $groupSexCounts[$g][$sex] += $n;
                }
            }
            $groupMembers[$g] = $members;
        }

        $cost = 0.0;

        foreach ($groupSize as $g => $size) {
            $cost += self::W_SIZE_FAIRNESS * (($size - $capacityTarget[$g]) ** 2);
        }

        if ($levelMode === 'balance') {
            $groupAverages = [];
            foreach ($groupAvgCount as $g => $count) {
                if ($count > 0) {
                    $groupAverages[] = $groupAvgSum[$g] / $count;
                }
            }
            if (count($groupAverages) > 1) {
                $cost += self::W_LEVEL_BALANCE * $this->variance($groupAverages);
            }
        } elseif ($levelMode === 'homogeneous') {
            for ($g = 0; $g < $groupCount; $g++) {
                $values = [];
                foreach ($groupsOfUnits[$g] as $unitIndex) {
                    if ($units[$unitIndex]['avg'] !== null) {
                        for ($k = 0; $k < $units[$unitIndex]['size']; $k++) {
                            $values[] = $units[$unitIndex]['avg'];
                        }
                    }
                }
                if (count($values) > 1) {
                    $cost += self::W_LEVEL_HOMOGENEOUS * $this->variance($values);
                }
            }
        }

        if ($genderMode === 'mixed_balanced') {
            $classCounts = ['f' => 0, 'm' => 0, 'other' => 0];
            $classTotal = 0;
            foreach ($groupSexCounts as $counts) {
                foreach (['f', 'm', 'other'] as $sex) {
                    $classCounts[$sex] += $counts[$sex];
                    $classTotal += $counts[$sex];
                }
            }
            if ($classTotal > 0) {
                $classRatios = [];
                foreach (['f', 'm', 'other'] as $sex) {
                    $classRatios[$sex] = $classCounts[$sex] / $classTotal;
                }
                foreach ($groupSexCounts as $g => $counts) {
                    if ($groupSize[$g] === 0) {
                        continue;
                    }
                    foreach (['f', 'm', 'other'] as $sex) {
                        $ratio = $counts[$sex] / $groupSize[$g];
                        $cost += self::W_GENDER_MIXED * (($ratio - $classRatios[$sex]) ** 2);
                    }
                }
            }
        } elseif ($genderMode === 'single_sex') {
            foreach ($groupSexCounts as $counts) {
                $present = array_filter(['f' => $counts['f'], 'm' => $counts['m'], 'other' => $counts['other']], fn ($n) => $n > 0);
                if (count($present) > 1) {
                    $values = array_values($present);
                    $crossPairs = 0;
                    for ($x = 0; $x < count($values); $x++) {
                        for ($y = $x + 1; $y < count($values); $y++) {
                            $crossPairs += $values[$x] * $values[$y];
                        }
                    }
                    $cost += self::W_GENDER_SINGLE * $crossPairs;
                }
            }
        }

        if ($avoidRepeats && $previousPairCounts !== []) {
            for ($g = 0; $g < $groupCount; $g++) {
                $members = $groupMembers[$g];
                $n = count($members);
                for ($x = 0; $x < $n; $x++) {
                    for ($y = $x + 1; $y < $n; $y++) {
                        $key = self::pairKey($members[$x], $members[$y]);
                        if (isset($previousPairCounts[$key])) {
                            $cost += self::W_REPEAT_PAIR * $previousPairCounts[$key];
                        }
                    }
                }
            }
        }

        foreach ($keepApartPairs as [$a, $b]) {
            $groupA = $this->groupOfStudent($groupMembers, (int) $a);
            $groupB = $this->groupOfStudent($groupMembers, (int) $b);
            if ($groupA !== null && $groupA === $groupB) {
                $cost += self::W_KEEP_APART;
            }
        }

        foreach ($keepTogetherPairs as [$a, $b]) {
            $groupA = $this->groupOfStudent($groupMembers, (int) $a);
            $groupB = $this->groupOfStudent($groupMembers, (int) $b);
            if ($groupA !== null && $groupB !== null && $groupA !== $groupB) {
                $cost += self::W_KEEP_TOGETHER;
            }
        }

        return $cost;
    }

    /**
     * @param  float[]  $values
     */
    private function variance(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sumSquares = 0.0;
        foreach ($values as $v) {
            $sumSquares += ($v - $mean) ** 2;
        }

        return $sumSquares / $n;
    }

    /**
     * @param  array<int, int[]>  $groupMembers
     */
    private function groupOfStudent(array $groupMembers, int $studentId): ?int
    {
        foreach ($groupMembers as $g => $members) {
            if (in_array($studentId, $members, true)) {
                return $g;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{members: int[], size: int, avg: ?float, sexCounts: array<string, int>, fixedIndex: ?int}>  $units
     * @param  array<int, ?int>  $assignment
     * @return array<int, int[]>
     */
    private function materializeGroups(array $units, array $assignment, int $groupCount): array
    {
        $groups = array_fill(0, $groupCount, []);

        foreach ($assignment as $unitIndex => $g) {
            if ($g === null) {
                continue;
            }
            $groups[$g] = [...$groups[$g], ...$units[$unitIndex]['members']];
        }

        return $groups;
    }
}
