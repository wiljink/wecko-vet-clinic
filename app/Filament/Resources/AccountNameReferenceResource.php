<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\AccountNameReferenceResource\Pages;
use App\Models\AccountNameReference;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class AccountNameReferenceResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = AccountNameReference::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Account References';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('code')->maxLength(50),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAccountNameReferences::route('/'),
        ];
    }
}
