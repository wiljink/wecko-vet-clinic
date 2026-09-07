<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wecko security model (System Maintenance > Security).
 *
 * Every Filament resource authorises against spatie permissions named
 * "<ability>_<key>" where ability is one of view_any/view/create/update/delete
 * (see App\Filament\Concerns\HasResourcePermissions). The `principal` role
 * bypasses all checks via Gate::before (AppServiceProvider).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** Resource permission keys grouped for role assignment. */
    public const RESOURCE_KEYS = [
        // Clients & Patients
        'client', 'patient', 'patient_transfer',
        // Calendar
        'appointment', 'reminder', 'task',
        // Consultations
        'consultation', 'standard_consult', 'vaccination', 'prescription',
        // Sales / Financials
        'counter_sale', 'invoice', 'payment', 'account_adjustment', 'statement_run',
        'banking_batch', 'till_session',
        // Inventory
        'product', 'supplier', 'stock_movement', 'inventory_order', 'stock_receipt',
        'stock_take', 'inventory_adjustment', 'inventory_return',
        // Setup
        'reference_data', 'company_setting', 'document_template', 'marketing_campaign',
        // System
        'user', 'role', 'activity', 'backup',
        // Reports
        'report',
    ];

    private const ABILITIES = ['view_any', 'view', 'create', 'update', 'delete'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [];
        foreach (self::RESOURCE_KEYS as $key) {
            foreach (self::ABILITIES as $ability) {
                $permissions[] = "{$ability}_{$key}";
            }
        }
        // Extra fine-grained abilities beyond CRUD.
        $permissions = array_merge($permissions, [
            'finalize_consultation', 'reopen_consultation',
            'post_stock_take', 'post_stock_receipt',
            'process_payment', 'process_refund', 'make_account_adjustment',
            'run_statements', 'run_reminders', 'run_marketing',
        ]);

        DB::transaction(function () use ($permissions) {
            foreach ($permissions as $name) {
                Permission::findOrCreate($name, 'web');
            }
        });

        $principal = Role::findOrCreate('principal', 'web');
        $vet = Role::findOrCreate('veterinarian', 'web');
        $nurse = Role::findOrCreate('nurse', 'web');
        $receptionist = Role::findOrCreate('receptionist', 'web');

        $principal->syncPermissions(Permission::all());

        // Veterinarian: full clinical + inventory + sales, no security/backup admin.
        $vet->syncPermissions(Permission::where(function ($q) {
            foreach ([
                'client', 'patient', 'patient_transfer', 'appointment', 'reminder', 'task',
                'consultation', 'standard_consult', 'vaccination', 'prescription',
                'counter_sale', 'invoice', 'payment', 'account_adjustment',
                'product', 'supplier', 'stock_movement', 'inventory_order', 'stock_receipt',
                'stock_take', 'inventory_adjustment', 'inventory_return', 'report',
                'reference_data', 'document_template',
            ] as $key) {
                $q->orWhere('name', 'like', "%_{$key}");
            }
        })->orWhereIn('name', [
            'finalize_consultation', 'reopen_consultation', 'post_stock_take',
            'post_stock_receipt', 'process_payment', 'process_refund',
            'make_account_adjustment', 'run_statements', 'run_reminders',
        ])->get());

        // Nurse: clinical support, no finalise, no financial adjustments.
        $nurse->syncPermissions(Permission::where(function ($q) {
            foreach ([
                'client', 'patient', 'appointment', 'reminder', 'task',
                'vaccination', 'prescription', 'product', 'stock_movement',
                'inventory_adjustment', 'stock_receipt',
            ] as $key) {
                $q->orWhere('name', 'like', "view_any_{$key}")
                    ->orWhere('name', 'like', "view_{$key}");
            }
        })->orWhereIn('name', [
            'view_any_consultation', 'view_consultation', 'create_consultation', 'update_consultation',
            'create_appointment', 'update_appointment', 'create_task', 'update_task',
            'create_vaccination', 'update_vaccination', 'create_prescription', 'update_prescription',
            'run_reminders',
        ])->get());

        // Receptionist: front desk — clients, appointments, counter sales, payments.
        $receptionist->syncPermissions(Permission::whereIn('name', [
            'view_any_client', 'view_client', 'create_client', 'update_client',
            'view_any_patient', 'view_patient', 'create_patient', 'update_patient',
            'view_any_appointment', 'view_appointment', 'create_appointment', 'update_appointment', 'delete_appointment',
            'view_any_reminder', 'view_reminder', 'update_reminder',
            'view_any_task', 'view_task', 'create_task', 'update_task',
            'view_any_counter_sale', 'view_counter_sale', 'create_counter_sale', 'update_counter_sale',
            'view_any_invoice', 'view_invoice',
            'view_any_payment', 'view_payment', 'create_payment', 'process_payment',
            'view_any_consultation', 'view_consultation',
            'view_any_report', 'view_report',
            'run_reminders', 'run_statements',
        ])->get());
    }
}
