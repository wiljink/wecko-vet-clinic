<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\MarketingCampaignResource\Pages;
use App\Models\DocumentTemplate;
use App\Models\MarketingCampaign;
use App\Models\Species;
use App\Services\ReminderDispatcher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketingCampaignResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = MarketingCampaign::class;

    protected static string $permissionKey = 'marketing_campaign';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Setup';

    protected static ?string $navigationLabel = 'Marketing';

    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->columnSpanFull(),
            Forms\Components\CheckboxList::make('channels')->options([
                'email' => 'Email', 'sms' => 'SMS', 'letter' => 'Letter',
            ])->required()->columns(3),
            Forms\Components\Select::make('document_template_id')->label('Template')
                ->options(DocumentTemplate::where('type', 'marketing')->pluck('name', 'id'))
                ->helperText('Create one under Document Templates (type "Marketing").'),
            Forms\Components\Fieldset::make('Audience filter')->schema([
                Forms\Components\Toggle::make('filter.has_email')->label('Must have an email address'),
                Forms\Components\Select::make('filter.species_id')->label('Owns a patient of species')
                    ->options(Species::pluck('name', 'id')),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('channels')->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                Tables\Columns\TextColumn::make('template.name')->label('Template'),
                Tables\Columns\TextColumn::make('ran_at')->dateTime('d M Y H:i')->placeholder('Not run'),
                Tables\Columns\TextColumn::make('recipients')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('run')
                    ->label('Run now')->icon('heroicon-m-paper-airplane')->color('success')->requiresConfirmation()
                    ->modalDescription('Sends the campaign to every matching client that has opted in to marketing on that channel.')
                    ->action(function (MarketingCampaign $r) {
                        abort_unless(auth()->user()->can('run_marketing'), 403);
                        $sent = app(ReminderDispatcher::class)->runCampaign($r);
                        Notification::make()->title("Campaign sent to {$sent} recipient(s)")->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketingCampaigns::route('/'),
            'create' => Pages\CreateMarketingCampaign::route('/create'),
            'edit' => Pages\EditMarketingCampaign::route('/{record}/edit'),
        ];
    }
}
