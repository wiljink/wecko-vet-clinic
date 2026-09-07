<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial operations 7.2–7.6: statement runs, banking batches, till sessions,
 * plus marketing campaigns (Setup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_runs', function (Blueprint $table) {
            $table->id();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('min_balance', 12, 2)->default(0);
            $table->string('fee_type')->default('none'); // none | fixed | percent
            $table->decimal('fee_value', 12, 2)->default(0);
            $table->boolean('exclude_no_activity')->default(true);
            $table->string('channel')->default('email'); // email | print
            $table->unsignedInteger('statement_count')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('banking_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('banking_date');
            $table->decimal('cash_total', 12, 2)->default(0);
            $table->decimal('cheque_total', 12, 2)->default(0);
            $table->decimal('eftpos_total', 12, 2)->default(0);
            $table->decimal('card_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('till_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->json('denominations')->nullable();       // {"1000": 3, "500": 2, ...}
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->default(0);
            $table->decimal('variance', 12, 2)->default(0);
            $table->string('status')->default('open');       // open | closed
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('channels');                        // ["email","sms"]
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('filter')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('till_sessions');
        Schema::dropIfExists('banking_batches');
        Schema::dropIfExists('statement_runs');
    }
};
