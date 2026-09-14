<?php

namespace App\Filament\Pages;

use App\Support\Import\CsvFile;
use App\Support\Import\Importer;
use App\Support\Import\ImportResult;
use App\Support\Import\ProductImporter;
use App\Support\Import\StockLevelImporter;
use App\Support\Import\SupplierImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Setup > Data Import — bulk-load the catalogue and stock from CSV files.
 *
 * Every import runs a dry-run preview first (inside a rolled-back transaction)
 * so the operator sees exactly what will change before committing.
 */
class DataImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Setup';

    protected static ?string $navigationLabel = 'Data Import';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.data-import';

    protected static ?string $title = 'Data Import';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?ImportResult $preview = null;

    /** @return array<string, class-string<Importer>> */
    public const TYPES = [
        'products' => ProductImporter::class,
        'suppliers' => SupplierImporter::class,
        'stock_levels' => StockLevelImporter::class,
    ];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('import_data') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['type' => 'products']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('type')->label('What are you importing?')
                ->options(collect(self::TYPES)->map(fn ($class) => (new $class)->label()))
                ->required()->live()->afterStateUpdated(fn () => $this->preview = null)
                ->helperText('Determines which columns are expected and which records are created or updated.'),

            Placeholder::make('columns')->label('Expected columns')
                ->content(fn (): Htmlable => new HtmlString(
                    '<code class="text-xs">'.implode(', ', $this->importer()->headers()).'</code>'
                    .'<p class="mt-1 text-xs text-gray-500">Column order does not matter; extra columns are ignored. '
                    .'Use the “Download template” button for a starter file.</p>',
                )),

            FileUpload::make('file')->label('CSV file')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                ->disk('local')->directory('imports')->visibility('private')
                ->storeFileNamesIn('file_name')
                ->required()
                ->afterStateUpdated(fn () => $this->preview = null)
                ->helperText('Run "Preview" first to see the changes in a rolled-back trial run before anything is actually saved.'),
        ])->statePath('data')->columns(1);
    }

    public function importer(): Importer
    {
        $class = self::TYPES[$this->data['type'] ?? 'products'] ?? ProductImporter::class;

        return new $class;
    }

    public function downloadTemplate()
    {
        $importer = $this->importer();
        $name = str_replace(' ', '-', strtolower($importer->label())).'-template.csv';

        return response()->streamDownload(
            fn () => print CsvFile::template($importer),
            $name,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function runPreview(): void
    {
        $this->preview = $this->execute(dryRun: true);

        if ($this->preview) {
            Notification::make()->title('Preview ready — review the changes below')->info()->send();
        }
    }

    public function runImport(): void
    {
        abort_unless(Auth::user()?->can('import_data'), 403);

        $result = $this->execute(dryRun: false);

        if (! $result) {
            return;
        }

        $this->preview = $result;

        Notification::make()
            ->title('Import complete')
            ->body($result->summary())
            ->success()
            ->send();
    }

    protected function execute(bool $dryRun): ?ImportResult
    {
        $state = $this->form->getState();
        $path = $state['file'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            Notification::make()->title('Upload a CSV file first')->warning()->send();

            return null;
        }

        try {
            $importer = $this->importer();
            $parsed = CsvFile::read(Storage::disk('local')->path($path), $importer);

            if ($parsed['rows'] === []) {
                Notification::make()->title('No data rows found in the file')->warning()->send();

                return null;
            }

            return $importer->run($parsed['rows'], $dryRun);
        } catch (\Throwable $e) {
            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();

            return null;
        }
    }
}
