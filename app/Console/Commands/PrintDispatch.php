<?php

namespace App\Console\Commands;

use App\Models\PosPrintLog;
use App\Print\EscPosReceiptRenderer;
use App\Print\PrintBridgeClient;
use App\Print\PrintJob;
use Illuminate\Console\Command;

/**
 * Drains queued print jobs to the local print bridge.
 *
 * Runs from the POS request's terminating callback, outside the sale
 * transaction: a receipt must never be able to block or fail a sale. One
 * `pos_print_logs` row is written per attempt — including failures — because
 * "did the guest get a receipt" and "was the receipt reprinted" are exactly
 * the questions the log exists to answer, and a missing log row is
 * indistinguishable from a missing receipt.
 */
class PrintDispatch extends Command
{
    protected $signature = 'print:dispatch {--limit=10 : Maximum queued jobs to drain}';

    protected $description = 'Dispatch queued receipt print jobs to the local ESC/POS bridge';

    public function handle(PrintBridgeClient $bridge): int
    {
        $renderer = new EscPosReceiptRenderer((int) config('pos.print.paper_width', 32));

        $limit = max(1, (int) $this->option('limit'));

        PrintJob::where('status', PrintJob::STATUS_QUEUED)
            ->with('docket')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->each(fn (PrintJob $job) => $this->dispatchJob($job, $renderer, $bridge));

        return self::SUCCESS;
    }

    private function dispatchJob(PrintJob $job, EscPosReceiptRenderer $renderer, PrintBridgeClient $bridge): void
    {
        $job->attempts = $job->attempts + 1;
        $job->status = PrintJob::STATUS_SENT;
        $job->save();

        try {
            $escpos = $renderer->render($job->docket);

            if (! $bridge->print($job, $escpos)) {
                throw new \RuntimeException('The print bridge rejected the job.');
            }

            $job->status = PrintJob::STATUS_DONE;
            $job->error = null;
            $job->printed_at = now();
            $job->save();

            $this->logAttempt($job, success: true);
        } catch (\Throwable $e) {
            $job->status = PrintJob::STATUS_FAILED;
            $job->error = $e->getMessage();
            $job->save();

            $this->logAttempt($job, success: false, reason: $e->getMessage());
        }
    }

    private function logAttempt(PrintJob $job, bool $success, ?string $reason = null): void
    {
        $docket = $job->docket;

        PosPrintLog::create([
            'docket_id' => $job->docket_id,
            'printed_by' => auth()->id() ?? $docket?->closed_by ?? $docket?->staff_id,
            'type' => 'receipt',
            'printed_at' => now(),
            'success' => $success,
            'is_reprint' => (bool) $job->is_reprint,
            'reason' => $reason !== null ? mb_substr($reason, 0, 100) : null,
            'node_id' => (string) config('sync.node_id'),
        ]);
    }
}
