<?php

namespace App\Contracts;

interface HrisReferenceSource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function references(): array;
}
