<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setup > Referential Data — the "engine room" lists that populate drop-downs
 * throughout Wicko. Every table carries `is_active` so a value can be retired
 * without breaking historical records that still reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Client-facing lists -------------------------------------------------
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // MR, MRS, MS, DR
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('account_name_references', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');                     // ACCOUNT OK, BAD DEBTOR, AT DEBT COLLECTOR
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Friend, Another Vet, Existing Client
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // province / region
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suburb_postcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->string('suburb');                   // city / municipality / barangay
            $table->string('postcode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('card_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // MASTERCARD, VISA
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // --- Patient lists -----------------------------------------------------
        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // CANINE, FELINE, AVIAN, EQUINE
            $table->string('size')->nullable();        // Small / Large / Small and Large Animal
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['species_id', 'name']);
        });

        Schema::create('colours', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // BLACK, BAY & WHITE, TABBY
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // --- Product / service classification --------------------------------
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // DRUGS, SURGERY, DENTAL, FOOD, MERCHANDISING
            $table->string('applies_to')->default('both'); // product | service | both
            $table->boolean('is_protected')->default(false); // Desexing/Euthanasia/Microchipping/Vaccinations
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('regime_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // 1 TABLET DAILY WITH FOOD
            $table->decimal('total_qty_used', 8, 2)->default(1); // dosage multiplier for auto qty
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patient_reminder_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Vaccination, Neutering, Dental, Worming
            $table->string('category')->default('other'); // vaccination | desexing | other
            $table->unsignedInteger('period_years')->default(0);
            $table->unsignedInteger('period_months')->default(0);
            $table->unsignedInteger('period_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // --- Staffing --------------------------------------------------------
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // DOCTOR, NURSE, CLERK
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('job_position_id')->references('id')->on('job_positions')->nullOnDelete();
        });

        Schema::create('leave_vacation_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the provider
            $table->string('type')->default('leave');  // leave | vacation | break
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // --- Calendar lists -------------------------------------------------
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // Consult Room 1, Surgery, Isolation, Waiting Room
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('national_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Very Important, Phone Call, Vacation
            $table->string('menu_caption')->nullable();
            $table->string('color')->default('#64748b'); // hex, shown on calendar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('reason');                   // 1ST VACC, DESEX, DENTAL — carried into consults
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Tentative, Confirmed, Checked In, Completed, Cancelled
            $table->string('menu_caption')->nullable();
            $table->string('color')->default('#64748b');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // Not Started, In Progress, Completed, Deferred
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['job_position_id']);
        });

        foreach ([
            'task_statuses', 'appointment_statuses', 'appointment_reasons', 'appointment_labels',
            'national_holidays', 'locations', 'leave_vacation_breaks', 'job_positions',
            'patient_reminder_types', 'regime_types', 'groups', 'colours', 'breeds', 'species',
            'card_types', 'suburb_postcodes', 'states', 'referrals', 'account_name_references', 'titles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
