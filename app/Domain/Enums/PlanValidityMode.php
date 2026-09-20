<?php

namespace App\Domain\Enums;

enum PlanValidityMode: string
{
    case Midnight = 'midnight';
    case Rolling = 'rolling';

    public function label(): string
    {
        return match ($this) {
            self::Midnight => 'Midnight (calendar days)',
            self::Rolling => 'Full 24-hour days',
        };
    }
}
