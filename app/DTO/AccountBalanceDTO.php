<?php

namespace App\DTO;

class AccountBalanceDTO
{
    public function __construct(
        public ?string $startingBalance,
        public ?string $debitBalance,
        public ?string $creditBalance,
        public ?string $netMovement,
        public ?string $endingBalance,
    ) {}
}
