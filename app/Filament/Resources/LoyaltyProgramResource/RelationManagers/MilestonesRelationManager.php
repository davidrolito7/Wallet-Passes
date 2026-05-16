<?php

namespace App\Filament\Resources\LoyaltyProgramResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Hitos / Premios intermedios';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                TextInput::make('stamp_count')
                    ->label('En el sello #')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('Número de sello al que se activa este premio.'),

                TextInput::make('reward_title')
                    ->label('Premio')
                    ->placeholder('Ej: Cookie gratis, 10% de descuento')
                    ->required()
                    ->maxLength(255),

                Textarea::make('reward_description')
                    ->label('Descripción')
                    ->rows(2)
                    ->columnSpanFull(),

                Toggle::make('is_repeatable')
                    ->label('Se repite cada ciclo')
                    ->helperText('Si está activo, se entrega en cada vez que se alcance este sello (ej. cada 3 visitas).')
                    ->default(false),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reward_title')
            ->columns([
                TextColumn::make('stamp_count')
                    ->label('Sello #')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('reward_title')
                    ->label('Premio')
                    ->searchable(),

                TextColumn::make('reward_description')
                    ->label('Descripción')
                    ->limit(40)
                    ->toggleable(),

                IconColumn::make('is_repeatable')
                    ->label('Repetible')
                    ->boolean(),

                TextColumn::make('redemptions_count')
                    ->label('Canjeados')
                    ->counts('redemptions')
                    ->sortable(),
            ])
            ->defaultSort('stamp_count')
            ->headerActions([
                CreateAction::make()->label('Agregar hito'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
