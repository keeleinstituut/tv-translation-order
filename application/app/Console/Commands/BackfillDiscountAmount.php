<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\SubProject;
use Illuminate\Console\Command;
use Throwable;

class BackfillDiscountAmount extends Command
{
    protected $signature = 'app:backfill-discount-amount';

    protected $description = 'Backfill the cached discount_amount column on assignments and sub-projects';

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->info('Backfilling assignment discount amounts...');
        Assignment::query()->chunkById(200, function ($assignments) {
            /** @var Assignment $assignment */
            foreach ($assignments as $assignment) {
                $assignment->discount_amount = $assignment->getPriceCalculator()->getDiscountAmount();
                $assignment->saveQuietly();
            }
        });

        $this->info('Backfilling sub-project discount amounts...');
        SubProject::query()->chunkById(200, function ($subProjects) {
            /** @var SubProject $subProject */
            foreach ($subProjects as $subProject) {
                $subProject->discount_amount = $subProject->getPriceCalculator()->getDiscountAmount();
                $subProject->saveQuietly();
            }
        });

        $this->info('Done.');
    }
}
