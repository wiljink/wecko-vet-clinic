<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Barcode;
use App\Support\Import\ProductImporter;
use App\Support\Import\StockLevelImporter;
use App\Support\Import\SupplierImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosBarcodeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ReferentialDataSeeder::class);
    }

    private function drug(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'kind' => 'product', 'name' => 'Test Drug', 'group_id' => Group::firstWhere('name', 'Drugs')?->id,
            'unit_cost_ex_tax' => 10, 'sell_price_ex_tax' => 25, 'tax_rate' => 12, 'list_this_product' => 'both',
        ], $overrides));
    }

    public function test_barcode_encoder_produces_svg_for_alphanumeric_and_numeric_values(): void
    {
        $this->assertStringContainsString('<svg', Barcode::svg('WECKO-00042'));
        $this->assertStringContainsString('<svg', Barcode::svg('4801234567890'));
        $this->assertNull(Barcode::svg(''));
        $this->assertMatchesRegularExpression('/^\d{12}$/', Barcode::generate());
    }

    public function test_scan_scope_resolves_barcode_or_code(): void
    {
        $p = $this->drug(['code' => 'ABC123', 'barcode' => '29000000009']);

        $this->assertSame($p->id, Product::query()->scan('29000000009')->first()?->id);
        $this->assertSame($p->id, Product::query()->scan('ABC123')->first()?->id);
        $this->assertNull(Product::query()->scan('nope')->first());
    }

    public function test_pos_page_completes_a_sale_and_deducts_stock(): void
    {
        $user = User::factory()->create();
        $user->assignRole('principal');
        $this->actingAs($user);

        $product = $this->drug(['sell_price_ex_tax' => 100, 'barcode' => '29000000123']);
        StockMovement::record($product, 'opening', 20, ['location_id' => Location::main()->id]);

        \Livewire\Livewire::test(\App\Filament\Pages\PointOfSale::class)
            ->set('scan', '29000000123')
            ->call('addScan')
            ->assertCount('cart', 1)
            ->set('cart.0.qty', 2)
            ->set('paymentType', 'cash')
            ->set('cashReceived', 300)
            ->call('completeSale')
            ->assertNotified();

        $this->assertEquals(18, $product->fresh()->qty_on_hand);
        $this->assertDatabaseHas('counter_sales', ['status' => 'completed']);
        $this->assertDatabaseHas('payments', ['change_given' => 76.00]);
    }

    public function test_product_importer_creates_then_updates_by_code(): void
    {
        $importer = new ProductImporter;

        $rows = [
            ['name' => 'Imported Drug', 'kind' => 'product', 'code' => 'IMP1', 'barcode' => '29000000777',
                'group' => 'Drugs', 'supplier' => 'Acme Vet', 'tax_rate' => '12', 'unit_cost_ex_tax' => '5',
                'sell_price_ex_tax' => '15', 'dispense_fee' => '0', 'reorder_level' => '10', 'max_holding' => '',
                'list_this_product' => 'both', 'is_active' => 'yes'],
        ];

        $created = $importer->run($rows, dryRun: false);
        $this->assertSame(1, $created->created);
        $this->assertDatabaseHas('products', ['code' => 'IMP1', 'barcode' => '29000000777', 'sell_price_ex_tax' => 15.00]);

        $rows[0]['sell_price_ex_tax'] = '19';
        $updated = $importer->run($rows, dryRun: false);
        $this->assertSame(1, $updated->updated);
        $this->assertDatabaseHas('products', ['code' => 'IMP1', 'sell_price_ex_tax' => 19.00]);
        $this->assertSame(1, Product::where('code', 'IMP1')->count());
    }

    public function test_product_import_dry_run_changes_nothing(): void
    {
        $importer = new ProductImporter;
        $rows = [['name' => 'Ghost', 'kind' => 'product', 'sell_price_ex_tax' => '10']];

        $result = $importer->run($rows, dryRun: true);

        $this->assertSame(1, $result->created);
        $this->assertDatabaseMissing('products', ['name' => 'Ghost']);
    }

    public function test_stock_level_importer_posts_adjusting_movement(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->drug(['code' => 'STK1']);
        StockMovement::record($product, 'opening', 10, ['location_id' => Location::main()->id]);

        $result = (new StockLevelImporter)->run(
            [['code' => 'STK1', 'barcode' => '', 'name' => '', 'qty_on_hand' => '25', 'unit_cost_ex_tax' => '4', 'batch_no' => '', 'expiry_on' => '']],
            dryRun: false,
        );

        $this->assertSame(1, $result->updated);
        $this->assertEquals(25, $product->fresh()->qty_on_hand);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'stock_take', 'qty_change' => 15.00]);
    }

    public function test_supplier_importer_upserts_by_name(): void
    {
        $rows = [['name' => 'Zoetis PH', 'contact_name' => 'Maria', 'email' => 'a@b.com', 'phone' => '', 'address' => '', 'account_no' => '', 'is_active' => 'yes']];

        $this->assertSame(1, (new SupplierImporter)->run($rows, false)->created);
        $this->assertSame(1, (new SupplierImporter)->run($rows, false)->updated);
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_import_data_permission_gates_the_page(): void
    {
        $reception = User::factory()->create();
        $reception->assignRole('receptionist');
        $this->assertFalse($reception->can('import_data'));

        $vet = User::factory()->create();
        $vet->assignRole('veterinarian');
        $this->assertTrue($vet->can('import_data'));
    }
}
