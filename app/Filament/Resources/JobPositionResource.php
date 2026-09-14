<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\JobPositionResource\Pages;
use App\Models\JobPosition;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class JobPositionResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = JobPosition::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Job Positions';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Job title/role available when setting up a staff member, e.g. Veterinarian, Receptionist.'),
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
            'index' => Pages\ManageJobPositions::route('/'),
        ];
    }
}
