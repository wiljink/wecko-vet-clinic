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
            Forms\Components\TextInput::make('label')->placeholder('Home, Work, Farm')
                ->helperText('Short tag to tell a client\'s multiple addresses apart.'),
            Forms\Components\Toggle::make('is_primary')
                ->helperText('The primary address is used by default on invoices, labels and reminders.'),
            Forms\Components\TextInput::make('line1')->label('Address line 1')->columnSpanFull()
                ->helperText('Street number and name.'),
            Forms\Components\TextInput::make('line2')->label('Address line 2')->columnSpanFull()
                ->helperText('Unit, building, or other additional address detail.'),
            Forms\Components\TextInput::make('suburb')
                ->helperText('Suburb or barangay.'),
            Forms\Components\TextInput::make('postcode')
                ->helperText('Postal / ZIP code.'),
            Forms\Components\Select::make('state_id')->relationship('state', 'name')->searchable()->preload()
                ->helperText('Province or state — used for postage and regional reporting.'),
            Forms\Components\TextInput::make('street_directory_ref')->label('Street directory ref.')
                ->helperText('Map reference for locating rural or hard-to-find properties.'),
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
