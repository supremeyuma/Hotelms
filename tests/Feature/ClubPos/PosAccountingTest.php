<?php

namespace Tests\Feature\ClubPos;

use App\Models\Account;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosSession;
use App\Models\User;
use App\Services\Reports\ProfitAndLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A settled POS sale must reach the books.
 *
 * Before this, cash and card sales wrote a docket and a payment row but no
 * journal entry, so club revenue never appeared in profit & loss. These tests
 * pin the entry, its balance, and the report that depends on it.
 */
class PosAccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private PosSession $session;
    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $permission = Permission::create([
            'name' => 'club.pos.access',
            'slug' => 'club.pos.access',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);

        $device = PosDevice::factory()->create();

        $this->session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
        ]);

        $this->item = MenuItem::factory()->create([
            'price' => 1000,
            'is_available' => true,
            'service_area' => 'bar',
        ]);
    }

    public function test_a_cash_sale_posts_a_balanced_journal_entry(): void
    {
        $docketId = $this->openDocketWithItem();

        $docket = PosDocket::findOrFail($docketId);
        $total = (float) $docket->total;

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.pay', $docketId), [
                'payments' => [['method' => 'cash', 'amount' => $total]],
            ])
            ->assertOk();

        $entry = \App\Models\JournalEntry::where('reference_type', 'pos_docket')
            ->where('reference_id', $docket->docket_number)
            ->with('lines')
            ->first();

        $this->assertNotNull($entry, 'the sale must post a journal entry');

        $debits = round((float) $entry->lines->sum('debit'), 2);
        $credits = round((float) $entry->lines->sum('credit'), 2);

        $this->assertSame($debits, $credits, 'a journal entry must balance');
        $this->assertSame(round($total, 2), $debits, 'the entry must equal the sale total');

        // Cash received into the drawer, revenue earned on the sale value.
        $cash = Account::where('name', 'Cash')->firstOrFail();
        $beverage = Account::where('name', 'Beverage Revenue')->firstOrFail();

        $this->assertSame(
            round($total, 2),
            round((float) $entry->lines->where('account_id', $cash->id)->sum('debit'), 2),
        );
        $this->assertSame(
            round((float) $docket->subtotal - (float) $docket->discount_amount, 2),
            round((float) $entry->lines->where('account_id', $beverage->id)->sum('credit'), 2),
        );
    }

    public function test_a_card_sale_posts_to_the_bank(): void
    {
        $docketId = $this->openDocketWithItem();
        $total = (float) PosDocket::findOrFail($docketId)->total;

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.pay', $docketId), [
                'payments' => [['method' => 'card', 'amount' => $total]],
            ])
            ->assertOk();

        $bank = Account::where('name', 'Bank')->firstOrFail();

        $this->assertDatabaseHas('journal_lines', [
            'account_id' => $bank->id,
            'debit' => $total,
        ]);
    }

    public function test_a_club_sale_shows_up_in_profit_and_loss(): void
    {
        $docketId = $this->openDocketWithItem();
        $docket = PosDocket::findOrFail($docketId);

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.pay', $docketId), [
                'payments' => [['method' => 'cash', 'amount' => (float) $docket->total]],
            ])
            ->assertOk();

        $today = Carbon::today();
        $report = app(ProfitAndLossService::class)->generate($today, $today);

        $beverage = collect($report['revenue'])
            ->firstWhere('account', 'Beverage Revenue');

        $this->assertNotNull($beverage, 'beverage revenue must appear in the P&L');
        $this->assertSame(
            round((float) $docket->subtotal - (float) $docket->discount_amount, 2),
            round((float) $beverage['amount'], 2),
        );
    }

    public function test_a_room_charge_sale_is_not_posted_twice(): void
    {
        // Room charges are posted by BillingService to the folio; the POS ledger
        // must not add a second revenue entry for the same money.
        $booking = \App\Models\Booking::factory()->create();

        $docketId = $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), [
                'session_id' => $this->session->id,
                'booking_id' => $booking->id,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 1],
        )->assertOk();

        $docket = PosDocket::findOrFail($docketId);

        $this->actingAs($this->staff)->postJson(route('club.pos.dockets.pay', $docketId), [
            'payments' => [['method' => 'room_charge', 'amount' => (float) $docket->total]],
        ])->assertOk();

        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => 'pos_docket',
            'reference_id' => $docket->docket_number,
        ]);
    }

    private function openDocketWithItem(): string
    {
        $docketId = $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 2],
        )->assertOk();

        return $docketId;
    }
}
