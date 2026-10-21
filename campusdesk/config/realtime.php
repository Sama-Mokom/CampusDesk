<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Realtime Delivery Feature Flag
    |--------------------------------------------------------------------------
    |
    | When disabled, the delivery service will not broadcast events to
    | transports, leaving outbox events in a pending state until enabled.
    |
    */
    'delivery_enabled' => (bool) env('REALTIME_DELIVERY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Replay Historical Backlog on Activation
    |--------------------------------------------------------------------------
    |
    | Whether the reconciler is allowed to replay pending events that were
    | recorded prior to the cutover window when delivery is enabled.
    |
    */
    'replay_historical_backlog' => (bool) env('REALTIME_REPLAY_HISTORICAL_BACKLOG', true),
];
