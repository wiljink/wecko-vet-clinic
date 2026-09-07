<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory 5.3–5.7: Stock Orders, Stock Receipts, Stock Takes,
 * Inventory Adjustments and Inventory Returns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->string('status')->default('draft'); // draft | placed | partially_received | received | cancelled
            $table->decimal('total_ex_tax', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_ordered', 12, 2);
            $table->decimal('unit_cost_ex_tax', 12, 4)->default(0);
            $table->decimal('qty_received', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_date');
            $table->string('status')->default('draft'); // draft | posted
            $table->string('supplier_doc_no')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_ex_tax', 12, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_cost_ex_tax', 12, 4)->default(0);
            $table->decimal('mark_up', 6, 2)->nullable();
            $table->decimal('sell_price_ex_tax', 12, 2)->nullable();
            $table->date('expiry_on')->nullable();
            $table->string('batch_no')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('take_date');
            $table->string('status')->default('open'); // open | posted
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 12, 2)->default(0);   // snapshot at time of take
            $table->decimal('counted_qty', 12, 2)->nullable();  // null = "not counted" (manual: leave blank, not zero)
            $table->timestamps();
            $table->unique(['stock_take_id', 'product_id']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('adjustment_date');
            $table->string('remarks')->nullable();
            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_delta', 12, 2);   // + or -
            $table->date('use_by_on')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('direction'); // supplier | customer
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_document')->nullable(); // order_no or invoice_no
            $table->date('return_date');
            $table->string('reason')->nullable();
            $table->string('status')->default('draft'); // draft | posted
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'inventory_return_items', 'inventory_returns',
            'inventory_adjustment_items', 'inventory_adjustments',
            'stock_take_items', 'stock_takes',
            'stock_receipt_items', 'stock_receipts',
            'inventory_order_items', 'inventory_orders',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
