<?php

namespace App\Console\Commands;

use App\Models\Price;
use App\Models\VendorSkillLanguagePair;
use Illuminate\Console\Command;

class CopyVendorSkillLanguagePairsFromPrices extends Command
{
    protected $signature = 'vendor-skill-language-pairs:copy-from-prices';

    protected $description = 'Idempotent copy of (vendor, src_lang, dst_lang, skill) tuples from prices to vendor_skill_language_pairs';

    public function handle(): int
    {
        $count = 0;

        Price::query()
            ->whereNull('deleted_at')
            ->select(['vendor_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id', 'skill_id'])
            ->chunk(500, function ($chunk) use (&$count) {
                foreach ($chunk as $price) {
                    VendorSkillLanguagePair::query()->updateOrCreate(
                        [
                            'vendor_id' => $price->vendor_id,
                            'src_lang_classifier_value_id' => $price->src_lang_classifier_value_id,
                            'dst_lang_classifier_value_id' => $price->dst_lang_classifier_value_id,
                            'skill_id' => $price->skill_id,
                        ],
                        [
                            'updated_at' => now(),
                        ]
                    );
                }

                $count += $chunk->count();
            });

        $this->info("Processed $count price rows.");

        return self::SUCCESS;
    }
}
