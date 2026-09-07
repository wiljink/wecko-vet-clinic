<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 7 — The Financial Sub-system: invoices, payments (with split
 * allocations), and credit/debit account adjustments. The Financial UI
 * (statements, aging, banking, till) is built on top in a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source');  // Consultation | CounterSale
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal_ex_tax', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->default('unpaid'); // unpaid | partial | paid | void
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('service'); // service | drug | vaccination | misc
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price_ex_tax', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(12);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('dispensing_fee', 10, 2)->default(0);
            $table->decimal('line_total_ex_tax', 12, 2)->default(0);
            $table->decimal('line_tax', 12, 2)->default(0);
            $table->decimal('line_total_inc_tax', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('payment_type')->default('cash'); // cash | credit_card | cheque | eftpos | advance_payment
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->default(0);
            $table->foreignId('card_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->boolean('is_refund')->default(false);
            $table->boolean('banked')->default(false);
            $table->date('banked_on')->nullable();
            $table->foreignId('banking_batch_id')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'payment_type']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('account_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('direction'); // credit | debit
            $table->string('reason');
            $table->decimal('amount', 12, 2);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('adjusted_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_adjustments');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
