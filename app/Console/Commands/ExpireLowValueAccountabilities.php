<?php

namespace App\Console\Commands;

use App\Services\SemiExpendablePropertyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:expire-low-value-accountabilities {--as-of= : Effective date (YYYY-MM-DD)}')]
#[Description('Extinguish due low-valued semi-expendable accountabilities without recording disposal')]
class ExpireLowValueAccountabilities extends Command
{
    public function __construct(private SemiExpendablePropertyService $properties)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = $this->properties->expireDueLowValueAccountabilities($this->option('as-of'));
        $this->info("Expired {$count} low-valued accountabilities.");

        return self::SUCCESS;
    }
}
