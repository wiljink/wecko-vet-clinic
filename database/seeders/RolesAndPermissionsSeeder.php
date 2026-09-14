<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wicko security model (System Maintenance > Security).
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
        'stock_take', 'inventory_adjustment', 'inventory_return', 'stock_transfer',
        // Setup — principal-only, except 'room' which branch staff self-serve
        'reference_data', 'company_setting', 'document_template', 'marketing_campaign', 'room',
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
            'post_stock_take', 'post_stock_receipt', 'post_stock_transfer',
            'process_payment', 'process_refund', 'make_account_adjustment',
            'run_statements', 'run_reminders', 'run_marketing',
            'import_data',
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

        // Veterinarian, nurse, receptionist: Setup (reference_data,
        // company_setting, marketing_campaign) is principal-only — branch
        // staff self-serve only their own Rooms and Document Templates.
        $vet->syncPermissions(Permission::where(function ($q) {
            foreach ([
                'client', 'patient', 'patient_transfer', 'appointment', 'reminder', 'task',
                'consultation', 'standard_consult', 'vaccination', 'prescription',
                'counter_sale', 'invoice', 'payment', 'account_adjustment',
                'product', 'supplier', 'stock_movement', 'inventory_order', 'stock_receipt',
                'stock_take', 'inventory_adjustment', 'inventory_return', 'stock_transfer', 'report',
                'room', 'document_template',
            ] as $key) {
                $q->orWhere('name', 'like', "%_{$key}");
            }
        })->orWhereIn('name', [
            'finalize_consultation', 'reopen_consultation', 'post_stock_take',
            'post_stock_receipt', 'post_stock_transfer', 'process_payment', 'process_refund',
            'make_account_adjustment', 'run_statements', 'run_reminders', 'import_data',
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
            'view_any_room', 'view_room', 'create_room', 'update_room', 'delete_room',
            'view_any_document_template', 'view_document_template', 'create_document_template', 'update_document_template', 'delete_document_template',
        ])->get());

        // Receptionist: front desk — clients, appointments, calendar. No
        // Inventory, no Setup, no Sales, no Reports — booking/records only.
        $receptionist->syncPermissions(Permission::whereIn('name', [
            'view_any_client', 'view_client', 'create_client', 'update_client',
            'view_any_patient', 'view_patient', 'create_patient', 'update_patient',
            'view_any_appointment', 'view_appointment', 'create_appointment', 'update_appointment', 'delete_appointment',
            'view_any_reminder', 'view_reminder', 'update_reminder',
            'view_any_task', 'view_task', 'create_task', 'update_task',
            'view_any_consultation', 'view_consultation',
            'run_reminders',
        ])->get());
    }
}
