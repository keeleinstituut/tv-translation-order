<?php

namespace App\Services\CatTools;

use App\Models\CatToolJob;
use App\Models\SubProject;
use App\Services\CatTools\Contracts\DownloadableFile;

readonly class CatToolAnalysisReport
{
    public function __construct(private SubProject $subProject)
    {
    }

    public function getReport(): DownloadableFile
    {
        $contents = [];
        foreach ($this->subProject->catToolJobs as $job) {
            $contents[] = $this->getReportSectionContent($job);
        }

        $contents = array_values(
            array_filter($contents)
        );

        return new VolumeAnalysisReportFile(
            implode($this->getSectionSeparator(), $contents),
            "{$this->subProject->ext_id}.txt"
        );
    }

    private function getReportSectionContent(CatToolJob $job): string
    {
        $sectionContent = '';
        $volumeAnalysis = $job->getVolumeAnalysis();
        if (empty($volumeAnalysis)) {
            return $sectionContent;
        }

        if (! empty($volumeAnalysis->files_names)) {
            $sectionContent .= implode('', [
                str_pad('Files: ', 23),
                implode(', ', $volumeAnalysis->files_names),
                PHP_EOL,
            ]);
        }

        $sectionContent .= implode('', [
            str_pad('Job: ', 23),
            $job->name,
            PHP_EOL,
        ]);

        $sectionContent .= implode('', [
            str_pad('Language direction: ', 23),
            implode(' > ', [
                $this->subProject->sourceLanguageClassifierValue->value,
                $this->subProject->destinationLanguageClassifierValue->value,
            ]),
            PHP_EOL,
        ]);

        $sectionContent .= $this->composeMatchTable($volumeAnalysis);

        return $sectionContent;
    }

    private function composeMatchTable(CatAnalysisResult $volumeAnalysis): string
    {
        $tableContent = implode('', [
            PHP_EOL,
            str_pad('Match Types', 16),
            str_pad('Words', 12, pad_type: STR_PAD_LEFT),
            str_pad('Percent', 14, pad_type: STR_PAD_LEFT),
            PHP_EOL,
        ]);

        $tableContent .= $this->composeMatchTableLine(
            '101%',
            $volumeAnalysis->tm_101,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            'Repetitions',
            $volumeAnalysis->repetitions,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '100%',
            $volumeAnalysis->tm_100,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '95-99%',
            $volumeAnalysis->tm_95_99,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '85-94%',
            $volumeAnalysis->tm_85_94,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '75-84%',
            $volumeAnalysis->tm_75_84,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '50-74%',
            $volumeAnalysis->tm_50_74,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '50-74%',
            $volumeAnalysis->tm_50_74,
            $volumeAnalysis->total
        );

        $tableContent .= $this->composeMatchTableLine(
            '0-49%',
            $volumeAnalysis->tm_0_49,
            $volumeAnalysis->total
        );

        $tableContent .= PHP_EOL;

        $tableContent .= $this->composeMatchTableLine(
            'Total',
            $volumeAnalysis->total,
            $volumeAnalysis->total
        );

        return $tableContent;
    }

    private function composeMatchTableLine(string $matchTypeLabel, int $wordsCount, int $total): string
    {
        return implode('', [
            str_pad($matchTypeLabel, 16),
            str_pad($wordsCount, 12, pad_type: STR_PAD_LEFT),
            str_pad(number_format($wordsCount / $total * 100, 2, '.', ''), 14, pad_type: STR_PAD_LEFT),
            PHP_EOL,
        ]);
    }

    private function getSectionSeparator(): string
    {
        return implode('', [
            PHP_EOL,
            PHP_EOL,
            '--------------------------------------------------------------------------------',
            PHP_EOL,
            PHP_EOL,
        ]);
    }
}
