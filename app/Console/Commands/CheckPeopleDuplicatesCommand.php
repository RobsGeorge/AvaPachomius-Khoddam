<?php

namespace App\Console\Commands;

use App\Services\People\PersonDuplicateDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Import / create preview: flag هالة/هاله-style collisions before inserting.
 * Does not write people — confirmation step for ops.
 */
class CheckPeopleDuplicatesCommand extends Command
{
    protected $signature = 'people:check-duplicates
                            {file : CSV path (headers: first_name,second_name,third_name,date_of_birth,mobile_number)}
                            {--against-registry : Also compare each row to existing people}';

    protected $description = 'Flag possible duplicates in an import CSV (normalized Arabic names)';

    public function handle(PersonDuplicateDetector $detector): int
    {
        $path = (string) $this->argument('file');
        if (! File::isFile($path)) {
            $path = base_path($path);
        }
        if (! File::isFile($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        if ($rows === []) {
            $this->warn('No data rows found.');

            return self::SUCCESS;
        }

        $intra = $detector->findIntraBatchCollisions($rows);
        $registryHits = [];

        if ($this->option('against-registry')) {
            foreach ($rows as $index => $row) {
                $matches = $detector->findPossibleMatches($row);
                if ($matches->isNotEmpty()) {
                    $registryHits[] = [
                        'row' => $index + 2, // header + 1-based
                        'normalized' => $detector->normalizedFromAttributes($row),
                        'match_ids' => $matches->pluck('person_id')->implode(','),
                    ];
                }
            }
        }

        if ($intra === [] && $registryHits === []) {
            $this->info('No possible duplicates flagged.');

            return self::SUCCESS;
        }

        if ($intra !== []) {
            $this->warn('Intra-file collisions (same normalized_name):');
            foreach ($intra as $collision) {
                $this->line(sprintf(
                    '  %s ← rows %s (%s)',
                    $collision['normalized_name'],
                    implode(',', array_map(fn ($i) => $i + 2, $collision['row_indexes'])),
                    implode(' / ', $collision['sample_names'])
                ));
            }
        }

        if ($registryHits !== []) {
            $this->warn('Matches against existing people registry:');
            foreach ($registryHits as $hit) {
                $this->line("  CSV row {$hit['row']}: {$hit['normalized']} → person_ids [{$hit['match_ids']}]");
            }
        }

        return self::SUCCESS;
    }

    /** @return list<array<string, string|null>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string) $data[$i]) : null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
