<?php

namespace App\Console\Commands;

use App\Actions\Integrations\SyncHrisReferences;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ims:sync-hris')]
#[Description('Synchronize employee references from the main HRIS API')]
class SyncHrisReferencesCommand extends Command
{
    public function handle(SyncHrisReferences $sync): int
    {
        $result = $sync->handle();

        $this->info(sprintf(
            'Employee references synchronized: %d created, %d updated, %d deactivated.',
            $result['created'],
            $result['updated'],
            $result['deactivated'],
        ));

        return self::SUCCESS;
    }
}
