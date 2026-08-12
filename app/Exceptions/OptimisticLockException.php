<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OptimisticLockException extends Exception
{
    public function __construct(
        string $message = '',
        public readonly ?int $currentLockVersion = null,
    ) {
        parent::__construct($message !== '' ? $message : 'Optimistic lock conflict');
    }

    public function render(Request $request): JsonResponse|Response
    {
        $payload = [
            'message' => __('structure.optimistic_lock_conflict'),
            'lock_version' => $this->currentLockVersion,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 409);
        }

        return response(__('structure.optimistic_lock_conflict'), 409);
    }
}
