<?php

use App\Services\GroupAssigner;

/**
 * @return array<int, int[]> studentId => group id, per member
 */
function groupSizes(array $groups): array
{
    return array_map('count', $groups);
}

function allPlacedIds(array $groups): array
{
    $ids = [];
    foreach ($groups as $members) {
        array_push($ids, ...$members);
    }
    sort($ids);

    return $ids;
}

function meanOf(array $values): ?float
{
    $values = array_values(array_filter($values, fn ($v) => $v !== null));

    return $values === [] ? null : array_sum($values) / count($values);
}

function stdDevOfGroupMeans(array $groups, array $averages): float
{
    $means = [];
    foreach ($groups as $members) {
        $mean = meanOf(array_map(fn ($id) => $averages[$id] ?? null, $members));
        if ($mean !== null) {
            $means[] = $mean;
        }
    }

    if (count($means) < 2) {
        return 0.0;
    }

    $mean = array_sum($means) / count($means);
    $sumSquares = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $means));

    return sqrt($sumSquares / count($means));
}

/**
 * Counts how many pairs are shared between two group snapshots.
 */
function sharedPairCount(array $groupsA, array $groupsB): int
{
    $pairsIn = function (array $groups): array {
        $pairs = [];
        foreach ($groups as $members) {
            $members = array_values($members);
            $n = count($members);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pairs[GroupAssigner::pairKey($members[$i], $members[$j])] = true;
                }
            }
        }

        return $pairs;
    };

    return count(array_intersect_key($pairsIn($groupsA), $pairsIn($groupsB)));
}

it('preserves student count and near-equal sizes with no criteria active', function () {
    mt_srand(1);
    $ids = range(1, 23);

    $result = (new GroupAssigner)->assign($ids, 5);

    expect(count($result->groups))->toBe(5)
        ->and(allPlacedIds($result->groups))->toBe($ids);

    $sizes = groupSizes($result->groups);
    expect(max($sizes) - min($sizes))->toBeLessThanOrEqual(1);
});

it('splits into homogeneous level bands', function () {
    mt_srand(2);
    $ids = range(1, 30);
    $averages = array_combine($ids, $ids);

    $result = (new GroupAssigner)->assign($ids, 3, averages: $averages, levelMode: 'homogeneous');

    expect(allPlacedIds($result->groups))->toBe($ids)
        ->and(groupSizes($result->groups))->toBe([10, 10, 10]);

    $means = array_map(fn ($members) => meanOf(array_map(fn ($id) => $averages[$id], $members)), $result->groups);

    expect($means[0])->toBeGreaterThan($means[1])
        ->and($means[1])->toBeGreaterThan($means[2]);
});

it('balances group averages when levelMode is balance', function () {
    $ids = range(1, 30);
    $averages = array_combine($ids, $ids);

    mt_srand(3);
    $homogeneous = (new GroupAssigner)->assign($ids, 3, averages: $averages, levelMode: 'homogeneous');

    mt_srand(3);
    $balanced = (new GroupAssigner)->assign($ids, 3, averages: $averages, levelMode: 'balance');

    expect(allPlacedIds($balanced->groups))->toBe($ids)
        ->and(stdDevOfGroupMeans($balanced->groups, $averages))
        ->toBeLessThan(stdDevOfGroupMeans($homogeneous->groups, $averages));
});

it('produces single-sex groups when genderMode is single_sex', function () {
    mt_srand(4);
    $ids = range(1, 24);
    $sexes = [];
    foreach ($ids as $id) {
        $sexes[$id] = $id <= 12 ? 'f' : 'm';
    }

    $result = (new GroupAssigner)->assign($ids, 4, sexes: $sexes, genderMode: 'single_sex');

    expect(allPlacedIds($result->groups))->toBe($ids);

    foreach ($result->groups as $members) {
        $distinctSexes = array_unique(array_map(fn ($id) => $sexes[$id], $members));
        expect($distinctSexes)->toHaveCount(1);
    }
});

it('keeps gender ratio roughly proportional when genderMode is mixed_balanced', function () {
    mt_srand(5);
    $ids = range(1, 30);
    $sexes = [];
    foreach ($ids as $id) {
        $sexes[$id] = $id <= 18 ? 'f' : 'm';
    }

    $result = (new GroupAssigner)->assign($ids, 5, sexes: $sexes, genderMode: 'mixed_balanced');

    expect(allPlacedIds($result->groups))->toBe($ids);

    foreach ($result->groups as $members) {
        $fRatio = count(array_filter($members, fn ($id) => $sexes[$id] === 'f')) / count($members);
        expect(abs($fRatio - 0.6))->toBeLessThanOrEqual(0.3);
    }
});

it('reduces repeat pairings across two generations when avoidRepeats is true', function () {
    $ids = range(1, 24);

    mt_srand(6);
    $first = (new GroupAssigner)->assign($ids, 4);
    $previousPairCounts = GroupAssigner::pairCountsFromHistory([$first->groups]);

    mt_srand(7);
    $avoided = (new GroupAssigner)->assign($ids, 4, avoidRepeats: true, previousPairCounts: $previousPairCounts);

    mt_srand(7);
    $baseline = (new GroupAssigner)->assign($ids, 4);

    $avoidedOverlap = sharedPairCount($avoided->groups, $first->groups);
    $baselineOverlap = sharedPairCount($baseline->groups, $first->groups);

    expect($avoidedOverlap)->toBeLessThan($baselineOverlap);
});

