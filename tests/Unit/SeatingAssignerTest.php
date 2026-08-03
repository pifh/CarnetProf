<?php

use App\Services\SeatingAssigner;

/**
 * @return array<int, array{row: int, col: int, capacity: int}>
 */
function grid(int $rows, int $cols, int $capacity = 2): array
{
    $desks = [];
    $id = 1;

    for ($row = 0; $row < $rows; $row++) {
        for ($col = 0; $col < $cols; $col++) {
            $desks[$id] = ['row' => $row, 'col' => $col, 'capacity' => $capacity];
            $id++;
        }
    }

    return $desks;
}

it('places every student exactly once with no criteria active', function () {
    mt_srand(1);
    $ids = range(1, 12);
    $desks = grid(3, 2); // 6 desks * 2 seats = 12 seats

    $result = (new SeatingAssigner)->assign($ids, $desks);

    expect($result->placements)->toHaveCount(12)
        ->and(array_keys($result->placements))->toEqualCanonicalizing($ids)
        ->and($result->unresolvedConflicts)->toBe([]);
});

it('seats a next-to pair at the same desk', function () {
    $ids = range(1, 8);
    $desks = grid(2, 2); // 4 desks * 2 seats = 8 seats

    $result = (new SeatingAssigner)->assign($ids, $desks, nextToPairs: [[1, 2]]);

    expect($result->placements[1])->toBe($result->placements[2]);
});

it('reports a conflict and does not fit a next-to cluster larger than any desk capacity', function () {
    $ids = range(1, 6);
    $desks = grid(3, 1, capacity: 2); // max capacity 2

    $result = (new SeatingAssigner)->assign($ids, $desks, nextToPairs: [[1, 2], [2, 3]]);

    expect($result->unresolvedConflicts)->toContain(['type' => 'next_to_too_large', 'members' => [1, 2, 3]]);
});

it('keeps a not-next-to pair off the same desk', function () {
    mt_srand(2);
    $ids = range(1, 4);
    $desks = grid(2, 1); // 2 desks * 2 seats = 4 seats

    $result = (new SeatingAssigner)->assign($ids, $desks, notNextToPairs: [[1, 2]]);

    expect($result->placements[1])->not->toBe($result->placements[2]);
});

it('reports a conflict when next-to and not-next-to contradict for the same pair', function () {
    $ids = [1, 2, 3, 4];
    $desks = grid(2, 1);

    $result = (new SeatingAssigner)->assign($ids, $desks, nextToPairs: [[1, 2]], notNextToPairs: [[1, 2]]);

    expect($result->unresolvedConflicts)->toContain(['type' => 'next_to_vs_conflict', 'pair' => [1, 2]]);
});

it('maximizes the distance between a far-from pair', function () {
    mt_srand(3);
    $ids = range(1, 4);
    $desks = grid(2, 2); // 4 desks in a 2x2 grid, max manhattan distance is 2 (opposite corners)

    $result = (new SeatingAssigner)->assign($ids, $desks, farFromPairs: [[1, 2]]);

    $deskA = $desks[$result->placements[1]];
    $deskB = $desks[$result->placements[2]];
    $distance = abs($deskA['row'] - $deskB['row']) + abs($deskA['col'] - $deskB['col']);

    expect($distance)->toBe(2);
});

it('honors allowed rows for a student when possible', function () {
    mt_srand(4);
    $ids = range(1, 8);
    $desks = grid(4, 2);

    $result = (new SeatingAssigner)->assign($ids, $desks, allowedRows: [1 => [0]]);

    expect($desks[$result->placements[1]]['row'])->toBe(0);
});

it('honors a restrictive allowed-rows list for a student when possible', function () {
    mt_srand(5);
    $ids = range(1, 8);
    $desks = grid(4, 2);

    $result = (new SeatingAssigner)->assign($ids, $desks, allowedRows: [1 => [3]]);

    expect($desks[$result->placements[1]]['row'])->toBe(3);
});

it('honors allowed columns for a student when possible', function () {
    mt_srand(6);
    $ids = range(1, 8);
    $desks = grid(2, 4);

    $result = (new SeatingAssigner)->assign($ids, $desks, allowedColumns: [1 => [3]]);

    expect($desks[$result->placements[1]]['col'])->toBe(3);
});

it('reports unassigned students when there are more students than seats', function () {
    $ids = range(1, 5);
    $desks = grid(1, 2); // 2 desks * 2 seats = 4 seats for 5 students

    $result = (new SeatingAssigner)->assign($ids, $desks);

    expect($result->placements)->toHaveCount(4);

    $unassigned = collect($result->unresolvedConflicts)->firstWhere('type', 'insufficient_desks');
    expect($unassigned)->not->toBeNull()
        ->and($unassigned['members'])->toHaveCount(1);
});

it('returns nothing for an empty student pool or an empty desk list', function () {
    $desks = grid(2, 2);

    expect((new SeatingAssigner)->assign([], $desks)->placements)->toBe([])
        ->and((new SeatingAssigner)->assign([1, 2], [])->placements)->toBe([]);
});
