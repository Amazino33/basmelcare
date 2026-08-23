<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * invoice_number and wifi_code are unique across the whole sales table, but
 * Sale carries BranchScope, which hides other branches' rows.
 *
 * Generating a globally-unique value from a branch-filtered query means a
 * till can hand out a number another branch already holds - and keep handing
 * out that same number on every attempt, because nothing it can see ever
 * moves. Production: "Duplicate entry 'INV-20260823-0002'", identical forty
 * minutes apart, until the till was unusable.
 */
class InvoiceNumberBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private function branch(): Branch
    {
        return Branch::withoutGlobalScopes()->create(['name' => 'BRANCH ' . uniqid()]);
    }

    private function sale(string $invoice, ?int $branchId, string $wifi = 'AAAAAA'): Sale
    {
        $sale = Sale::withoutGlobalScopes()->make([
            'invoice_number' => $invoice,
            'wifi_code'      => $wifi,
            'user_id'        => User::factory()->create()->id,
            'total_amount'   => 200,
            'status'         => 'pending',
        ]);

        $sale->branch_id = $branchId;
        $sale->saveQuietly();

        return $sale;
    }

    private function actAsCashierOfBranch(int $branchId): User
    {
        $user = User::factory()->create([
            'role'      => ['cashier'],
            'branch_id' => $branchId,
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_a_number_held_by_another_branch_is_not_reissued(): void
    {
        $other = $this->branch();
        $mine  = $this->branch();

        $today = 'INV-' . now()->format('Ymd') . '-';
        $this->sale($today . '0001', $mine->id, 'AAAAAA');
        $this->sale($today . '0002', $other->id, 'BBBBBB');

        $this->actAsCashierOfBranch($mine->id);

        $this->assertSame(
            $today . '0003',
            Sale::generateInvoiceNumber(),
            'The generator reissued a number another branch already holds.'
        );
    }

    public function test_a_number_held_by_a_branchless_sale_is_not_reissued(): void
    {
        // An admin has no branch_id, so the sale they ring up is invisible to
        // every branch-scoped cashier - the exact production case.
        $mine  = $this->branch();
        $today = 'INV-' . now()->format('Ymd') . '-';

        $this->sale($today . '0001', $mine->id, 'AAAAAA');
        $this->sale($today . '0002', null, 'BBBBBB');

        $this->actAsCashierOfBranch($mine->id);

        $this->assertSame($today . '0003', Sale::generateInvoiceNumber());
    }

    public function test_the_till_is_not_permanently_stuck(): void
    {
        // The failure that mattered: not one collision, but the same one
        // returned on every retry, so the till could never take a sale.
        $other = $this->branch();
        $mine  = $this->branch();
        $today = 'INV-' . now()->format('Ymd') . '-';

        $this->sale($today . '0001', $other->id, 'AAAAAA');
        $this->actAsCashierOfBranch($mine->id);

        $issued = [];

        for ($i = 0; $i < 3; $i++) {
            $number = Sale::generateInvoiceNumber();
            $issued[] = $number;

            Sale::transactWithRetry(fn () => Sale::create([
                'invoice_number' => $number,
                'wifi_code'      => Sale::generateWifiCode(),
                'user_id'        => auth()->id(),
                'total_amount'   => 200,
                'status'         => 'pending',
            ]));
        }

        $this->assertSame([$today . '0002', $today . '0003', $today . '0004'], $issued);
    }

    public function test_the_wifi_code_check_is_not_branch_scoped(): void
    {
        // Asserted against the source rather than by generating codes: a draw
        // from 31^6 will never collide with a chosen value by chance, so a
        // behavioural test here would pass whether or not the bug was present.
        $source = file_get_contents(app_path('Models/Sale.php'));

        $body = substr($source, strpos($source, 'function generateWifiCode'));
        $body = substr($body, 0, strpos($body, "
    }"));

        $this->assertStringContainsString(
            'withoutGlobalScope(BranchScope::class)',
            $body,
            'wifi_code is unique table-wide, so its uniqueness check must see every branch.'
        );
    }

    public function test_the_premise_that_branch_scope_hides_other_branches(): void
    {
        // If this ever stops being true, the fix above is unnecessary.
        $other = $this->branch();
        $mine  = $this->branch();

        $this->sale('INV-X-0001', $other->id, 'ZZZZZZ');
        $this->actAsCashierOfBranch($mine->id);

        $this->assertFalse(
            Sale::where('wifi_code', 'ZZZZZZ')->exists(),
            'Sanity: the scope should hide the other branch.'
        );
        $this->assertTrue(
            Sale::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                ->where('wifi_code', 'ZZZZZZ')->exists()
        );
    }

    public function test_an_admin_still_sees_the_whole_sequence(): void
    {
        $branch = $this->branch();
        $today  = 'INV-' . now()->format('Ymd') . '-';

        $this->sale($today . '0001', $branch->id, 'AAAAAA');

        $this->actingAs(User::factory()->create(['role' => ['admin'], 'branch_id' => null]));

        $this->assertSame($today . '0002', Sale::generateInvoiceNumber());
    }
}
