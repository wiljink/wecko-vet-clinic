<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 6 — The Consultation Sub-System: the SOAP consult, its line items
 * (services / drugs / vaccinations / misc), standard-consult templates, the
 * vaccination register and drug prescriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('consult_date');

            // Vitals
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();

            // Medical descriptions (default values seeded from CompanySetting)
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('tests')->nullable();
            $table->text('comment')->nullable();
            $table->text('differential_diagnosis')->nullable();
            $table->text('consult_diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('home_care_notes')->nullable();

            $table->string('status')->default('open'); // open | closed | finalized
            $table->decimal('subtotal_ex_tax', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total_inc_tax', 12, 2)->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'consult_date']);
        });

        Schema::create('consultation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('service'); // service | drug | vaccination | misc
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price_ex_tax', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(12);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('dispensing_fee', 10, 2)->default(0);
            $table->decimal('injection_fee', 10, 2)->default(0);
            $table->foreignId('regime_id')->nullable()->constrained('regime_types')->nullOnDelete();
            $table->string('drug_regime')->nullable();
            $table->decimal('line_total_ex_tax', 12, 2)->default(0);
            $table->decimal('line_tax', 12, 2)->default(0);
            $table->decimal('line_total_inc_tax', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('standard_consults', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('appointment_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('standard_consult_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_consult_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('service');
            $table->decimal('qty', 12, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('batch_no')->nullable();
            $table->date('given_on');
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('booster_due_on')->nullable();
            $table->text('protection')->nullable();
            $table->boolean('certificate_printed')->default(false);
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drug_name');
            $table->foreignId('regime_id')->nullable()->constrained('regime_types')->nullOnDelete();
            $table->string('dosage')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('dispensing_fee', 10, 2)->default(0);
            $table->date('start_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('vaccinations');
        Schema::dropIfExists('standard_consult_items');
        Schema::dropIfExists('standard_consults');
        Schema::dropIfExists('consultation_items');
        Schema::dropIfExists('consultations');
    }
};
