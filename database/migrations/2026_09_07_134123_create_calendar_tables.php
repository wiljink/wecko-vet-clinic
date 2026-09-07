<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 4 — The Calendar Sub-System: Appointments (with recurrence and
 * all-day events), patient Reminders (auto-generated) and practice Tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes')->default(15);
            $table->boolean('all_day')->default(false);

            $table->foreignId('appointment_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_label_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_status_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();

            // Recurrence (manual 4.1.4)
            $table->string('recurrence_freq')->nullable();       // daily | weekly | monthly | yearly | weekday
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->json('recurrence_weekdays')->nullable();
            $table->date('recurrence_until')->nullable();
            $table->unsignedSmallInteger('recurrence_count')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('appointments')->cascadeOnDelete();

            // Reminder tracking
            $table->string('reminder_channel')->nullable();      // letter | email | sms
            $table->timestamp('reminder_sent_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'provider_id']);
            $table->index('appointment_status_id');
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('remindable');               // Vaccination | Patient | Appointment
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_reminder_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('other');       // vaccination | desexing | other | phone
            $table->foreignId('vaccination_id')->nullable();
            $table->foreignId('species_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');       // pending | sent | done | cancelled
            $table->string('sent_channel')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('batch_id')->nullable();
            $table->unsignedTinyInteger('sequence')->default(1); // 1st / 2nd notice
            $table->timestamps();

            $table->index(['status', 'due_on']);
            $table->index('category');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('task_type')->nullable();            // from a standard list
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('task_status_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('appointments');
    }
};
