<?php

namespace App\Contracts;

interface RequestEventTransport
{
    /**
     * @param  list<string>  $channels
     * @param  array{
     *     event_id: string,
     *     event_name: string,
     *     schema_version: int,
     *     request_id: int,
     *     aggregate_sequence: int,
     *     occurred_at: string,
     *     payload: array<string, mixed>
     * }  $event
     */
    public function publish(array $channels, array $event): void;
}
