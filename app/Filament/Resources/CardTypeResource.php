<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\CardTypeResource\Pages;
use App\Models\CardType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class CardTypeResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = CardType::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Payment / Card Types';

    protected static ?int $navigationSort = 15;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCardTypes::route('/'),
        ];
    }
}
