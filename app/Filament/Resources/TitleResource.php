<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\TitleResource\Pages;
use App\Models\Title;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class TitleResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Title::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Titles';

    protected static ?int $navigationSort = 10;

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
            'index' => Pages\ManageTitles::route('/'),
        ];
    }
}