it('keeps locked students in their chosen group', function () {
    mt_srand(8);
    $ids = range(1, 15);
    $averages = array_combine($ids, array_fill(0, 15, null));
    $sexes = array_combine($ids, array_fill(0, 15, null));

    $result = (new GroupAssigner)->assign(
        $ids, 4,
        averages: $averages, sexes: $sexes,
        levelMode: 'balance', genderMode: 'mixed_balanced',
        lockedPlacements: [1 => 0, 2 => 2, 3 => 3],
    );

    expect($result->groups[0])->toContain(1)
        ->and($result->groups[2])->toContain(2)
        ->and($result->groups[3])->toContain(3);
});

it('honors keep-together pairs', function () {
    mt_srand(9);
    $ids = range(1, 20);

    $result = (new GroupAssigner)->assign(
        $ids, 4,
        levelMode: 'none', genderMode: 'none',
        keepTogetherPairs: [[1, 2], [5, 6]],
    );

    $groupOf = function (int $id, array $groups) {
        foreach ($groups as $index => $members) {
            if (in_array($id, $members, true)) {
                return $index;
            }
        }

        return null;
    };

    expect($groupOf(1, $result->groups))->toBe($groupOf(2, $result->groups))
        ->and($groupOf(5, $result->groups))->toBe($groupOf(6, $result->groups));
});

it('honors keep-apart pairs', function () {
    mt_srand(10);
    $ids = range(1, 16);

    $result = (new GroupAssigner)->assign(
        $ids, 4,
        keepApartPairs: [[1, 2], [3, 4], [5, 6]],
    );

    $groupOf = function (int $id, array $groups) {
        foreach ($groups as $index => $members) {
            if (in_array($id, $members, true)) {
                return $index;
            }
        }

        return null;
    };

    expect($groupOf(1, $result->groups))->not->toBe($groupOf(2, $result->groups))
        ->and($groupOf(3, $result->groups))->not->toBe($groupOf(4, $result->groups))
        ->and($groupOf(5, $result->groups))->not->toBe($groupOf(6, $result->groups))
        ->and($result->unresolvedConflicts)->toBe([]);
});

it('combines every criterion at once without crashing and still respects hard constraints', function () {
    mt_srand(11);
    $ids = range(1, 30);
    $averages = [];
    $sexes = [];
    foreach ($ids as $id) {
        $averages[$id] = $id % 3 === 0 ? null : (float) ($id % 20);
        $sexes[$id] = $id <= 15 ? 'f' : 'm';
    }

    $previousPairCounts = GroupAssigner::pairCountsFromHistory([
        [[1, 2, 3], [4, 5, 6]],
    ]);

    $result = (new GroupAssigner)->assign(
        $ids, 5,
        averages: $averages, sexes: $sexes,
        levelMode: 'homogeneous', genderMode: 'single_sex',
        avoidRepeats: true, previousPairCounts: $previousPairCounts,
        lockedPlacements: [10 => 0, 20 => 1],
        keepTogetherPairs: [[7, 8]],
        keepApartPairs: [[11, 12]],
    );

    expect(allPlacedIds($result->groups))->toBe($ids);

    $groupOf = function (int $id, array $groups) {
        foreach ($groups as $index => $members) {
            if (in_array($id, $members, true)) {
                return $index;
            }
        }

        return null;
    };

    expect($groupOf(10, $result->groups))->toBe(0)
        ->and($groupOf(20, $result->groups))->toBe(1)
        ->and($groupOf(7, $result->groups))->toBe($groupOf(8, $result->groups))
        ->and($groupOf(11, $result->groups))->not->toBe($groupOf(12, $result->groups));
});

it('reports a conflict instead of crashing on contradictory keep-together and keep-apart pairs', function () {
    mt_srand(12);
    $ids = range(1, 10);

    $result = (new GroupAssigner)->assign(
        $ids, 3,
        keepTogetherPairs: [[1, 2]],
        keepApartPairs: [[1, 2]],
    );

    $groupOf = function (int $id, array $groups) {
        foreach ($groups as $index => $members) {
            if (in_array($id, $members, true)) {
                return $index;
            }
        }

        return null;
    };

    $conflictTypes = array_column($result->unresolvedConflicts, 'type');

    expect($conflictTypes)->toContain('keep_together_vs_conflict')
        ->and($groupOf(1, $result->groups))->not->toBe($groupOf(2, $result->groups));
});

it('aggregates co-occurrences across multiple archived snapshots', function () {
    $counts = GroupAssigner::pairCountsFromHistory([
        [[1, 2, 3], [4, 5]],
        [[1, 2], [3, 4, 5]],
        [[1, 3], [2, 4], [5]],
    ]);

    expect($counts[GroupAssigner::pairKey(1, 2)])->toBe(2)
        ->and($counts[GroupAssigner::pairKey(1, 3)])->toBe(2)
        ->and($counts[GroupAssigner::pairKey(2, 3)])->toBe(1)
        ->and($counts[GroupAssigner::pairKey(4, 5)])->toBe(2)
        ->and($counts[GroupAssigner::pairKey(3, 4)])->toBe(1)
        ->and($counts)->not->toHaveKey(GroupAssigner::pairKey(1, 5));
});
