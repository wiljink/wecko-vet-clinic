<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Models\Attachment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Charts & Files';

    protected static ?string $icon = 'heroicon-o-paper-clip';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')->options(Attachment::TYPES)->required()->default('doc'),
            Forms\Components\TextInput::make('title'),
            Forms\Components\FileUpload::make('path')
                ->directory('patient-files')->downloadable()->openable()
                ->acceptedFileTypes(['image/*', 'application/pdf', 'video/mp4'])
                ->helperText('Photo, PDF, X-ray, video…'),
            Forms\Components\Textarea::make('body')->label('Notes / chart description')->rows(4)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state) => Attachment::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('body')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('uploader.name')->label('By')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(Attachment::TYPES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['uploaded_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
