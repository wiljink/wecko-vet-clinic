<?php

namespace Database\Seeders;

use App\Models\AppointmentStatus;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Colour;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\Location;
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
    /** The two demo branches, keyed by name — id 0 is always the main branch. */
    private array $branches = [];

    public function run(): void
    {
        $this->branches();
        $this->staff();
        $this->inventory();
        $this->clientsAndPatients();
        $this->calendar();
        $this->consultations();
        $this->counterSales();
        $this->financials();
        $this->reminders();
    }

    /** Three branches (Zamboanga Peninsula / Northern Mindanao) so the multi-branch dashboard and stock features have real data to show. */
    private function branches(): void
    {
        $main = Location::firstOrCreate(
            ['name' => 'Wicko Vet Clinic Main'],
            [
                'type' => Location::TYPE_BRANCH,
                'code' => 'OZC', 'address' => 'Don Anceto Peña St, Ozamis City, Misamis Occidental',
                'phone' => '(088) 521 1234', 'email' => 'ozamis@wecko.test', 'is_main' => true,
            ],
        );
        $pagadian = Location::firstOrCreate(
            ['name' => 'Wicko Vet Clinic Pagadian'],
            [
                'type' => Location::TYPE_BRANCH,
                'code' => 'PGD', 'address' => 'Rizal Ave, Pagadian City, Zamboanga del Sur',
                'phone' => '(062) 214 5678', 'email' => 'pagadian@wecko.test',
            ],
        );
        $iligan = Location::firstOrCreate(
            ['name' => 'Wicko Vet Clinic Iligan'],
            [
                'type' => Location::TYPE_BRANCH,
                'code' => 'ILG', 'address' => 'Quezon Ave, Iligan City, Lanao del Norte',
                'phone' => '(063) 221 9876', 'email' => 'iligan@wecko.test',
            ],
        );

        $this->branches = [$main->id, $pagadian->id, $iligan->id];
    }

    private function reminders(): void
    {
        // Pull a handful of vaccination boosters back so some reminders are due now.
        \App\Models\Vaccination::inRandomOrder()->take(4)->get()->each(function (\App\Models\Vaccination $v) {
            $v->update(['booster_due_on' => now()->subDays(random_int(3, 40))]);
            \App\Models\Reminder::where('vaccination_id', $v->id)->update(['due_on' => $v->booster_due_on]);
        });

        \Illuminate\Support\Facades\Artisan::call('reminders:generate');

        // A few phone reminders.
        \App\Models\Patient::has('consultations')->inRandomOrder()->take(3)->get()->each(fn (Patient $p) => \App\Models\Reminder::create([
            'remindable_type' => $p->getMorphClass(), 'remindable_id' => $p->id,
            'client_id' => $p->client_id, 'patient_id' => $p->id,
            'category' => 'phone', 'due_on' => now()->addDays(random_int(1, 5)),
            'notes' => "Follow up on {$p->name}'s recovery",
        ]));

        \App\Models\MarketingCampaign::create([
            'name' => 'Rainy-season parasite check 2026',
            'channels' => ['email'],
            'document_template_id' => \App\Models\DocumentTemplate::where('type', 'marketing')->value('id'),
            'filter' => ['has_email' => true],
        ]);
    }

    private function financials(): void
    {
        if (\App\Models\Payment::where('reference', 'like', 'Account payment%')->exists()) {
            return;
        }

        // Pay off roughly two-thirds of outstanding consult invoices.
        \App\Models\Invoice::outstanding()->inRandomOrder()->take(24)->get()->each(function (\App\Models\Invoice $invoice) {
            $full = fake()->boolean(70);
            $amount = $full ? (float) $invoice->balance : round((float) $invoice->balance * fake()->randomFloat(2, 0.3, 0.8), 2);

            $payment = \App\Models\Payment::create([
                'client_id' => $invoice->client_id,
                'location_id' => $invoice->location_id,
                'payment_type' => fake()->randomElement(['cash', 'cash', 'eftpos', 'credit_card', 'cheque']),
                'amount' => $amount,
                'reference' => 'Account payment',
                'received_at' => fake()->dateTimeBetween($invoice->invoice_date, 'now'),
            ]);
            $payment->allocateTo([$invoice]);
        });

        // A couple of goodwill / write-off adjustments.
        \App\Models\Client::has('invoices')->inRandomOrder()->take(4)->get()->each(function (\App\Models\Client $client) {
            $client->accountAdjustments()->create([
                'location_id' => $client->invoices()->latest('invoice_date')->value('location_id'),
                'direction' => fake()->randomElement(['credit', 'credit', 'debit']),
                'reason' => fake()->randomElement(['Goodwill discount', 'Loyalty credit', 'Clinic-Ware conversion balance', 'Interest on overdue account']),
                'amount' => fake()->randomFloat(2, 50, 400),
                'adjusted_on' => fake()->dateTimeBetween('-2 months', 'now'),
            ]);
        });

        // Bank the older payments.
        \App\Models\BankingBatch::bankUpTo(\Illuminate\Support\Carbon::now()->subWeeks(2));

        // One closed till session for yesterday.
        $till = \App\Models\TillSession::create([
            'session_date' => now()->subDay()->toDateString(),
            'location_id' => $this->branches[0],
            'opening_float' => 2000,
            'denominations' => ['1000' => 2, '500' => 3, '100' => 8, '50' => 4, '20' => 5, '10' => 3],
        ]);
        $till->close();
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
                'location_id' => fake()->randomElement($this->branches),
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
                'location_id' => fake()->randomElement($this->branches),
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
        $rooms = \App\Models\Location::rooms()->pluck('id')->all();
        $reasons = \App\Models\AppointmentReason::pluck('id')->all();
        $statuses = \App\Models\AppointmentStatus::pluck('id', 'name');
        $labels = \App\Models\AppointmentLabel::pluck('id')->all();
        $notStarted = \App\Models\TaskStatus::where('name', 'Not Started')->value('id');

        Patient::with('client')->inRandomOrder()->take(45)->get()->each(function (Patient $patient) use ($providers, $rooms, $reasons, $statuses, $labels) {
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
                'location_id' => fake()->randomElement($this->branches),
                'room_id' => fake()->boolean(70) ? fake()->randomElement($rooms) : null,
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

            // Opening stock at the main branch, plus a smaller holding at every other branch.
            foreach ($this->branches as $i => $branchId) {
                StockMovement::record($p, 'opening', fake()->numberBetween($i === 0 ? 20 : 10, $i === 0 ? 150 : 60), [
                    'location_id' => $branchId,
                    'reason' => 'Opening balance',
                    'unit_cost_ex_tax' => $cost,
                    'moved_at' => now()->subMonths(2),
                ]);
            }
        }

        // A posted stock receipt brings the vaccines onto the shelf at the main branch.
        $receipt = \App\Models\StockReceipt::create([
            'supplier_id' => $suppliers->firstWhere('name', 'Zoetis Philippines')->id,
            'location_id' => $this->branches[0],
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

        foreach ($people as $i => [$name, $email, $role, $position, $isProvider, $licence]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => bcrypt('password'),
                'job_position_id' => $position?->id,
                'licence_no' => $licence,
                'is_provider' => $isProvider,
                'can_login' => true,
                'email_verified_at' => now(),
                'home_location_id' => $this->branches[$i % count($this->branches)],
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
