<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use App\Models\TaskStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationGroup = 'Calendar';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->columnSpanFull(),
            Forms\Components\Select::make('task_type')->options([
                'Call back' => 'Call back', 'Order supplies' => 'Order supplies',
                'Equipment service' => 'Equipment service', 'Lab follow-up' => 'Lab follow-up',
                'Recall patient' => 'Recall patient', 'Admin' => 'Admin',
            ])->searchable(),
            Forms\Components\Select::make('provider_id')->label('Assigned to')
                ->relationship('provider', 'name')->searchable()->preload(),
            Forms\Components\DatePicker::make('due_on'),
            Forms\Components\Select::make('task_status_id')->label('Status')
                ->relationship('status', 'name')
                ->default(fn () => TaskStatus::where('name', 'Not Started')->value('id')),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('task_type')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('provider.name')->label('Assigned to')->sortable(),
                Tables\Columns\TextColumn::make('due_on')->date('d M Y')->sortable()
                    ->color(fn (Task $r) => $r->due_on && $r->due_on->isPast() && $r->status?->name !== 'Completed' ? 'danger' : null),
                Tables\Columns\TextColumn::make('status.name')->label('Status')->badge()
                    ->colors([
                        'gray' => 'Not Started', 'info' => 'In Progress',
                        'success' => 'Completed', 'warning' => 'Deferred',
                    ]),
            ])
            ->defaultSort('due_on')
            ->filters([
                Tables\Filters\SelectFilter::make('task_status_id')->relationship('status', 'name')->label('Status'),
                Tables\Filters\SelectFilter::make('provider_id')->relationship('provider', 'name')->label('Assigned to'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('complete')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (Task $r) => $r->status?->name !== 'Completed')
                    ->action(fn (Task $r) => $r->update([
                        'task_status_id' => TaskStatus::where('name', 'Completed')->value('id'),
                    ])),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTasks::route('/'),
        ];
    }
}
