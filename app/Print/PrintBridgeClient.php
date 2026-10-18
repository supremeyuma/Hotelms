<?php

namespace App\Print;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the local print bridge daemon over loopback HTTP.
 *
 * The bridge is deliberately unreachable from the LAN: it listens on
 * 127.0.0.1 and writes to a physical device on this host. Hardware retry
 * (a transient USB failure) happens inside the daemon, so this client only
 * ever sees a terminal answer: accepted or failed.
 */
class PrintBridgeClient
{
    /**
     * @throws ConnectionException when the bridge is not running
     */
    public function print(PrintJob $job, string $escpos): bool
    {
        $response = Http::timeout(5)->post(rtrim($this->baseUrl(), '/').'/print', [
            'job_id' => (string) $job->id,
            'data' => base64_encode($escpos),
        ]);

        return $response->successful();
    }

    public function baseUrl(): string
    {
        return (string) config('pos.print.bridge_url', 'http://127.0.0.1:9100');
    }
}
