<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\LeaveVacationBreakResource\Pages;
use App\Models\LeaveVacationBreak;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class LeaveVacationBreakResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = LeaveVacationBreak::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Leave, Vacation & Breaks';

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'type';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\Select::make('user_id')->label('Provider')->required()
                ->relationship('user', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('is_provider', true))->searchable()->preload()
                ->helperText('Staff member who will be unavailable during this period.'),
            Forms\Components\Select::make('type')->required()->default('leave')
                ->options(['leave' => 'Leave', 'vacation' => 'Vacation', 'break' => 'Break'])
                ->helperText('How this absence is labelled on the calendar.'),
            Forms\Components\DateTimePicker::make('starts_at')->required()->seconds(false)
                ->helperText('When the provider becomes unavailable.'),
            Forms\Components\DateTimePicker::make('ends_at')->required()->seconds(false)
                ->helperText('When the provider becomes available again.'),
            Forms\Components\Textarea::make('notes')->columnSpanFull()
                ->helperText('Reason for the absence, e.g. annual leave, conference, sick leave.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('user.name')->label('Provider')->sortable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('starts_at')->dateTime('d M Y H:i')->sortable(),
            Tables\Columns\TextColumn::make('ends_at')->dateTime('d M Y H:i'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLeaveVacationBreaks::route('/'),
        ];
    }
}
