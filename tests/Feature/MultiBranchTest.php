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
}
