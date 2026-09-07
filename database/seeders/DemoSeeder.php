<?php

namespace Database\Seeders;

use App\Models\AppointmentStatus;
use App\Models\Breed;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Colour;
use App\Models\JobPosition;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\Species;
use App\Models\State;
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
        $this->clientsAndPatients();
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
