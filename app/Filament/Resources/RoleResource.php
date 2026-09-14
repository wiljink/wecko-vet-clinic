<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use HasResourcePermissions;

    protected static string $permissionKey = 'role';

    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record) => $record?->name === 'principal')
                ->helperText('Shown wherever roles are listed or assigned to a user; the built-in "principal" role cannot be renamed.'),
            Forms\Components\CheckboxList::make('permissions')
                ->relationship('permissions', 'name')
                ->options(Permission::orderBy('name')->pluck('name', 'id'))
                ->columns(3)->searchable()->bulkToggleable()
                ->descriptions(collect(Permission::pluck('name'))->mapWithKeys(fn ($n) => [$n => ''])->all())
                ->helperText('The "principal" role always has every permission.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')->badge(),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Users')->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn (Role $r) => ! in_array($r->name, ['principal', 'veterinarian', 'nurse', 'receptionist'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
