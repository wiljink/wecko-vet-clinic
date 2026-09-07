<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 6 — Counter Sales: over-the-counter sales of products flagged for OTC,
 * plus free-format "misc" lines. Allows a walk-in customer or a registered client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no')->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('walk_in')->default(true);
            $table->string('walk_in_name')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sale_date');
            $table->string('status')->default('open');   // open | completed | on_account
            $table->decimal('subtotal_ex_tax', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total_inc_tax', 12, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('counter_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('product'); // product | misc
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price_ex_tax', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(12);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('dispensing_fee', 10, 2)->default(0);
            $table->foreignId('regime_id')->nullable()->constrained('regime_types')->nullOnDelete();
            $table->decimal('line_total_ex_tax', 12, 2)->default(0);
            $table->decimal('line_tax', 12, 2)->default(0);
            $table->decimal('line_total_inc_tax', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_sale_items');
        Schema::dropIfExists('counter_sales');
    }
};
