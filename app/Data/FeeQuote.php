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
        return $this->reason === 'internal_subscription'
            ? 0
            : $this->percentageFeeKobo;
    }
}
