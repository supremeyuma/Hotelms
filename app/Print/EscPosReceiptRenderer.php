<?php

namespace App\Print;

use App\Models\PosDocket;

/**
 * Renders a docket to raw ESC/POS bytes for a thermal receipt printer.
 *
 * Width is a character count, not a millimetre value: 32 columns for 58mm
 * paper, 48 for 80mm. Every line is truncated to the paper width explicitly
 * because an over-long line wraps on some printers and corrupts the receipt
 * on others — the device must never be trusted with layout.
 */
final class EscPosReceiptRenderer
{
    /** ESC @ — initialise printer. */
    private const CMD_INIT = "\x1b\x40";

    /** ESC a n — alignment (0 left, 1 centre). */
    private const CMD_ALIGN_LEFT = "\x1b\x61\x00";

    private const CMD_ALIGN_CENTRE = "\x1b\x61\x01";

    /** ESC E n — emphasised printing. */
    private const CMD_BOLD_ON = "\x1b\x45\x01";

    private const CMD_BOLD_OFF = "\x1b\x45\x00";

    /** GS V m — partial cut, feeds paper first. */
    private const CMD_CUT = "\x1d\x56\x00";

    public const PAPER_58MM_COLUMNS = 32;

    public const PAPER_80MM_COLUMNS = 48;

    public function __construct(private readonly int $paperWidth = self::PAPER_58MM_COLUMNS) {}

    /**
     * @return string raw ESC/POS bytes for a thermal printer
     */
    public function render(PosDocket $docket): string
    {
        $docket->loadMissing(['items', 'payments', 'room', 'booking']);

        $out = self::CMD_INIT;

        // Header: the docket reference, centred and bold.
        $out .= self::CMD_ALIGN_CENTRE;
        $out .= self::CMD_BOLD_ON;
        $out .= $this->centreLine('Docket #'.$docket->docket_number);
        $out .= self::CMD_BOLD_OFF;
        $out .= self::CMD_ALIGN_LEFT;

        // The receipt must show when the sale happened, not when the tab was
        // opened: a tab opened at 18:00 and paid at 23:30 is a 23:30 sale.
        $out .= $this->line('Date: '.($docket->closed_at ?? $docket->opened_at)?->format('Y-m-d H:i'));
        $out .= $this->line('Staff: '.($docket->staff?->name ?? '—'));

        if ($docket->customer_name) {
            $out .= $this->line('Guest: '.$docket->customer_name);
        }
        if ($docket->table_identifier) {
            $out .= $this->line('Table: '.$docket->table_identifier);
        }
        if ($docket->room) {
            $out .= $this->line('Room: '.($docket->room->display_name ?? $docket->room->name ?? $docket->room->code ?? $docket->room->id));
        }
        if ($docket->booking) {
            $dates = $docket->booking->check_in.' → '.$docket->booking->check_out;
            $out .= $this->line('Booking: '.$dates);
        }

        $out .= $this->rule();

        foreach ($docket->items as $item) {
            $out .= $this->column(
                $item->item_name,
                $item->quantity.' x '.number_format((float) $item->unit_price, 2),
            );
        }

        $out .= $this->rule();

        $out .= $this->column('Subtotal', number_format((float) $docket->subtotal, 2));

        if ((float) $docket->vat > 0) {
            $out .= $this->column('VAT', number_format((float) $docket->vat, 2));
        }
        if ((float) $docket->service_charge > 0) {
            $out .= $this->column('Service charge', number_format((float) $docket->service_charge, 2));
        }
        if ((float) $docket->discount_amount > 0) {
            $out .= $this->column('Discount', '-'.number_format((float) $docket->discount_amount, 2));
        }

        $out .= $this->rule();

        $out .= self::CMD_ALIGN_CENTRE;
        $out .= self::CMD_BOLD_ON;
        $out .= $this->centreLine('TOTAL '.number_format((float) $docket->total, 2));
        $out .= self::CMD_BOLD_OFF;
        $out .= self::CMD_ALIGN_LEFT;

        if ($docket->payments->isNotEmpty()) {
            $out .= $this->rule();
            foreach ($docket->payments as $payment) {
                $out .= $this->column(
                    ucfirst(str_replace('_', ' ', $payment->payment_method)),
                    number_format((float) $payment->amount, 2),
                );
            }
        }

        $out .= self::CMD_CUT;

        return $out;
    }

    private function rule(): string
    {
        return str_repeat('-', $this->paperWidth)."\n";
    }

    private function centreLine(string $text): string
    {
        $text = trim($text);
        $text = $this->truncate($text);
        $pad = max(0, intdiv($this->paperWidth - mb_strlen($text), 2));

        return str_repeat(' ', $pad).$text."\n";
    }

    private function line(string $text): string
    {
        return $this->truncate(rtrim($text))."\n";
    }

    private function column(string $left, string $right): string
    {
        $left = $this->truncate(rtrim($left));
        $right = $this->truncate(rtrim($right));

        $gap = $this->paperWidth - mb_strlen($left) - mb_strlen($right);
        if ($gap < 1) {
            // Keep the price whole: the name is the expendable side.
            $left = $this->truncate(rtrim($left), max(1, $this->paperWidth - mb_strlen($right) - 1));
            $gap = max(1, $this->paperWidth - mb_strlen($left) - mb_strlen($right));
        }

        return $left.str_repeat(' ', $gap).$right."\n";
    }

    private function truncate(string $text, ?int $limit = null): string
    {
        $limit ??= $this->paperWidth;
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return ($limit <= 3 ? '' : mb_substr($text, 0, $limit - 3)).'...';
    }
}
