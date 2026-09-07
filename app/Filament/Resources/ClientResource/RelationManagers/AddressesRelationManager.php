<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Addresses';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')->placeholder('Home, Work, Farm'),
            Forms\Components\Toggle::make('is_primary'),
            Forms\Components\TextInput::make('line1')->label('Address line 1')->columnSpanFull(),
            Forms\Components\TextInput::make('line2')->label('Address line 2')->columnSpanFull(),
            Forms\Components\TextInput::make('suburb'),
            Forms\Components\TextInput::make('postcode'),
            Forms\Components\Select::make('state_id')->relationship('state', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('street_directory_ref')->label('Street directory ref.'),
            Forms\Components\TextInput::make('travel_distance')
                ->helperText('For farm / large-animal call-outs.'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line1')
            ->columns([
                Tables\Columns\IconColumn::make('is_primary')->boolean()->label('Primary'),
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('one_line')->label('Address')->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('makePrimary')
                    ->icon('heroicon-m-star')->color('warning')
                    ->visible(fn ($record) => ! $record->is_primary)
                    ->action(function ($record) {
                        $record->client->addresses()->update(['is_primary' => false]);
                        $record->update(['is_primary' => true]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
