<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Users & Security';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()
                    ->helperText('Shown throughout the system on schedules, audit logs and printed documents.'),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true)
                    ->helperText('Used to log in and to receive password reset emails.'),
                Forms\Components\TextInput::make('password')->password()->revealable()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('Leave blank when editing an existing user to keep their current password.'),
                Forms\Components\Select::make('job_position_id')->relationship('jobPosition', 'name')->searchable()->preload()
                    ->helperText('Job title shown on staff lists and schedules; does not by itself grant any access.'),
                Forms\Components\TextInput::make('licence_no')->label('PRC licence #')
                    ->helperText('Printed on prescriptions and certificates signed by this user.'),
                Forms\Components\Select::make('roles')->multiple()->relationship('roles', 'name')->preload()
                    ->helperText('Security level. "principal" has full access to everything.'),
                Forms\Components\Select::make('home_location_id')->label('Home branch')
                    ->relationship('homeLocation', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->branches())
                    ->searchable()->preload()
                    ->helperText('Branch this user is restricted to. Principals can see and switch between every branch regardless of this setting.'),
            ]),
            Forms\Components\Section::make('Access')->columns(3)->schema([
                Forms\Components\Toggle::make('is_provider')->label('Is a provider (vet / nurse who attends patients)')
                    ->helperText('Allows this user to be assigned as the attending vet or nurse on appointments and consultations.'),
                Forms\Components\Toggle::make('can_login')->label('Allowed to log in')->default(true)
                    ->helperText('Uncheck to block this account from signing in without deleting their record.'),
                Forms\Components\Toggle::make('is_principal')->label('Principal / practice owner')
                    ->helperText('Grants unrestricted access to every module and cannot be blocked or deleted.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('jobPosition.name')->label('Position')->badge(),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge(),
                Tables\Columns\TextColumn::make('homeLocation.name')->label('Branch')->toggleable(),
                Tables\Columns\TextColumn::make('licence_no')->label('Licence')->toggleable(),
                Tables\Columns\IconColumn::make('is_provider')->boolean()->label('Provider'),
                Tables\Columns\IconColumn::make('can_login')->boolean()->label('Login'),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_provider')->label('Providers'),
                Tables\Filters\TernaryFilter::make('can_login')->label('Can log in'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn (User $r) => ! $r->is_principal),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
