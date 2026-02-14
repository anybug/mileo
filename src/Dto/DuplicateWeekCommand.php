<?php

namespace App\Dto;

final class DuplicateWeekCommand
{
    public int $sourceWeek;
    public int $targetWeek;
    public string $mode;
    public bool $workingDaysOnly;

    public function __construct(
        int $sourceWeek,
        int $targetWeek,
        string $mode = 'skip_existing',
        bool $workingDaysOnly = false
    ) {
        $this->sourceWeek = $sourceWeek;
        $this->targetWeek = $targetWeek;
        $this->mode = $mode;
        $this->workingDaysOnly = $workingDaysOnly;
    }
}
