# Wecko Vet Clinic

A veterinary practice-management system built with **Laravel 13 + Filament 3**, modelled on
the *Clinic-Ware User Manual v2.0* feature set and localised for the **Philippines**
(currency ₱, 12% VAT — both configurable under Setup → Company Settings).

## Sub-systems

| Area | Modules |
|---|---|
| Clients & Patients | Clients (accounts, 9 comm-pref flags), Patients (animals), transfer between owners, medical charts & files, photos, history PDF |
| Calendar | FullCalendar board (month/week/day/list, colour-coded, leave + holiday overlays), Appointments (recurring, all-day), auto Reminders, practice Tasks |
| Consultations | SOAP consults (default descriptions), services / drugs / vaccinations / misc lines, dispense & injection fees, drug regimes, open→closed→finalized, finalize → stock deduction + invoice + vaccination register + booster reminder + prescriptions, standard-consult templates, vaccination certificate PDF |
| Sales | Counter Sales (OTC / walk-in, payment window with cash change), Invoices (record payment, void), Payments (types, split allocation, advance), Account Adjustments |
| Inventory | Products / Vaccines / Services, Suppliers, Stock Orders (auto-reorder), Receipts, Stock Takes (+ count sheet), Adjustments, Returns, append-only Stock Ledger |
| Financials | Account Balances (ledger + aging), Statement Run (batch, fees), Banking batches, Balance-the-Till |
| Setup | 20 referential-data lists, Company Settings, Document Templates (merge fields), Marketing campaigns |
| System | Users & role-based security (principal / veterinarian / nurse / receptionist), Roles & per-resource permissions, Activity Log (audit trail), Backups & Housekeeping |
| Reports | Sales & VAT, Payments Received, Cash Sales, Sales/Profit, Transaction Summary, Aging of Accounts, Account Reconciliation, Inventory Valuation, Price List, Reminders Due — all with CSV + PDF export |

## Local setup (Laragon / Windows)

MySQL must be running (Laragon → Start All). Database: **`wecko_vet_clinic`**.

```bash
composer install
cp .env.example .env        # already points at MySQL wecko_vet_clinic
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Admin panel: <http://localhost:8000/admin>

### Seeded logins (password `password`)

| Email | Role |
|---|---|
| `admin@wecko.test` | principal — full access |
| `maria@wecko.test`, `jose@wecko.test`, `andrea@wecko.test` | veterinarian |
| `grace@wecko.test`, `emman@wecko.test` | nurse |
| `rowena@wecko.test` | receptionist (front desk only) |

## Scheduled commands

```bash
php artisan reminders:generate    # vaccination boosters, de-sexing age, product intervals
php artisan reminders:dispatch    # send due reminders (email / sms / letter) via templates
php artisan backup:run --only-db  # nightly DB backup (spatie/laravel-backup + Laragon mysqldump)
```

All are registered in `routes/console.php` for `php artisan schedule:run` (nightly).

## Notes & deviations from a production build

- **SMS / letters** are logged, not sent — `App\Services\ReminderDispatcher::deliver()` is the
  single swap-in point for a real SMS gateway or print queue. **Email** uses Laravel Mail
  (the `log` mailer in dev; set SMTP in `.env` to send).
- **`saade/filament-fullcalendar`** has no Filament-3 + Laravel-13 build, so the calendar page
  loads FullCalendar 6 from CDN directly (`resources/views/filament/pages/calendar-board.blade.php`).
- **Backup** uses Laragon's bundled `mysqldump` (`config/database.php` → `mysql.dump.dump_binary_path`);
  set `DB_DUMP_BINARY_PATH` in `.env` if your MySQL lives elsewhere.
- Not built (Setup screens exist, no live integration): barcode / microchip reader hardware,
  ChipherLab stock-take terminal import, IDEXX & pathology downloads, supplier E-Order EDI.
- `php artisan test` — 11 feature tests cover line-total maths, stock deduction on finalize,
  vaccination boosters, patient transfer, counter-sale change, payment allocation, reminder
  generation, permission gating and statement fees.
