<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds an invoice billed by a vendor assigned to the given AP rep.
     *
     * The chain matters: the policy walks invoice -> vendor -> user_id, so the fixture has to
     * build that same chain. `for($user, 'assignee')` names the relationship explicitly
     * because for() would otherwise guess `user` from the User class, and Vendor calls it
     * `assignee`.
     */
    private function invoiceAssignedTo(User $rep): Invoice
    {
        $vendor = Vendor::factory()->for($rep, 'assignee')->create();

        return Invoice::factory()->for($vendor)->create();
    }

    public function test_an_ap_rep_can_view_an_invoice_from_their_own_vendor(): void
    {
        $tom = User::factory()->create();        // factory default role is Ap

        $invoice = $this->invoiceAssignedTo($tom);

        // can() runs the policy and returns a bool — no HTTP request involved, which is why
        // policy tests are much closer to plain unit tests than the route tests you wrote.
        $this->assertTrue($tom->can('view', $invoice));
    }

    public function test_an_ap_rep_cannot_view_an_invoice_from_a_different_vendor(): void
    {
        $tom = User::factory()->create();
        $jerry = User::factory()->create();

        $invoice = $this->invoiceAssignedTo($jerry);

        $this->assertFalse($tom->can('view', $invoice));
    }

    public function test_an_admin_can_view_any_invoice(): void
    {
        $admin = User::factory()->admin()->create();

        $invoice = Invoice::factory()->create();

        $this->assertTrue($admin->can('view', $invoice));
    }

    public function test_nobody_below_admin_can_delete_an_invoice(): void
    {
        $tom = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $invoice = Invoice::factory()->create();

        $this->assertFalse($tom->can('delete', $invoice));
        $this->assertTrue($admin->can('delete', $invoice));
    }
}
