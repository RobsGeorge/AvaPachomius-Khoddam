<?php

namespace App\Services\People;

use App\Models\Person;
use Illuminate\Support\Collection;
use RuntimeException;

class PersonDuplicateNeedsConfirmationException extends RuntimeException
{
    /** @param  Collection<int, Person>  $matches */
    public function __construct(
        public readonly Collection $matches
    ) {
        parent::__construct('Possible person duplicates require confirmation before insert.');
    }
}
