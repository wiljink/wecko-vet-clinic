<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class GroupResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Product / Service Groups';

    protected static ?int $navigationSort = 18;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->disabled(fn (?\App\Models\Group $record) => $record?->is_protected),
            Forms\Components\Select::make('applies_to')->required()->default('both')
                ->options(['product' => 'Products', 'service' => 'Services', 'both' => 'Both']),
            Forms\Components\Placeholder::make('protected')->content('System group — cannot be renamed or removed.')
                ->visible(fn (?\App\Models\Group $record) => $record?->is_protected),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('applies_to')->badge(),
            Tables\Columns\IconColumn::make('is_protected')->label('System')->boolean(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageGroups::route('/'),
        ];
    }
}
