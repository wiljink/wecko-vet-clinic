<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->nullable()->constrained()->nullOnDelete();
            $table->string('given_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('surname');                          // the one mandatory field
            $table->string('company_name')->nullable();
            $table->boolean('use_company_as_first_address')->default(false);
            $table->string('partner_name')->nullable();         // trusted family member who may bring patients in

            $table->string('email')->nullable();
            $table->string('residence_phone')->nullable();
            $table->string('office_phone')->nullable();
            $table->string('office_ext')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('fax')->nullable();

            $table->foreignId('account_name_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type')->nullable();         // Cash, Account, Card...
            $table->string('statement_type')->default('email'); // email | print | none
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->date('account_opened_on')->nullable();
            $table->text('notes')->nullable();

            // Communication preferences — Setup 2.2.5 (letter / email / sms x appointments / reminders / marketing)
            $table->boolean('appointments_by_letter')->default(false);
            $table->boolean('appointments_by_email')->default(true);
            $table->boolean('appointments_by_sms')->default(true);
            $table->boolean('reminders_by_letter')->default(false);
            $table->boolean('reminders_by_email')->default(true);
            $table->boolean('reminders_by_sms')->default(true);
            $table->boolean('marketing_by_letter')->default(false);
            $table->boolean('marketing_by_email')->default(false);
            $table->boolean('marketing_by_sms')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['surname', 'given_name']);
        });

        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();               // Home, Work, Farm
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('suburb')->nullable();
            $table->string('postcode')->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->string('street_directory_ref')->nullable();
            $table->string('travel_distance')->nullable();      // for farm / large-animal call-outs
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete(); // current owner
            $table->string('name');
            $table->string('microchip_no')->nullable();
            $table->foreignId('species_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('gender')->default('unknown');       // female | male | unknown
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('age_years')->nullable();  // used when birth_date unknown
            $table->unsignedSmallInteger('age_months')->nullable();
            $table->unsignedSmallInteger('age_weeks')->nullable();
            $table->date('date_neutered')->nullable();
            $table->string('neuter_status')->default('not_neutered'); // neutered | not_neutered | owner_declines
            $table->decimal('weight', 8, 2)->nullable();        // kg — latest known
            $table->string('temperament')->nullable();
            $table->string('insurance_policy_no')->nullable();
            $table->string('heart_wormer')->nullable();
            $table->string('int_wormer')->nullable();
            $table->string('flea_control')->nullable();
            $table->string('diet')->nullable();
            $table->text('behavioural_warning')->nullable();
            $table->date('first_visit_on')->nullable();
            $table->date('last_visit_on')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('microchip_no');
        });

        Schema::create('patient_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('to_client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('transferred_at');
            $table->timestamps();
        });

        // Polymorphic file/chart store — patients (charts, docs, images, video),
        // and later consultations, vaccinations (certificates), etc.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('type')->default('doc'); // skin_chart | nrsg_chart | serial_chart | eye_chart | dental_chart | doc | image | video | certificate | misc
            $table->string('title')->nullable();
            $table->string('path')->nullable();
            $table->text('body')->nullable();       // free-text note / chart description
            $table->json('annotations')->nullable(); // drawing overlay data
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('patient_transfers');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('client_addresses');
        Schema::dropIfExists('clients');
    }
};
