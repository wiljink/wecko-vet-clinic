<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branches only — clinic sites staff, stock, sales and the dashboard are
 * scoped to. Exam rooms live in {@see RoomResource} instead.
 */
class LocationResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Branches';

    protected static ?int $navigationSort = 23;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Branch';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Branches';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->branches();
    }

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\Hidden::make('type')->default(Location::TYPE_BRANCH),
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Branch name shown throughout the app, e.g. when assigning sales and stock to a branch.'),
            Forms\Components\TextInput::make('code')->maxLength(255)
                ->helperText('Short branch code, e.g. for receipt/invoice numbering across branches.'),
            Forms\Components\TextInput::make('description')->maxLength(255)
                ->helperText('Optional note to help staff tell similarly named branches apart.'),
            Forms\Components\Textarea::make('address')->maxLength(500)
                ->helperText('Street address of this branch.'),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
            Forms\Components\TextInput::make('email')->email()->maxLength(255),
            Forms\Components\Toggle::make('is_main')->label('Main branch')
                ->helperText('The default branch new staff and historical records fall back to. Only one branch can be main.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->badge()->toggleable(),
            Tables\Columns\TextColumn::make('description')->limit(50)->toggleable(),
            Tables\Columns\TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\IconColumn::make('is_main')->label('Main')->boolean()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLocations::route('/'),
        ];
    }
}
