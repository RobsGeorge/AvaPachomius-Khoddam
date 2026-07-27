<?php

namespace App\Observability\Contracts;

/**
 * Optional external (or local) destination for error/incident payloads.
 * First-party DB persist is handled by ObservabilityRecorder; sinks are adapters.
 */
interface ErrorSink
{
    /**
     * @param  array{
     *     severity: string,
     *     category: string,
     *     fingerprint: string,
     *     message: string,
     *     exception_class?: ?string,
     *     stack_excerpt?: ?string,
     *     context?: array<string, mixed>,
     *     request_id?: ?string,
     *     user_id?: ?int,
     *     church_id?: ?int,
     * }  $event
     */
    public function send(array $event): void;
}
