<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Setup > General — a single-row settings table.
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();

            // Company information
            $table->string('company_name')->default('Wecko Vet Clinic');
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('website')->nullable();
            $table->string('tin')->nullable();           // TIN / ABN — printed on invoices

            // Country / tax
            $table->string('country')->default('Philippines');
            $table->string('currency_symbol')->default('₱');
            $table->decimal('tax_rate', 5, 2)->default(12);
            $table->string('tax_label')->default('VAT');
            $table->string('date_format')->default('d/m/Y');
            $table->json('coin_denominations')->nullable(); // for Balance-the-Till

            // Operational flags (Setup > Misc)
            $table->boolean('show_reminders_on_login')->default(true);
            $table->unsignedInteger('reminder_days_window')->default(7);
            $table->boolean('auto_generate_product_code')->default(true);
            $table->boolean('display_patients_per_client')->default(true);
            $table->boolean('open_discounting')->default(false);
            $table->unsignedInteger('accounting_period_days')->default(30); // 15 or 30 — aging buckets
            $table->unsignedInteger('default_desex_age_months')->default(6);
            $table->decimal('default_dispense_fee', 10, 2)->default(0);
            $table->decimal('default_injection_fee', 10, 2)->default(0);

            // Referential Data default values — prefill new consults
            $table->text('default_history')->nullable();
            $table->text('default_examination')->nullable();
            $table->text('default_tests')->nullable();
            $table->text('default_differential_diagnosis')->nullable();
            $table->text('default_consult_diagnosis')->nullable();

            $table->timestamps();
        });

        // Setup > Template & Document Management
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');          // letter | email | sms | certificate | home_care | reminder_letter | reminder_email | reminder_sms | statement | marketing
            $table->string('channel')->nullable(); // letter | email | sms
            $table->string('subject')->nullable();
            $table->longText('body');         // supports merge fields e.g. {{ client.name }}
            $table->foreignId('patient_reminder_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('sequence')->default(1); // 1st / 2nd reminder
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('company_settings');
    }
};
