<?php

namespace Tests\Feature;

use App\Filament\Widgets\SalesPaymentsOverview;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function drug(): Product
    {
        return Product::create([
            'kind' => 'product', 'name' => 'Branch Drug',
            'unit_cost_ex_tax' => 10, 'sell_price_ex_tax' => 25, 'tax_rate' => 12,
            'list_this_product' => 'both',
        ]);
    }

    public function test_branch_pinned_user_only_sees_their_branch_invoices(): void
    {
        $branchA = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $branchB = Location::create(['name' => 'Branch B', 'type' => Location::TYPE_BRANCH]);

        $client = Client::create(['surname' => 'Owner']);
        $invoiceA = Invoice::create(['client_id' => $client->id, 'location_id' => $branchA->id, 'invoice_date' => now()]);
        $invoiceB = Invoice::create(['client_id' => $client->id, 'location_id' => $branchB->id, 'invoice_date' => now()]);

        $userA = User::factory()->create(['home_location_id' => $branchA->id]);
        $this->actingAs($userA);

        $visible = Invoice::pluck('id')->all();
        $this->assertContains($invoiceA->id, $visible);
        $this->assertNotContains($invoiceB->id, $visible);

        $principal = User::factory()->create()->assignRole('principal');
        $this->actingAs($principal);
        $visibleToPrincipal = Invoice::pluck('id')->all();
        $this->assertContains($invoiceA->id, $visibleToPrincipal);
        $this->assertContains($invoiceB->id, $visibleToPrincipal);
    }

    public function test_stock_movement_tracks_quantity_per_branch(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('principal'));
        $branchA = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $branchB = Location::create(['name' => 'Branch B', 'type' => Location::TYPE_BRANCH]);
        $product = $this->drug();

        StockMovement::record($product, 'opening', 50, ['location_id' => $branchA->id]);
        StockMovement::record($product, 'opening', 20, ['location_id' => $branchB->id]);
        StockMovement::record($product, 'sale', -5, ['location_id' => $branchA->id]);

        $this->assertEquals(45, ProductStockLevel::qtyOf($product, $branchA->id));
        $this->assertEquals(20, ProductStockLevel::qtyOf($product, $branchB->id));
        $this->assertEquals(65, $product->fresh()->qty_on_hand); // company-wide total
    }

    public function test_stock_transfer_moves_quantity_between_branches(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('principal'));
        $branchA = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $branchB = Location::create(['name' => 'Branch B', 'type' => Location::TYPE_BRANCH]);
        $product = $this->drug();

        StockMovement::record($product, 'opening', 30, ['location_id' => $branchA->id]);

        $transfer = StockTransfer::create([
            'from_location_id' => $branchA->id, 'to_location_id' => $branchB->id, 'transfer_date' => now(),
        ]);
        $transfer->items()->create(['product_id' => $product->id, 'qty' => 10]);
        $transfer->post();

        $this->assertEquals(20, ProductStockLevel::qtyOf($product, $branchA->id));
        $this->assertEquals(10, ProductStockLevel::qtyOf($product, $branchB->id));
        $this->assertSame('posted', $transfer->fresh()->status);
    }

    public function test_dashboard_sales_widget_reflects_branch_filter(): void
    {
        $branchA = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $branchB = Location::create(['name' => 'Branch B', 'type' => Location::TYPE_BRANCH]);
        $client = Client::create(['surname' => 'Owner']);

        $invoiceA = Invoice::create(['client_id' => $client->id, 'location_id' => $branchA->id, 'invoice_date' => now()]);
        $invoiceA->items()->create(['kind' => 'service', 'description' => 'Consult', 'qty' => 1, 'unit_price_ex_tax' => 1000, 'tax_rate' => 0]);

        $invoiceB = Invoice::create(['client_id' => $client->id, 'location_id' => $branchB->id, 'invoice_date' => now()]);
        $invoiceB->items()->create(['kind' => 'service', 'description' => 'Consult', 'qty' => 1, 'unit_price_ex_tax' => 500, 'tax_rate' => 0]);

        $this->actingAs(User::factory()->create()->assignRole('principal'));

        Livewire::test(SalesPaymentsOverview::class, ['filters' => ['location_id' => $branchA->id]])
            ->assertSee('1,000.00')
            ->assertDontSee('1,500.00');

        Livewire::test(SalesPaymentsOverview::class, ['filters' => []])
            ->assertSee('1,500.00');
    }

    public function test_consultation_finalize_generates_invoice_at_same_branch(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('principal'));
        $branch = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $client = Client::create(['surname' => 'Owner']);
        $patient = \App\Models\Patient::create([
            'client_id' => $client->id, 'name' => 'Rex', 'gender' => 'male',
            'birth_date' => now()->subYear(), 'neuter_status' => 'not_neutered',
        ]);

        $consult = Consultation::create([
            'client_id' => $client->id, 'patient_id' => $patient->id,
            'location_id' => $branch->id, 'consult_date' => now(),
        ]);
        $consult->items()->create(['kind' => 'service', 'description' => 'Consult', 'qty' => 1, 'unit_price_ex_tax' => 500, 'tax_rate' => 0]);

        $invoice = $consult->finalize();

        $this->assertSame($branch->id, $invoice->location_id);
    }

    public function test_the_system_always_keeps_exactly_one_main_branch(): void
    {
        $branchA = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $branchB = Location::create(['name' => 'Branch B', 'type' => Location::TYPE_BRANCH]);

        // First branch created auto-becomes main.
        $this->assertTrue($branchA->fresh()->is_main);
        $this->assertFalse($branchB->fresh()->is_main);

        // Can't unset the only main branch by editing it off.
        $branchA->update(['is_main' => false]);
        $this->assertTrue($branchA->fresh()->is_main);

        // Can't delete the main branch.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $branchA->delete();
    }

    public function test_main_switches_to_a_different_branch_without_ever_having_zero(): void
    {
        $branchA = Location::create(['name' => 'Branch A', 'type' => Location::TYPE_BRANCH]);
        $branchB = Location::create(['name' => 'Branch B', 'type' => Location::TYPE_BRANCH]);

        $branchB->update(['is_main' => true]);

        $this->assertFalse($branchA->fresh()->is_main);
        $this->assertTrue($branchB->fresh()->is_main);
        $this->assertSame($branchB->id, Location::main()->id);

        // Branch A is no longer main, so it's deletable now (and it isn't the last branch).
        $branchA = $branchA->fresh();
        $this->assertTrue($branchA->isDeletable());
        $branchA->delete();
        $this->assertDatabaseMissing('locations', ['id' => $branchA->id]);

        // Branch B is main and the only branch left — can't delete it either way.
        $this->assertFalse($branchB->fresh()->isDeletable());
    }

    public function test_the_seeded_admin_account_is_tied_to_the_main_branch(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('email', 'admin@wecko.test')->first();
        $main = Location::main();

        $this->assertNotNull($main, 'DemoSeeder should have created a main branch.');
        $this->assertTrue($admin->hasRole('principal'));
        $this->assertSame($main->id, $admin->home_location_id);
    }
}
