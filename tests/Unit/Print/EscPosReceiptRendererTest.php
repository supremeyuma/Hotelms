<?php

namespace Tests\Unit\Print;

use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosDocketPayment;
use App\Models\PosSession;
use App\Models\User;
use App\Print\EscPosReceiptRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The receipt renderer produces raw ESC/POS bytes, not HTML. Width is a
 * character count — 32 columns for 58mm paper, 48 for 80mm — and every line
 * must be truncated explicitly, because an over-long line wraps on some
 * printers and corrupts the receipt on others.
 */
class EscPosReceiptRendererTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create();
    }

    private function makeDocket(array $attributes = []): PosDocket
    {
        $device = PosDevice::factory()->create();
        $session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
        ]);

        return PosDocket::create(array_merge([
            'device_id' => $device->id,
            'session_id' => $session->id,
            'staff_id' => $this->staff->id,
            'docket_number' => 7,
            'status' => 'closed',
            'subtotal' => 2000,
            'vat' => 0,
            'service_charge' => 0,
            'discount_amount' => 0,
            'total' => 2000,
            'opened_at' => now(),
            'closed_at' => now(),
        ], $attributes));
    }

    private function addItem(PosDocket $docket, string $name, int $quantity, float $unitPrice): void
    {
        PosDocketItem::create([
            'docket_id' => $docket->id,
            'menu_item_id' => MenuItem::factory()->create(['price' => $unitPrice])->id,
            'item_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ]);
    }

    private function addPayment(PosDocket $docket, string $method, float $amount): void
    {
        PosDocketPayment::create([
            'docket_id' => $docket->id,
            'payment_method' => $method,
            'amount' => $amount,
        ]);
    }

    /**
     * Printable content of the receipt with the ESC/POS control bytes removed,
     * so line lengths can be checked against the paper width.
     *
     * Strips whole command sequences, not just the single control byte: ESC a
     * n, ESC E n and GS V m n each carry argument bytes that would otherwise
     * be mistaken for printable characters.
     *
     * @return list<string>
     */
    private function printableLines(string $escpos): array
    {
        return array_map(
            fn (string $line) => preg_replace(
                ['/\x1b[\x40-\x7e][\x00-\xff]?/', '/\x1d[\x40-\x7e][\x00-\xff]?/', '/[\x00-\x1f]/'],
                '',
                $line,
            ),
            explode("\n", $escpos),
        );
    }

    public function test_output_initialises_the_printer_and_ends_with_a_cut(): void
    {
        $docket = $this->makeDocket();
        $this->addItem($docket, 'House Lager', 2, 1000);

        $out = (new EscPosReceiptRenderer)->render($docket);

        $this->assertStringStartsWith("\x1b\x40", $out, 'ESC @ must initialise the printer.');
        $this->assertStringEndsWith("\x1d\x56\x00", $out, 'GS V 0 must cut the paper.');
    }

    public function test_receipt_carries_the_docket_guest_items_totals_and_payment(): void
    {
        $docket = $this->makeDocket([
            'customer_name' => 'Jane Guest',
            'table_identifier' => 'T4',
        ]);
        $this->addItem($docket, 'House Lager', 2, 1000);
        $this->addPayment($docket, 'cash', 2000);

        $out = (new EscPosReceiptRenderer)->render($docket);

        $this->assertStringContainsString('Docket #7', $out);
        $this->assertStringContainsString('Guest: Jane Guest', $out);
        $this->assertStringContainsString('Table: T4', $out);
        $this->assertStringContainsString('House Lager', $out);
        $this->assertStringContainsString('2 x 1,000.00', $out);
        $this->assertStringContainsString('TOTAL 2,000.00', $out);
        $this->assertStringContainsString('Cash', $out);
    }

    public function test_vat_service_charge_and_discount_appear_when_charged(): void
    {
        $docket = $this->makeDocket([
            'subtotal' => 2000,
            'vat' => 300,
            'service_charge' => 200,
            'discount_amount' => 100,
            'total' => 2400,
        ]);
        $this->addItem($docket, 'House Lager', 2, 1000);

        $out = (new EscPosReceiptRenderer)->render($docket);

        $this->assertStringContainsString('Subtotal', $out);
        $this->assertStringContainsString('VAT', $out);
        $this->assertStringContainsString('300.00', $out);
        $this->assertStringContainsString('Service charge', $out);
        $this->assertStringContainsString('200.00', $out);
        $this->assertStringContainsString('Discount', $out);
        $this->assertStringContainsString('-100.00', $out);
    }

    /**
     * An untruncated line wraps on most cheap printers and corrupts the
     * receipt on others, so nothing may exceed the paper width — whatever the
     * length of a guest's name or a menu item's name.
     */
    public function test_no_printed_line_exceeds_the_paper_width(): void
    {
        $docket = $this->makeDocket(['customer_name' => str_repeat('Very Long Guest Name ', 6)]);
        $this->addItem($docket, str_repeat('An Unreasonably Long Menu Item Name ', 4), 99, 12345.67);
        $this->addPayment($docket, 'mobile_money', 999999.99);

        foreach ([32, 48] as $width) {
            $out = (new EscPosReceiptRenderer($width))->render($docket);

            foreach ($this->printableLines($out) as $line) {
                $this->assertLessThanOrEqual(
                    $width,
                    mb_strlen($line),
                    "A line exceeds the {$width}-column paper width and would corrupt the receipt. Offending line: [{$line}] (len ".mb_strlen($line).')',
                );
            }
        }
    }

    public function test_overlong_names_truncate_with_an_ellipsis(): void
    {
        $docket = $this->makeDocket();
        $this->addItem($docket, str_repeat('X', 80), 1, 100);

        $out = (new EscPosReceiptRenderer)->render($docket);

        $this->assertStringContainsString('XXX...', $out);
        $this->assertStringNotContainsString(str_repeat('X', 80), $out);
    }

    public function test_the_80mm_profile_uses_forty_eight_columns(): void
    {
        $docket = $this->makeDocket(['customer_name' => str_repeat('Guest ', 10)]);
        $this->addItem($docket, 'Long Named Cocktail', 1, 1500);

        $out = (new EscPosReceiptRenderer(EscPosReceiptRenderer::PAPER_80MM_COLUMNS))->render($docket);

        $this->assertStringContainsString(str_repeat('-', 48), $out, 'The 80mm rule must span 48 columns.');
        $this->assertStringNotContainsString(str_repeat('-', 32)."\n\n", $out);
    }
}
