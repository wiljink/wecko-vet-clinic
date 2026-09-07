<?php

namespace Database\Seeders;

use App\Models\AppointmentStatus;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Colour;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\Patient;
use App\Models\PatientReminderType;
use App\Models\Product;
use App\Models\RegimeType;
use App\Models\Referral;
use App\Models\Species;
use App\Models\State;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SuburbPostcode;
use App\Models\Title;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demonstration data — a Filipino clinic with staff, clients and patients.
 * Extended per build phase (appointments, consults, invoices, payments).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->staff();
        $this->inventory();
        $this->clientsAndPatients();
        $this->calendar();
        $this->consultations();
        $this->counterSales();
    }

    private function counterSales(): void
    {
        if (\App\Models\CounterSale::count() > 0) {
            return;
        }

        $otc = Product::sellable('otc')->get();
        if ($otc->isEmpty()) {
            return;
        }

        $clients = Client::where('is_active', true)->pluck('id')->all();
        $providers = User::where('is_provider', true)->pluck('id')->all();

        foreach (range(1, 18) as $n) {
            $walkIn = fake()->boolean(55);
            $sale = \App\Models\CounterSale::create([
                'walk_in' => $walkIn,
                'walk_in_name' => $walkIn ? fake()->name() : null,
                'client_id' => $walkIn ? null : fake()->randomElement($clients),
                'provider_id' => fake()->randomElement($providers),
                'sale_date' => fake()->dateTimeBetween('-2 months', 'now'),
            ]);

            foreach (range(1, random_int(1, 3)) as $l) {
                $p = $otc->random();
                $sale->items()->create([
                    'kind' => 'product',
                    'product_id' => $p->id,
                    'description' => $p->name,
                    'qty' => random_int(1, 3),
                    'unit_price_ex_tax' => $p->sell_price_ex_tax,
                    'tax_rate' => $p->tax_rate,
                    'dispensing_fee' => $p->dispense_fee_always ? $p->dispense_fee : 0,
                ]);
            }

            $sale->refresh();

            if (! $walkIn && fake()->boolean(30)) {
                $sale->complete(null); // on account
            } else {
                $pay = ['payment_type' => fake()->randomElement(['cash', 'cash', 'credit_card', 'eftpos'])];
                if ($pay['payment_type'] === 'cash') {
                    $pay['cash_received'] = ceil((float) $sale->total_inc_tax / 100) * 100;
                }
                $sale->complete($pay);
            }
        }
    }

    private function consultations(): void
    {
        if (\App\Models\Consultation::count() > 0) {
            return;
        }

        // Standard consult templates
        $consultService = Product::where('name', 'General Consultation')->first();
        $c5 = Product::where('name', 'like', '5-in-1%')->first();
        $rabies = Product::where('name', 'like', 'Anti-Rabies%')->first();

        if ($consultService && $c5 && $rabies) {
            $std = \App\Models\StandardConsult::create([
                'name' => 'Annual Vaccination — Canine',
                'appointment_reason_id' => \App\Models\AppointmentReason::where('reason', 'like', '%Booster%')->value('id'),
            ]);
            $std->items()->createMany([
                ['product_id' => $consultService->id, 'kind' => 'service', 'qty' => 1],
                ['product_id' => $c5->id, 'kind' => 'vaccination', 'qty' => 1],
                ['product_id' => $rabies->id, 'kind' => 'vaccination', 'qty' => 1],
            ]);
        }

        $providers = User::where('is_provider', true)->pluck('id')->all();
        $services = Product::kind('service')->get();
        $drugs = Product::kind('product')->where('group_id', \App\Models\Group::where('name', 'Drugs')->value('id'))->get();
        $vaccines = Product::kind('vaccine')->get();
        $reasons = \App\Models\AppointmentReason::pluck('id')->all();

        Patient::with('client')->inRandomOrder()->take(35)->get()->each(function (Patient $patient) use ($providers, $services, $drugs, $vaccines, $reasons) {
            $date = \Illuminate\Support\Carbon::instance(fake()->dateTimeBetween('-4 months', '-2 days'))
                ->setTime(fake()->numberBetween(9, 16), fake()->randomElement([0, 30]));

            $consult = \App\Models\Consultation::create([
                'client_id' => $patient->client_id,
                'patient_id' => $patient->id,
                'provider_id' => fake()->randomElement($providers),
                'consult_date' => $date,
                'appointment_reason_id' => fake()->randomElement($reasons),
                'weight' => $patient->weight,
                'temperature' => fake()->randomFloat(1, 37.5, 39.5),
                'consult_diagnosis' => fake()->randomElement([
                    'Healthy — routine visit', 'Otitis externa', 'Gastroenteritis', 'Skin allergy / atopy',
                    'Dental disease grade 2', 'Wound — left hind limb', 'Upper respiratory infection',
                ]),
            ]);

            $consult->items()->create(\App\Filament\Resources\ConsultationResource::lineFromProduct(
                $services->firstWhere('name', 'General Consultation') ?? $services->random(), 'service', 1
            ));

            if (fake()->boolean(55) && $drugs->isNotEmpty()) {
                $drug = $drugs->random();
                $consult->items()->create(\App\Filament\Resources\ConsultationResource::lineFromProduct($drug, 'drug', fake()->numberBetween(7, 21)));
            }

            if (fake()->boolean(35) && $vaccines->isNotEmpty()) {
                $consult->items()->create(\App\Filament\Resources\ConsultationResource::lineFromProduct($vaccines->random(), 'vaccination', 1));
            }

            // Finalize most of them.
            if (fake()->boolean(80)) {
                $consult->finalize();
            } else {
                $consult->update(['status' => fake()->randomElement(['open', 'closed'])]);
            }
        });
    }

    private function calendar(): void
    {
        if (\App\Models\Appointment::count() > 0) {
            return;
        }

        $providers = User::where('is_provider', true)->pluck('id')->all();
        $locations = \App\Models\Location::pluck('id')->all();
        $reasons = \App\Models\AppointmentReason::pluck('id')->all();
        $statuses = \App\Models\AppointmentStatus::pluck('id', 'name');
        $labels = \App\Models\AppointmentLabel::pluck('id')->all();
        $notStarted = \App\Models\TaskStatus::where('name', 'Not Started')->value('id');

        Patient::with('client')->inRandomOrder()->take(45)->get()->each(function (Patient $patient) use ($providers, $locations, $reasons, $statuses, $labels) {
            $start = fake()->boolean(65)
                ? fake()->dateTimeBetween('now', '+3 weeks')
                : fake()->dateTimeBetween('-3 weeks', 'now');
            $start = \Illuminate\Support\Carbon::instance($start)->setTime(fake()->numberBetween(8, 16), fake()->randomElement([0, 15, 30, 45]));
            $duration = fake()->randomElement([15, 15, 30, 30, 45]);

            $status = $start->isPast()
                ? $statuses->only(['Completed', 'No Show', 'Cancelled'])->random()
                : $statuses->only(['Tentative', 'Confirmed', 'Confirmed'])->random();

            \App\Models\Appointment::create([
                'client_id' => $patient->client_id,
                'patient_id' => $patient->id,
                'provider_id' => fake()->randomElement($providers),
                'location_id' => fake()->randomElement($locations),
                'starts_at' => $start,
                'ends_at' => (clone $start)->addMinutes($duration),
                'duration_minutes' => $duration,
                'appointment_reason_id' => fake()->randomElement($reasons),
                'appointment_status_id' => $status,
                'appointment_label_id' => fake()->boolean(30) ? fake()->randomElement($labels) : null,
            ]);
        });

        foreach ([
            ['Call Mrs Santos re: lab results', 'Lab follow-up'],
            ['Order more 5-in-1 vaccine — running low', 'Order supplies'],
            ['Autoclave annual service due', 'Equipment service'],
            ['Recall overdue vaccination patients', 'Recall patient'],
            ['Restock consult room 2', 'Admin'],
        ] as [$title, $type]) {
            \App\Models\Task::create([
                'title' => $title,
                'task_type' => $type,
                'provider_id' => fake()->randomElement($providers),
                'task_status_id' => $notStarted,
                'due_on' => fake()->dateTimeBetween('now', '+2 weeks'),
            ]);
        }
    }

    private function inventory(): void
    {
        if (Product::count() > 0) {
            return;
        }

        $suppliers = collect([
            'Zoetis Philippines', 'MSD Animal Health', 'Boehringer Ingelheim',
            'Virbac Philippines', 'CENVET Distribution', 'Royal Canin PH',
        ])->map(fn ($n) => Supplier::create([
            'name' => $n,
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('(02) 8### ####'),
        ]));

        $g = fn (string $name) => Group::firstWhere('name', $name)?->id;
        $regimes = RegimeType::pluck('id', 'name');
        $vaccReminder = PatientReminderType::firstWhere('name', 'Vaccination')?->id;

        $services = [
            ['General Consultation', 'Consultation', 650],
            ['Follow-up Consultation', 'Consultation', 400],
            ['Emergency Consultation', 'Consultation', 1200],
            ['Spay — Canine (small)', 'Desexing', 4500],
            ['Castration — Canine', 'Desexing', 3200],
            ['Spay — Feline', 'Desexing', 2800],
            ['Dental Scale & Polish', 'Dental', 3500],
            ['Microchip Implant', 'Microchipping', 950],
            ['Complete Blood Count', 'Laboratory', 850],
            ['Blood Chemistry Panel', 'Laboratory', 1800],
            ['Digital X-Ray (1 view)', 'Imaging', 1500],
            ['Ultrasound — Abdomen', 'Imaging', 2200],
            ['Hospitalisation — per day', 'Hospitalisation', 900],
            ['Euthanasia', 'Euthanasia', 1500],
            ['Full Groom — Dog (medium)', 'Grooming', 800],
            ['Nail Trim', 'Grooming', 150],
        ];
        foreach ($services as [$name, $group, $price]) {
            Product::create([
                'kind' => 'service', 'name' => $name, 'group_id' => $g($group),
                'sell_price_ex_tax' => $price, 'tax_rate' => 12, 'list_this_product' => 'both',
            ]);
        }

        $vaccines = [
            ['5-in-1 (DHPPi+L) — Canine', 12, 'Canine Distemper, Hepatitis, Parvovirus, Parainfluenza & Leptospirosis', 'Zoetis Philippines'],
            ['Anti-Rabies — Canine/Feline', 12, 'Rabies', 'MSD Animal Health'],
            ['4-in-1 (FVRCP+FeLV) — Feline', 12, 'Feline Rhinotracheitis, Calicivirus, Panleucopenia & Leukemia', 'Boehringer Ingelheim'],
            ['Kennel Cough (Bronchi-Shield)', 12, 'Bordetella bronchiseptica', 'Zoetis Philippines'],
        ];
        foreach ($vaccines as [$name, $months, $protection, $sup]) {
            Product::create([
                'kind' => 'vaccine', 'name' => $name, 'group_id' => $g('Vaccinations'),
                'supplier_id' => $suppliers->firstWhere('name', $sup)?->id,
                'unit_cost_ex_tax' => fake()->randomFloat(2, 120, 380),
                'sell_price_ex_tax' => fake()->randomFloat(0, 550, 1100),
                'tax_rate' => 12, 'pack_qty' => 10, 'reorder_level' => 15, 'max_holding' => 60,
                'has_expiry' => true, 'protection' => $protection,
                'booster_months' => $months, 'prints_certificate' => true,
                'patient_reminder_type_id' => $vaccReminder,
                'dispense_fee' => 0, 'list_this_product' => 'consult',
            ]);
        }

        $products = [
            ['Amoxicillin 250mg Tablet', 'Drugs', 8, 25, '1 Tablet Twice Daily With Food'],
            ['Metronidazole 250mg Tablet', 'Drugs', 6, 20, '1 Tablet Twice Daily'],
            ['Carprofen 50mg Tablet', 'Drugs', 15, 42, '1 Tablet Daily With Food'],
            ['Meloxicam Oral Suspension 15ml', 'Drugs', 180, 520, '½ Tablet Once Daily'],
            ['Apoquel 5.4mg Tablet', 'Drugs', 45, 120, '1 Tablet Twice Daily'],
            ['Ivermectin Injection 50ml', 'Drugs', 220, 620, null],
            ['NexGard Chewable (M)', 'Drugs', 320, 780, '1 Tablet Daily'],
            ['Drontal Plus Tablet', 'Drugs', 60, 165, null],
            ['Advocate Spot-On Dog (M)', 'Drugs', 280, 690, null],
            ['Disposable Syringe 3ml', 'Consumables', 4, 12, null],
            ['Hypodermic Needle 23G', 'Consumables', 2, 6, null],
            ['Surgical Gloves (pair)', 'Consumables', 12, 30, null],
            ['Elizabethan Collar (M)', 'Consumables', 90, 240, null],
            ['Hills Science Diet Adult 3kg', 'Food', 780, 1450, null],
            ['Royal Canin Kitten 2kg', 'Food', 690, 1290, null],
        ];
        foreach ($products as [$name, $group, $cost, $price, $regime]) {
            $p = Product::create([
                'kind' => 'product', 'name' => $name, 'group_id' => $g($group),
                'supplier_id' => $suppliers->random()->id,
                'unit_cost_ex_tax' => $cost, 'sell_price_ex_tax' => $price, 'tax_rate' => 12,
                'pack_qty' => in_array($group, ['Drugs', 'Consumables']) ? 100 : 1,
                'reorder_level' => fake()->randomElement([10, 20, 30]),
                'max_holding' => fake()->randomElement([80, 120, 200]),
                'has_expiry' => $group === 'Drugs',
                'dispense_fee' => $group === 'Drugs' ? 50 : 0,
                'dispense_fee_always' => $group === 'Drugs',
                'regime_id' => $regime ? $regimes[$regime] ?? null : null,
                'list_this_product' => 'both',
                'print_label' => $group === 'Drugs',
            ]);

            // Opening stock.
            StockMovement::record($p, 'opening', fake()->numberBetween(20, 150), [
                'reason' => 'Opening balance',
                'unit_cost_ex_tax' => $cost,
                'moved_at' => now()->subMonths(2),
            ]);
        }

        // A posted stock receipt brings the vaccines onto the shelf.
        $receipt = \App\Models\StockReceipt::create([
            'supplier_id' => $suppliers->firstWhere('name', 'Zoetis Philippines')->id,
            'received_date' => now()->subWeeks(3)->toDateString(),
            'supplier_doc_no' => 'ZP-'.fake()->numerify('#####'),
        ]);
        foreach (Product::kind('vaccine')->get() as $vaccine) {
            $receipt->items()->create([
                'product_id' => $vaccine->id,
                'qty' => 40,
                'unit_cost_ex_tax' => $vaccine->unit_cost_ex_tax,
                'sell_price_ex_tax' => $vaccine->sell_price_ex_tax,
                'batch_no' => strtoupper(fake()->bothify('??##??')),
                'expiry_on' => now()->addYear()->toDateString(),
            ]);
        }
        $receipt->post();
    }

    private function staff(): void
    {
        $doctor = JobPosition::firstWhere('name', 'DOCTOR');
        $nurse = JobPosition::firstWhere('name', 'NURSE');
        $recept = JobPosition::firstWhere('name', 'RECEPTIONIST');

        $people = [
            ['Dr. Maria Santos', 'maria@wecko.test', 'veterinarian', $doctor, true, 'PRC-VET-0093214'],
            ['Dr. Jose Reyes', 'jose@wecko.test', 'veterinarian', $doctor, true, 'PRC-VET-0101887'],
            ['Dr. Andrea Lim', 'andrea@wecko.test', 'veterinarian', $doctor, true, 'PRC-VET-0112456'],
            ['Grace Bautista', 'grace@wecko.test', 'nurse', $nurse, true, null],
            ['Emmanuel Cruz', 'emman@wecko.test', 'nurse', $nurse, true, null],
            ['Rowena Flores', 'rowena@wecko.test', 'receptionist', $recept, false, null],
        ];

        foreach ($people as [$name, $email, $role, $position, $isProvider, $licence]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => bcrypt('password'),
                'job_position_id' => $position?->id,
                'licence_no' => $licence,
                'is_provider' => $isProvider,
                'can_login' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);
        }
    }

    private function clientsAndPatients(): void
    {
        if (Client::count() > 0) {
            return;
        }

        $titles = Title::pluck('id', 'name');
        $referrals = Referral::pluck('id')->all();
        $mm = State::firstWhere('name', 'Metro Manila');
        $suburbs = SuburbPostcode::where('state_id', $mm?->id)->get();
        $speciesList = Species::with('breeds')->whereIn('name', ['CANINE', 'FELINE', 'AVIAN', 'RABBIT'])->get();
        $colours = Colour::pluck('id')->all();

        DB::transaction(function () use ($titles, $referrals, $mm, $suburbs, $speciesList, $colours) {
            Client::factory()->count(30)->make()->each(function (Client $client) use ($titles, $referrals, $mm, $suburbs, $speciesList, $colours) {
                $faker = fake();
                $client->fill([
                    'title_id' => $titles->random(),
                    'referral_id' => $referrals ? $faker->randomElement($referrals) : null,
                    'account_opened_on' => $faker->dateTimeBetween('-6 years', '-1 month'),
                    'discount_pct' => $faker->randomElement([0, 0, 0, 5, 10]),
                ])->save();

                $suburb = $suburbs->random();
                ClientAddress::create([
                    'client_id' => $client->id,
                    'label' => 'Home',
                    'line1' => $faker->buildingNumber().' '.$faker->streetName(),
                    'suburb' => $suburb->suburb,
                    'postcode' => $suburb->postcode,
                    'state_id' => $mm?->id,
                    'is_primary' => true,
                ]);

                foreach (range(1, random_int(1, 3)) as $i) {
                    $species = $speciesList->random();
                    $breed = $species->breeds->isNotEmpty() ? $species->breeds->random() : null;
                    $birth = $faker->dateTimeBetween('-14 years', '-4 months');
                    Patient::create([
                        'client_id' => $client->id,
                        'name' => $faker->petName(),
                        'microchip_no' => $faker->boolean(60) ? $faker->numerify('9800#########') : null,
                        'species_id' => $species->id,
                        'breed_id' => $breed?->id,
                        'colour_id' => $colours ? $faker->randomElement($colours) : null,
                        'gender' => $faker->randomElement(['female', 'male']),
                        'birth_date' => $birth,
                        'neuter_status' => $faker->randomElement(['neutered', 'neutered', 'not_neutered', 'owner_declines']),
                        'date_neutered' => $faker->boolean(60) ? $faker->dateTimeBetween($birth, 'now') : null,
                        'weight' => $faker->randomFloat(1, 0.4, 38),
                        'temperament' => $faker->randomElement(['Friendly', 'Nervous', 'Calm', 'Excitable', null]),
                        'diet' => $faker->randomElement(['Kibble', 'Raw', 'Home-cooked', 'Prescription diet', null]),
                        'first_visit_on' => $faker->dateTimeBetween('-3 years', '-1 month'),
                        'last_visit_on' => $faker->dateTimeBetween('-3 months', 'now'),
                    ]);
                }
            });
        });

        // Ensure the demo statuses exist for later phases.
        AppointmentStatus::firstWhere('is_default', true) ?? AppointmentStatus::query()->first()?->update(['is_default' => true]);
    }
}
