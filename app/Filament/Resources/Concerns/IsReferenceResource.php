<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Concerns\HasResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Shared behaviour for every Setup > Referential Data list: a "Setup" nav group,
 * an is_active toggle + default "active only" filter, and standard row actions.
 * A concrete resource only supplies {@see referenceFormFields()} and
 * {@see referenceTableColumns()} plus the usual navigation metadata.
 */
trait IsReferenceResource
{
    use HasResourcePermissions;

    protected static function permissionKey(): string
    {
        return 'reference_data';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Setup';
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    abstract protected static function referenceFormFields(): array;

    /** @return array<int, \Filament\Tables\Columns\Column> */
    abstract protected static function referenceTableColumns(): array;

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...static::referenceFormFields(),
            Forms\Components\Toggle::make('is_active')
                ->helperText('Inactive values stay on historical records but are hidden from drop-downs.')
                ->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...static::referenceTableColumns(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active')->default(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-m-check-circle')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-m-x-circle')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->paginated([25, 50, 100, 'all']);
    }
}
