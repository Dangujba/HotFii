<?php

namespace App\Data;

final readonly class FeeQuote
{
    public function __construct(
        public int $billableSalesKobo,
        public int $percentageFeeKobo,
        public int $minimumFeeKobo,
        public int $totalFeeKobo,
        public string $reason,
    ) {}

    public function chargeablePercentageFeeKobo(): int
    {
        return in_array($this->reason, ['trial_or_sandbox', 'internal_subscription'], true)
            ? 0
            : $this->percentageFeeKobo;
    }
}