<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\CounterSale;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\LineTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicWorkflowTest extends TestCase
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

    private function patient(): Patient
    {
        $client = Client::create(['surname' => 'Tester', 'given_name' => 'Pat']);

        return Patient::create([
            'client_id' => $client->id, 'name' => 'Rex', 'gender' => 'male',
            'birth_date' => now()->subYear(), 'neuter_status' => 'not_neutered',
            'species_id' => \App\Models\Species::first()->id,
        ]);
    }

    public function test_line_totals_apply_discount_fee_and_tax_in_order(): void
    {
        $t = LineTotals::forLine(qty: 2, unitPriceExTax: 100, taxRate: 12, discountPct: 10, dispensingFee: 50);

        // (2*100) - 10% + 50 = 230 ex; +12% tax = 27.60
        $this->assertSame(230.0, $t['ex_tax']);
        $this->assertSame(27.6, $t['tax']);
        $this->assertSame(257.6, $t['inc_tax']);
    }

    public function test_stock_movement_updates_cached_quantity(): void
    {
        $drug = $this->drug();
        StockMovement::record($drug, 'opening', 100);
        StockMovement::record($drug, 'sale', -15);

        $this->assertEquals(85, $drug->fresh()->qty_on_hand);
    }

    public function test_finalizing_a_consult_deducts_stock_and_raises_an_invoice(): void
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();
        $drug = $this->drug();
        StockMovement::record($drug, 'opening', 50);

        $consult = Consultation::create([
            'client_id' => $patient->client_id, 'patient_id' => $patient->id, 'consult_date' => now(),
        ]);
        $consult->items()->create([
            'kind' => 'drug', 'product_id' => $drug->id, 'description' => $drug->name,
            'qty' => 5, 'unit_price_ex_tax' => 25, 'tax_rate' => 12,
        ]);

        $invoice = $consult->finalize();

        $this->assertSame('finalized', $consult->fresh()->status);
        $this->assertEquals(45, $drug->fresh()->qty_on_hand);
        $this->assertEqualsWithDelta(140.0, (float) $invoice->total, 0.01); // 125 ex + 15 tax
        $this->assertDatabaseHas('prescriptions', ['product_id' => $drug->id, 'quantity' => 5]);
    }

    public function test_vaccination_line_creates_a_booster_reminder(): void
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();
        $vaccine = Product::create([
            'kind' => 'vaccine', 'name' => 'C5', 'sell_price_ex_tax' => 500, 'tax_rate' => 12,
            'booster_months' => 12, 'list_this_product' => 'consult',
            'patient_reminder_type_id' => \App\Models\PatientReminderType::firstWhere('category', 'vaccination')?->id,
        ]);

        $consult = Consultation::create([
            'client_id' => $patient->client_id, 'patient_id' => $patient->id, 'consult_date' => now(),
        ]);
        $consult->items()->create([
            'kind' => 'vaccination', 'product_id' => $vaccine->id, 'description' => 'C5',
            'qty' => 1, 'unit_price_ex_tax' => 500, 'tax_rate' => 12,
        ]);
        $consult->finalize();

        $this->assertDatabaseHas('vaccinations', ['patient_id' => $patient->id, 'name' => 'C5']);
        $reminder = Reminder::where('patient_id', $patient->id)->where('category', 'vaccination')->first();
        $this->assertNotNull($reminder);
        $this->assertTrue($reminder->due_on->isSameDay(now()->addMonths(12)));
    }

    public function test_patient_transfer_moves_consults_but_not_invoices(): void
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();
        $oldOwner = $patient->client;
        $newOwner = Client::create(['surname' => 'NewOwner']);

        $consult = Consultation::create([
            'client_id' => $oldOwner->id, 'patient_id' => $patient->id, 'consult_date' => now(),
        ]);
        $consult->items()->create(['kind' => 'service', 'description' => 'Consult', 'qty' => 1, 'unit_price_ex_tax' => 500, 'tax_rate' => 12]);
        $invoice = $consult->finalize();

        \App\Models\PatientTransfer::create([
            'patient_id' => $patient->id, 'from_client_id' => $oldOwner->id,
            'to_client_id' => $newOwner->id, 'transferred_at' => now(),
        ]);
        $consult->update(['client_id' => $newOwner->id]);
        $patient->update(['client_id' => $newOwner->id]);

        $this->assertSame($newOwner->id, $consult->fresh()->client_id);
        $this->assertSame($oldOwner->id, $invoice->fresh()->client_id);
    }

    public function test_counter_sale_completion_takes_payment_with_change(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->drug(['sell_price_ex_tax' => 100]);
        StockMovement::record($product, 'opening', 20);

        $sale = CounterSale::create(['walk_in' => true, 'walk_in_name' => 'Jo', 'sale_date' => now()]);
        $sale->items()->create(['kind' => 'product', 'product_id' => $product->id, 'description' => $product->name, 'qty' => 2, 'unit_price_ex_tax' => 100, 'tax_rate' => 12]);
        $sale->refresh();

        [$invoice, $payment] = $sale->complete(['payment_type' => 'cash', 'cash_received' => 300]);

        $this->assertEqualsWithDelta(224.0, (float) $invoice->total, 0.01); // 200 + 12% tax
        $this->assertEqualsWithDelta(76.0, (float) $payment->change_given, 0.01);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(18, $product->fresh()->qty_on_hand);
    }

    public function test_payment_allocates_across_invoices_oldest_first(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['surname' => 'Payer']);
        $older = Invoice::create(['client_id' => $client->id, 'invoice_date' => now()->subMonth()]);
        $older->items()->create(['kind' => 'service', 'description' => 'A', 'qty' => 1, 'unit_price_ex_tax' => 100, 'tax_rate' => 0]);
        $newer = Invoice::create(['client_id' => $client->id, 'invoice_date' => now()]);
        $newer->items()->create(['kind' => 'service', 'description' => 'B', 'qty' => 1, 'unit_price_ex_tax' => 100, 'tax_rate' => 0]);

        $payment = Payment::create(['client_id' => $client->id, 'payment_type' => 'cash', 'amount' => 150, 'received_at' => now()]);
        $payment->allocateTo([$older->fresh(), $newer->fresh()]);

        $this->assertSame('paid', $older->fresh()->status);
        $this->assertEqualsWithDelta(50.0, (float) $newer->fresh()->balance, 0.01);
    }

    public function test_reminder_generation_flags_patients_past_desexing_age(): void
    {
        $client = Client::create(['surname' => 'Owner']);
        Patient::create([
            'client_id' => $client->id, 'name' => 'Milo', 'gender' => 'female',
            'birth_date' => now()->subYears(2), 'neuter_status' => 'not_neutered',
            'species_id' => \App\Models\Species::first()->id,
        ]);

        $this->artisan('reminders:generate')->assertSuccessful();

        $this->assertDatabaseHas('reminders', ['category' => 'desexing', 'status' => 'pending']);
    }

    public function test_receptionist_cannot_finalize_consultations(): void
    {
        $reception = User::factory()->create();
        $reception->assignRole('receptionist');

        $this->assertFalse($reception->can('finalize_consultation'));
        $this->assertTrue($reception->can('create_appointment'));
        $this->assertFalse($reception->can('view_any_reference_data'));
    }

    public function test_statement_fee_calculation(): void
    {
        $run = new \App\Models\StatementRun(['fee_type' => 'percent', 'fee_value' => 2]);
        $this->assertSame(20.0, $run->feeFor(1000));

        $run->fee_type = 'fixed';
        $run->fee_value = 15;
        $this->assertSame(15.0, $run->feeFor(1000));
    }
}
