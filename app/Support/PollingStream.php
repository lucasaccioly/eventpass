<?php

namespace App\Support;

use Closure;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-sent events by polling: re-read a snapshot every poll interval and
 * send it only when it changed. Each connection lives at most
 * `eventpass.stream_seconds`, so a PHP worker is never held for long; the
 * browser's EventSource reconnects on its own when the window closes.
 */
final class PollingStream
{
    /**
     * @param  Closure(): array<string, mixed>  $snapshot
     * @param  (Closure(array<string, mixed>): bool)|null  $done  stop early once this returns true
     */
    public static function response(string $event, Closure $snapshot, ?Closure $done = null): StreamedResponse
    {
        return response()->eventStream(function () use ($event, $snapshot, $done) {
            // PHP's default 30 s max_execution_time would kill a full window.
            set_time_limit((int) config('eventpass.stream_seconds') + 15);
            $until = microtime(true) + (int) config('eventpass.stream_seconds');
            $pollMicros = (int) config('eventpass.stream_poll_ms') * 1000;
            $heartbeat = (int) config('eventpass.heartbeat_seconds');
            $lastPing = microtime(true);
            $lastSent = null;

            while (true) {
                $data = $snapshot();
                $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                if ($json !== $lastSent) {
                    yield new StreamedEvent($event, $json);
                    $lastSent = $json;
                }

                if (($done !== null && $done($data)) || microtime(true) >= $until || connection_aborted()) {
                    return;
                }

                if (microtime(true) - $lastPing >= $heartbeat) {
                    // Keeps proxies from closing an idle connection.
                    yield new StreamedEvent('ping', '1');
                    $lastPing = microtime(true);
                }

                usleep($pollMicros);
            }
        }, endStreamWith: null);
    }
}
