<?php

namespace App\Services\Prices;

use App\Models\Project;

readonly class ProjectPriceCalculator implements PriceCalculator
{

    public function __construct(private Project $project)
    {
    }

    public function getPrice(): ?float
    {
        $prices = $this->project->subProjects()->pluck('price');

        if ($prices->search(null) === false) {
            return $prices->sum();
        }

        return null;
    }
}
