<?php

namespace App\Domain\Enums;

enum VoucherPinFormat: string
{
    case Numbers = 'numbers';
    case Alphabet = 'alphabet';
    case Alphanumeric = 'alphanumeric';

    public function label(): string
    {
        return match ($this) {
            self::Numbers => 'Numbers only', self::Alphabet => 'Letters only', self::Alphanumeric => 'Letters and numbers',
        };
    }

    /**
     * Characters a pin is drawn from. Letters that a customer could misread as a
     * digit are left out, so nobody has to guess between I and 1 or O and 0.
     */
    public function alphabet(): string
    {
        return match ($this) {
            self::Numbers => '0123456789',
            self::Alphabet => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            self::Alphanumeric => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        };
    }
}
