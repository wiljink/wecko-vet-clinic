<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\IsReferenceResource;
use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class DocumentTemplateResource extends Resource
{
    use IsReferenceResource;

    protected static ?string $model = DocumentTemplate::class;

    protected static function permissionKey(): string
    {
        return 'document_template';
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Document Templates';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function referenceFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('type')->required()
                ->options([
                    'letter' => 'Letter', 'email' => 'Email', 'sms' => 'SMS',
                    'reminder_letter' => 'Reminder — Letter', 'reminder_email' => 'Reminder — Email',
                    'reminder_sms' => 'Reminder — SMS', 'certificate' => 'Vaccination Certificate',
                    'home_care' => 'Home Care Note', 'statement' => 'Account Statement', 'marketing' => 'Marketing',
                ])->live(),
            Forms\Components\Select::make('channel')->options(['letter' => 'Letter', 'email' => 'Email', 'sms' => 'SMS']),
            Forms\Components\Select::make('patient_reminder_type_id')->label('Reminder type')
                ->relationship('reminderType', 'name')->searchable()->preload()
                ->visible(fn (Forms\Get $get) => str_contains((string) $get('type'), 'reminder')),
            Forms\Components\TextInput::make('sequence')->numeric()->default(1)
                ->helperText('1 = first notice, 2 = second/chaser notice.'),
            Forms\Components\TextInput::make('subject')->maxLength(255)->columnSpanFull(),
            Forms\Components\Textarea::make('body')->required()->rows(10)->columnSpanFull()
                ->helperText('Merge fields: {{ client.name }}, {{ patient.name }}, {{ reminder.due_on }}, {{ clinic.name }}, {{ invoice.total }}.'),
        ];
    }

    protected static function referenceTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('channel')->badge(),
            Tables\Columns\TextColumn::make('reminderType.name')->label('Reminder type'),
            Tables\Columns\TextColumn::make('sequence'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDocumentTemplates::route('/'),
        ];
    }
}
