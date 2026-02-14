<?php
// src/Dto/DuplicateWeeksCommand.php

namespace App\Dto;

final class DuplicateWeeksCommand
{
    public int $sourceWeek;

    /** @var int[] */
    public array $targetWeeks;

    public string $mode;
    public bool $workingDaysOnly;

    /**
     * @param int[] $targetWeeks
     */
    public function __construct(
        int $sourceWeek,
        array $targetWeeks,
        string $mode = 'skip_existing',
        bool $workingDaysOnly = false
    ) {
        $this->sourceWeek = $sourceWeek;

        $targetWeeks = array_values(array_unique(array_map('intval', $targetWeeks)));
        $this->targetWeeks = $targetWeeks;

        $this->mode = $mode;
        $this->workingDaysOnly = $workingDaysOnly;
    }
}
