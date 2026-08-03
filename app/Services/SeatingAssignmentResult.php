<?php

namespace App\Services;

final class SeatingAssignmentResult
{
    /**
     * @param  array<int, int>  $placements  studentId => deskId
     * @param  array<int, array{type: string, pair?: array{0: int, 1: int}, members?: int[]}>  $unresolvedConflicts
     */
    public function __construct(
        public readonly array $placements,
        public readonly array $unresolvedConflicts,
        public readonly float $cost,
    ) {}
}
