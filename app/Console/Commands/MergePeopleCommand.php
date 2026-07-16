<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\People\PersonMergeService;
use Illuminate\Console\Command;

class MergePeopleCommand extends Command
{
    protected $signature = 'people:merge
                            {survivor : Surviving person_id}
                            {duplicate : Duplicate person_id to soft-retire}';

    protected $description = 'Merge duplicate person into survivor (re-point FKs + audit)';

    public function handle(PersonMergeService $merger): int
    {
        $survivor = Person::withoutTenancy()->find($this->argument('survivor'));
        $duplicate = Person::withoutTenancy()->find($this->argument('duplicate'));

        if (! $survivor || ! $duplicate) {
            $this->error('Survivor or duplicate person not found.');

            return self::FAILURE;
        }

        try {
            $summary = $merger->merge($survivor, $duplicate, auth()->user());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Merged person #{$summary['duplicate_id']} → #{$summary['survivor_id']}.");
        $this->table(
            ['Metric', 'Count'],
            collect($summary)
                ->except(['survivor_id', 'duplicate_id', 'actor_user_id'])
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        return self::SUCCESS;
    }
}
