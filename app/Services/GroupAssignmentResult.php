<?php

namespace App\Services;

final class GroupAssignmentResult
{
    /**
     * @param  array<int, int[]>  $groups  0-based group index => student ids
     * @param  array<int, array{type: string, pair?: array{0: int, 1: int}, members?: int[]}>  $unresolvedConflicts
     */
    public function __construct(
        public readonly array $groups,
        public readonly array $unresolvedConflicts,
        public readonly float $cost,
    ) {}
}
