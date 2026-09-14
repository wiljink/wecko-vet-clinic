<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\TaskStatusResource\Pages;
use App\Models\TaskStatus;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class TaskStatusResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = TaskStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationLabel = 'Task Statuses';

    protected static ?int $navigationSort = 28;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Status label for internal staff tasks, e.g. To Do, In Progress, Done.'),
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
            'index' => Pages\ManageTaskStatuses::route('/'),
        ];
    }
}
