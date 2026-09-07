# Wecko Vet Clinic

A veterinary practice-management system built with **Laravel 13 + Filament 3**, modelled on
the *Clinic-Ware User Manual v2.0* feature set and localised for the **Philippines**
(currency ₱, 12% VAT — both configurable under Setup → Company Settings).

## Sub-systems

| Area | Modules |
|---|---|
| Clients & Patients | Clients (accounts), Patients (animals), patient transfer between owners, medical charts, photos |
| Calendar | Appointments (recurring, all-day, colour-coded), Reminders (auto-generated), practice Tasks |
| Consultations | SOAP consults, services / drugs / vaccinations, dispensing & injection fees, drug regimes, finalize → invoice |
| Sales | Counter Sales (OTC / walk-in), Invoices, Payments, Account Adjustments |
| Inventory | Products / Vaccines / Services, Suppliers, Stock Orders, Receipts, Stock Takes, Adjustments, Returns, Ledger |
| Financials | Account Balances, Advance Payments, Statements, Aging, Reconciliation, Banking, Balance-the-Till |
| Setup | 21 referential-data lists, Company Settings, Document Templates, Marketing |
| System | Users & role-based security, Activity Log (audit trail), Backups |
| Reports | Sales / VAT, Payments, Cash, Profit, Inventory Valuation, Price List, Reminders Due, … |

## Local setup (Laragon / Windows)

```bash
# MySQL must be running (Laragon → Start All). Database: wecko_vet_clinic
composer install
cp .env.example .env      # already configured for MySQL wecko_vet_clinic
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Admin panel: <http://localhost:8000/admin>

### Seeded logins (password `password`)

| Email | Role |
|---|---|
| `admin@wecko.test` | principal (full access) |

More logins are created by `DemoSeeder` (veterinarians, nurses, receptionist).

## Scheduled commands

```bash
php artisan reminders:generate    # build reminder rows from vaccination boosters, desex age, reminder-type periods
php artisan reminders:dispatch    # send due reminders via letter / email / SMS templates
php artisan backup:run            # nightly database + files backup (spatie/laravel-backup)
```

All three are registered in `routes/console.php` for `php artisan schedule:run`.

## Out of scope / stubbed

- Real SMS gateway — reminder/marketing messages are logged (channel is pluggable).
- Barcode / microchip reader hardware, ChipherLab stock-take terminal import, IDEXX &
  pathology-download interfaces, supplier E-Order EDI. Setup screens exist; no live integration.
- Outbound email needs SMTP credentials in Setup; the `log` mailer is used in development.
