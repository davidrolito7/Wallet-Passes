<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyProgramResource\Pages;
use App\Filament\Resources\LoyaltyProgramResource\RelationManagers;
use App\Models\Business;
use App\Models\LoyaltyProgram;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LoyaltyProgramResource extends Resource
{
    protected static ?string $model = LoyaltyProgram::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Programas de Lealtad';

    protected static ?string $modelLabel = 'Programa';

    protected static ?string $pluralModelLabel = 'Programas de Lealtad';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Programa')->schema([
                Select::make('business_id')
                    ->label('Negocio')
                    ->options(Business::pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                TextInput::make('name')
                    ->label('Nombre del Programa')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(2)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ])->columns(2),

            Section::make('Configuración de Sellos')->schema([
                TextInput::make('total_stamps')
                    ->label('Total de Sellos Requeridos')
                    ->numeric()
                    ->default(10)
                    ->minValue(1)
                    ->maxValue(50)
                    ->required(),

                Select::make('stamp_icon')
                    ->label('Icono de Sello')
                    ->options([
                        'coffee' => '☕ Café',
                        'star'   => '⭐ Estrella',
                        'stamp'  => '🔵 Sello',
                        'heart'  => '❤️ Corazón',
                    ])
                    ->default('coffee')
                    ->required(),

                TextInput::make('stamp_icon_url')
                    ->label('URL de Icono Personalizado')
                    ->url()
                    ->maxLength(2048)
                    ->helperText('Opcional — solo si usas un icono personalizado.'),
            ])->columns(3),

            Section::make('Premio')->schema([
                TextInput::make('reward_title')
                    ->label('Título del Premio')
                    ->placeholder('Ej: Café gratis')
                    ->required()
                    ->maxLength(255),

                Textarea::make('reward_description')
                    ->label('Descripción del Premio')
                    ->placeholder('Ej: 1 café de tu elección sin costo')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(1),

            Section::make('Google Wallet')->schema([
                TextInput::make('google_class_suffix')
                    ->label('Sufijo de Clase Google')
                    ->helperText('Identificador único para Google Wallet. Se genera automáticamente si se deja vacío.')
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->label('Negocio')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Programa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stamp_icon')
                    ->label('Icono')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'coffee' => '☕',
                        'star'   => '⭐',
                        'stamp'  => '🔵',
                        'heart'  => '❤️',
                        default  => '●',
                    }),

                TextColumn::make('total_stamps')
                    ->label('Sellos')
                    ->suffix(' sellos')
                    ->sortable(),

                TextColumn::make('reward_title')
                    ->label('Premio')
                    ->limit(30),

                TextColumn::make('loyaltyCards_count')
                    ->label('Tarjetas')
                    ->counts('loyaltyCards')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('business_id')
                    ->label('Negocio')
                    ->options(Business::pluck('name', 'id')),
                TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MilestonesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoyaltyPrograms::route('/'),
            'create' => Pages\CreateLoyaltyProgram::route('/create'),
            'edit'   => Pages\EditLoyaltyProgram::route('/{record}/edit'),
        ];
    }
}
