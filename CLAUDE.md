# Wecko Vet Clinic — working notes

Laravel 13 + Filament 3 veterinary practice-management system, PH-localised (₱ / 12% VAT).
Built from the *Clinic-Ware User Manual v2.0*. Single Filament panel at `/admin`, MySQL
`wecko_vet_clinic`. See `README.md` for the feature map and seeded logins.

## Conventions

- **Permissions**: every Filament resource uses `App\Filament\Concerns\HasResourcePermissions`
  (or `IsReferenceResource` for the 20 Setup lists). Set the key with
  `protected static string $permissionKey = '…'` OR override
  `protected static function permissionKey(): string`. Abilities are `<ability>_<key>`
  (view_any/view/create/update/delete) plus fine-grained ones
  (`finalize_consultation`, `post_stock_take`, `process_payment`, `run_statements`…),
  all generated in `Database\Seeders\RolesAndPermissionsSeeder::RESOURCE_KEYS`.
  Role `principal` bypasses every check via `Gate::before` in `AppServiceProvider`.
- **Filament closures** must have injectable param names: `fn ($state)`, `fn (Model $record)`,
  `fn (Builder $query)` — never `$q` / `$s`.
- **Money**: line items store ex-tax and inc-tax; `App\Support\LineTotals::forLine()` is the
  one place the discount → fee → tax order is defined. Totals recompute via model `saved`/`deleted`
  hooks calling the parent's `recalculateTotals()`.
- **Stock**: `StockMovement::record($product, $type, $qtyChange, [...])` is the only way to
  move stock; a `created` observer updates `products.qty_on_hand`. Never write qty_on_hand directly.
- **Doc numbers**: `App\Models\Concerns\GeneratesReference` (`$referencePrefix`, `$referenceColumn`).
- **Reports**: add a method to `App\Support\Reports\ReportBuilder` returning the standard
  `key/title/subtitle/period/tiles/sections/notes` shape, then a one-line subclass of
  `App\Filament\Pages\Reports\BaseReport` setting `$builderMethod`.
- **Reminders / marketing**: `App\Services\ReminderDispatcher` — `deliver()` is the channel
  swap-in point (email real, sms/letter logged).

## Gotchas

- Composer: `C:\laragon\bin\composer\composer.phar`; use `--no-scripts` then
  `php artisan package:discover` (post-autoload-dump hangs under Laragon).
- `saade/filament-fullcalendar` unavailable for FL3+L13 → calendar loads FullCalendar 6 from CDN.
- `spatie/laravel-backup` needs `^10`; dump binary path is set in `config/database.php`.
- Tests run on sqlite `:memory:` (`php artisan test`).
