<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\ReferralResource\Pages;
use App\Models\Referral;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ReferralResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = Referral::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Referral Sources';

    protected static ?int $navigationSort = 12;

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
            'index' => Pages\ManageReferrals::route('/'),
        ];
    }
}
