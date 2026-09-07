<?php

namespace Database\Seeders;

use App\Models\AccountNameReference;
use App\Models\AppointmentLabel;
use App\Models\AppointmentReason;
use App\Models\AppointmentStatus;
use App\Models\Breed;
use App\Models\CardType;
use App\Models\Colour;
use App\Models\CompanySetting;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\Location;
use App\Models\NationalHoliday;
use App\Models\PatientReminderType;
use App\Models\Referral;
use App\Models\RegimeType;
use App\Models\Species;
use App\Models\State;
use App\Models\SuburbPostcode;
use App\Models\TaskStatus;
use App\Models\Title;
use Illuminate\Database\Seeder;

/**
 * Populates Setup > Referential Data from the Clinic-Ware manual's sample lists,
 * adapted for a Philippine practice.
 */
class ReferentialDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['MR', 'MRS', 'MS', 'MISS', 'DR', 'ENGR', 'ATTY', 'PROF'] as $n) {
            Title::firstOrCreate(['name' => $n]);
        }

        foreach ([
            ['code' => 'OK', 'name' => 'ACCOUNT OK'],
            ['code' => 'ACC', 'name' => 'ACCOUNT CLIENT'],
            ['code' => 'CASH', 'name' => 'CASH ONLY'],
            ['code' => 'WO', 'name' => 'ACCOUNT WRITTEN OFF'],
            ['code' => 'BD', 'name' => 'BAD DEBTOR'],
            ['code' => 'BDLA', 'name' => 'BAD DEBTOR / LEFT ADDRESS'],
            ['code' => 'DC', 'name' => 'AT DEBT COLLECTOR'],
            ['code' => 'AH', 'name' => 'AFTER HOURS'],
        ] as $row) {
            AccountNameReference::firstOrCreate(['name' => $row['name']], $row);
        }

        foreach ([
            'Existing Client', 'Another Client', 'Another Vet', 'Friend', 'Walk-in',
            'Facebook', 'Google Search', 'After Hours Clinic', 'Barangay Referral',
        ] as $n) {
            Referral::firstOrCreate(['name' => $n]);
        }

        $states = [
            'Metro Manila', 'Cavite', 'Laguna', 'Batangas', 'Rizal', 'Bulacan',
            'Pampanga', 'Cebu', 'Davao del Sur', 'Iloilo',
        ];
        foreach ($states as $n) {
            State::firstOrCreate(['name' => $n]);
        }

        $mm = State::where('name', 'Metro Manila')->first();
        foreach ([
            ['Quezon City', '1100'], ['Makati', '1200'], ['Manila', '1000'],
            ['Pasig', '1600'], ['Taguig', '1630'], ['Parañaque', '1700'],
            ['Mandaluyong', '1550'], ['Marikina', '1800'],
        ] as [$suburb, $pc]) {
            SuburbPostcode::firstOrCreate(
                ['suburb' => $suburb, 'state_id' => $mm?->id],
                ['postcode' => $pc],
            );
        }

        foreach (['CASH', 'MASTERCARD', 'VISA', 'GCASH', 'MAYA', 'BANK TRANSFER'] as $n) {
            CardType::firstOrCreate(['name' => $n]);
        }

        $speciesBreeds = [
            'CANINE' => ['Small and Large Animal', ['Aspin', 'Shih Tzu', 'Labrador Retriever', 'Golden Retriever', 'Pomeranian', 'Beagle', 'Poodle', 'Chihuahua', 'Siberian Husky', 'Dachshund', 'Pit Bull', 'German Shepherd', 'Rottweiler', 'Bulldog']],
            'FELINE' => ['Small Animal', ['Puspin', 'Persian', 'Siamese', 'American Shorthair', 'Maine Coon', 'British Shorthair', 'Ragdoll', 'Bengal']],
            'AVIAN' => ['Small Animal', ['Lovebird', 'Cockatiel', 'African Grey', 'Budgerigar', 'Philippine Hanging Parrot', 'Chicken', 'Duck']],
            'RABBIT' => ['Small Animal', ['New Zealand White', 'Netherland Dwarf', 'Lionhead', 'Flemish Giant']],
            'EQUINE' => ['Large Animal', ['Philippine Native Horse', 'Thoroughbred', 'Arabian']],
            'BOVINE' => ['Large Animal', ['Carabao', 'Brahman', 'Holstein']],
            'CAPRINE' => ['Large Animal', ['Native Goat', 'Boer', 'Anglo-Nubian']],
            'EXOTIC' => ['Small Animal', ['Hamster', 'Guinea Pig', 'Bearded Dragon', 'Ball Python', 'Sugar Glider', 'Turtle']],
        ];
        foreach ($speciesBreeds as $sp => [$size, $breeds]) {
            $species = Species::firstOrCreate(['name' => $sp], ['size' => $size]);
            foreach ($breeds as $b) {
                Breed::firstOrCreate(['species_id' => $species->id, 'name' => $b]);
            }
        }

        foreach ([
            'Black', 'White', 'Brown', 'Tan', 'Golden', 'Grey', 'Brindle', 'Black & White',
            'Brown & White', 'Tricolour', 'Tabby', 'Orange', 'Cream', 'Fawn', 'Blue',
        ] as $n) {
            Colour::firstOrCreate(['name' => $n]);
        }

        $groups = [
            ['Consultation', 'service', false],
            ['Vaccinations', 'both', true],
            ['Desexing', 'both', true],
            ['Microchipping', 'both', true],
            ['Euthanasia', 'both', true],
            ['Surgery', 'service', false],
            ['Dental', 'service', false],
            ['Laboratory', 'service', false],
            ['Imaging', 'service', false],
            ['Hospitalisation', 'service', false],
            ['Grooming', 'service', false],
            ['Drugs', 'product', false],
            ['Consumables', 'product', false],
            ['Food', 'product', false],
            ['Merchandise', 'product', false],
            ['Account Fees', 'service', false],
            ['Discounts', 'service', false],
        ];
        foreach ($groups as [$name, $applies, $protected]) {
            Group::firstOrCreate(['name' => $name], [
                'applies_to' => $applies,
                'is_protected' => $protected,
            ]);
        }

        foreach ([
            ['1 Tablet Daily', 1], ['1 Tablet Daily With Food', 1], ['1 Tablet Twice Daily', 2],
            ['1 Tablet Twice Daily With Food', 2], ['½ Tablet Once Daily', 0.5],
            ['½ Tablet Twice Daily', 1], ['1 Tablet now and 1 in 2 weeks', 2],
            ['2 Tablets Daily', 2], ['1 Sachet Once Daily', 1], ['2 Scoops Twice Daily', 4],
            ['Apply to affected area twice daily', 1], ['1 drop each eye twice daily', 1],
        ] as [$name, $qty]) {
            RegimeType::firstOrCreate(['name' => $name], ['total_qty_used' => $qty]);
        }

        foreach ([
            ['Vaccination', 'vaccination', 1, 0, 0],
            ['C5 Booster', 'vaccination', 1, 0, 0],
            ['Rabies Booster', 'vaccination', 1, 0, 0],
            ['Feline 3-in-1 Booster', 'vaccination', 1, 0, 0],
            ['Neutering / Desexing', 'desexing', 0, 0, 0],
            ['Dental Check', 'other', 1, 0, 0],
            ['Worming', 'other', 0, 3, 0],
            ['Heartworm Prevention', 'other', 0, 1, 0],
            ['Flea & Tick Treatment', 'other', 0, 1, 0],
            ['Geriatric Check-up', 'other', 0, 6, 0],
            ['Grooming', 'other', 0, 1, 15],
            ['Cartrophen Course', 'other', 0, 6, 0],
        ] as [$name, $cat, $y, $m, $d]) {
            PatientReminderType::firstOrCreate(['name' => $name], [
                'category' => $cat,
                'period_years' => $y,
                'period_months' => $m,
                'period_days' => $d,
            ]);
        }

        foreach (['DOCTOR', 'NURSE', 'RECEPTIONIST', 'CLERK', 'GROOMER', 'PRACTICE MANAGER'] as $n) {
            JobPosition::firstOrCreate(['name' => $n]);
        }

        foreach ([
            ['Consult Room 1', 'General consultation room'],
            ['Consult Room 2', 'General consultation room'],
            ['Surgery', 'Operating theatre'],
            ['Dental Suite', 'Dental procedures'],
            ['Treatment Area', 'Nurse treatments and procedures'],
            ['Isolation', 'Infectious / quarantine patients'],
            ['Hospital Ward', 'Inpatient hospitalisation'],
            ['Waiting Room', 'Reception waiting area'],
            ['Grooming Room', 'Grooming and bathing'],
            ['Home / Farm Visit', 'Off-site visit'],
        ] as [$name, $desc]) {
            Location::firstOrCreate(['name' => $name], ['description' => $desc]);
        }

        $year = now()->year;
        foreach ([
            ["New Year's Day", "$year-01-01", 'Regular holiday'],
            ['Araw ng Kagitingan', "$year-04-09", 'Regular holiday'],
            ['Labor Day', "$year-05-01", 'Regular holiday'],
            ['Independence Day', "$year-06-12", 'Regular holiday'],
            ['National Heroes Day', "$year-08-25", 'Regular holiday'],
            ['Bonifacio Day', "$year-11-30", 'Regular holiday'],
            ['Christmas Day', "$year-12-25", 'Regular holiday — clinic closed'],
            ['Rizal Day', "$year-12-30", 'Regular holiday'],
        ] as [$name, $date, $desc]) {
            NationalHoliday::firstOrCreate(['name' => $name, 'holiday_date' => $date], ['description' => $desc]);
        }

        foreach ([
            ['Very Important', '#ef4444'],
            ['Important', '#f97316'],
            ['Must Attend', '#3b82f6'],
            ['Phone Call', '#0ea5e9'],
            ['Follow-up', '#14b8a6'],
            ['Personal', '#a3a3a3'],
            ['Business', '#f59e0b'],
            ['Not Available', '#78716c'],
            ['Vacation', '#fcd34d'],
            ['Group Appointment', '#d97706'],
        ] as [$name, $color]) {
            AppointmentLabel::firstOrCreate(['name' => $name], [
                'menu_caption' => $name,
                'color' => $color,
            ]);
        }

        foreach ([
            ['V1', '1st Vaccination'],
            ['V2', '2nd Vaccination'],
            ['VB', 'Vaccination Booster'],
            ['DESEX', 'Desexing / Spey / Castrate'],
            ['DENTAL', 'Dental Scale & Polish'],
            ['CONS', 'General Consultation'],
            ['RECHK', 'Recheck / Follow-up'],
            ['SICK', 'Sick Animal'],
            ['SURG', 'Surgery'],
            ['MICRO', 'Microchipping'],
            ['GROOM', 'Grooming'],
            ['EUTH', 'Euthanasia'],
            ['LAB', 'Laboratory / Blood Test'],
            ['XRAY', 'X-Ray / Imaging'],
        ] as [$code, $reason]) {
            AppointmentReason::firstOrCreate(['reason' => $reason], ['code' => $code]);
        }

        foreach ([
            ['Tentative', '#94a3b8', false],
            ['Confirmed', '#22c55e', true],
            ['Checked In', '#0ea5e9', false],
            ['In Consult', '#8b5cf6', false],
            ['Completed', '#64748b', false],
            ['Cancelled', '#ef4444', false],
            ['No Show', '#b91c1c', false],
        ] as [$name, $color, $default]) {
            AppointmentStatus::firstOrCreate(['name' => $name], [
                'menu_caption' => $name,
                'color' => $color,
                'is_default' => $default,
            ]);
        }

        foreach (['Not Started', 'In Progress', 'Completed', 'Deferred'] as $n) {
            TaskStatus::firstOrCreate(['name' => $n]);
        }

        CompanySetting::current()->update([
            'company_name' => 'Wecko Vet Clinic',
            'address' => "123 Katipunan Avenue\nQuezon City, Metro Manila 1108",
            'email' => 'reception@weckovet.ph',
            'phone' => '(02) 8123 4567',
            'website' => 'https://weckovet.ph',
            'tin' => '009-123-456-000',
            'default_history' => 'Presenting complaint: ',
            'default_examination' => "BAR. MM pink, CRT <2s. HR __ RR __ T __°C.\nHydration adequate. LN normal.",
            'default_tests' => '',
            'default_differential_diagnosis' => '',
            'default_consult_diagnosis' => '',
            'coin_denominations' => [1000, 500, 200, 100, 50, 20, 10, 5, 1],
        ]);
    }
}
