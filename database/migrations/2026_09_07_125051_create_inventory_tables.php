<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory "complex files" from the manual: Suppliers, and a single products
 * table covering Products, Vaccines and Services (kind column). Plus the
 * append-only stock_movements ledger that is the source of truth for quantity
 * on hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('account_no')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->default('product');       // product | vaccine | service
            $table->string('name');
            $table->string('description')->nullable();          // "title generator"
            $table->string('code')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('animal_size')->nullable();          // Small / Large / Small and Large Animal

            // Pricing
            $table->decimal('tax_rate', 5, 2)->default(12);
            $table->decimal('mark_up', 6, 2)->default(0);       // percent
            $table->decimal('unit_cost_ex_tax', 12, 4)->default(0);
            $table->decimal('list_cost_price', 12, 4)->default(0);
            $table->decimal('sell_price_ex_tax', 12, 2)->default(0);
            $table->decimal('sell_price_inc_tax', 12, 2)->default(0);
            $table->decimal('dispense_fee', 10, 2)->default(0);
            $table->boolean('dispense_fee_always')->default(false);
            $table->boolean('discountable')->default(true);

            // Listing / behaviour
            $table->string('list_this_product')->default('both'); // none | consult | otc | both
            $table->boolean('print_label')->default(false);
            $table->text('home_care_note')->nullable();
            $table->foreignId('patient_reminder_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('regime_id')->nullable()->constrained('regime_types')->nullOnDelete();

            // Stock (product + vaccine only)
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('pack_qty', 10, 2)->default(1);
            $table->decimal('qty_on_hand', 12, 2)->default(0);   // cached from stock_movements
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->decimal('max_holding', 12, 2)->default(0);
            $table->boolean('has_expiry')->default(false);

            // Vaccine extras
            $table->text('protection')->nullable();              // "F4 - FELINE RHINOTRACHEITIS..."
            $table->foreignId('certificate_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->boolean('prints_certificate')->default(false);
            $table->unsignedInteger('booster_years')->default(0);
            $table->unsignedInteger('booster_months')->default(0);
            $table->unsignedInteger('booster_days')->default(0);
            $table->string('next_vaccination_note')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
            $table->index('name');
        });

        // Needles / swabs / gloves consumed per vaccine dose — auto stock deduction,
        // not shown as consult line items (manual 9.4.3.2).
        Schema::create('vaccine_consumables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaccine_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qty', 10, 2)->default(1);
            $table->timestamps();
            $table->unique(['vaccine_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');            // receipt | sale | vaccine_consumable | adjustment | stock_take | return_supplier | return_customer | opening
            $table->decimal('qty_change', 12, 2); // + in, - out
            $table->decimal('unit_cost_ex_tax', 12, 4)->default(0);
            $table->nullableMorphs('source');  // Consultation | CounterSale | StockReceipt | StockTake | InventoryAdjustment | InventoryReturn
            $table->string('batch_no')->nullable();
            $table->date('expiry_on')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->timestamp('moved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('vaccine_consumables');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
    }
};
