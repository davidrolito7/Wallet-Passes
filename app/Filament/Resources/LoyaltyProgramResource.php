<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyProgramResource\Pages;
use App\Models\Business;
use App\Models\LoyaltyProgram;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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
        return $schema->columns(1)->schema([

            // ─────────────────────────────────────────────────────────
            // INFORMACIÓN GENERAL
            // ─────────────────────────────────────────────────────────
            Section::make('Información General')
                ->description('Configura el programa principal de lealtad.')
                ->icon('heroicon-o-sparkles')
                ->collapsible()
                ->schema([

                    Select::make('business_id')
                        ->label('Negocio')
                        ->options(Business::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),

                    TextInput::make('name')
                        ->label('Nombre del Programa')
                        ->placeholder('Ej: Café Lovers')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('total_stamps')
                        ->label('Visitas Necesarias')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(50)
                        ->suffix('sellos')
                        ->required()
                        ->live(),

                    Toggle::make('is_active')
                        ->label('Programa Activo')
                        ->default(true)
                        ->inline(false),

                    Textarea::make('description')
                        ->label('Descripción')
                        ->placeholder('Describe brevemente el beneficio del programa...')
                        ->rows(3),

                ])
                ->columns(1),

            // ─────────────────────────────────────────────────────────
            // PREMIOS
            // ─────────────────────────────────────────────────────────
            Section::make('Sistema de Premios')
                ->description('Premios intermedios y recompensa final.')
                ->icon('heroicon-o-trophy')
                ->collapsible()
                ->schema([

                    Repeater::make('milestones')
                        ->relationship()
                        ->label('Premios Intermedios')
                        ->collapsed()
                        ->cloneable()
                        ->reorderableWithButtons()
                        ->addActionLabel('Agregar Premio')
                        ->itemLabel(
                            fn(array $state): ?string =>
                            filled($state['stamp_count'])
                                ? "Visita #{$state['stamp_count']}"
                                : 'Nuevo premio'
                        )
                        ->schema([

                            TextInput::make('stamp_count')
                                ->label('Visita')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->prefix('#'),

                            TextInput::make('reward_title')
                                ->label('Premio')
                                ->placeholder('Ej: Galleta gratis')
                                ->required(),

                            Textarea::make('reward_description')
                                ->label('Descripción')
                                ->rows(2),

                            Toggle::make('is_repeatable')
                                ->label('Repetible')
                                ->helperText('Se entrega en cada ciclo.')
                                ->inline(false),

                        ])
                        ->columns(1),

                    Section::make('Premio Final')
                        ->description('Recompensa principal al completar la tarjeta.')
                        ->icon('heroicon-o-gift')
                        ->collapsible()
                        ->schema([

                            TextInput::make('reward_title')
                                ->label('Premio Principal')
                                ->placeholder('Ej: Bebida gratis')
                                ->required(),

                            Textarea::make('reward_description')
                                ->label('Descripción')
                                ->rows(2),

                        ])
                        ->columns(1)

                ])
                ->columns(1),

            // ─────────────────────────────────────────────────────────
            // IMÁGENES PARA WALLET
            // ─────────────────────────────────────────────────────────
            Section::make('Imágenes para Wallet')
                ->description('Estas imágenes se muestran en Apple Wallet y Google Wallet.')
                ->icon('heroicon-o-photo')
                ->collapsible()
                ->schema([

                    FileUpload::make('pass_background_image')
                        ->label('Imagen de Fondo')
                        ->helperText('PNG · Recomendado: 1032×300 px · Se usa como fondo en Apple Wallet (strip) y Google Wallet (hero)')
                        ->image()
                        ->disk('public')
                        ->directory('programs/backgrounds')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->imagePreviewHeight('120')
                        ->columnSpanFull(),

                ])
                ->columns(1),

            // ─────────────────────────────────────────────────────────
            // GOOGLE WALLET
            // ─────────────────────────────────────────────────────────
            Section::make('Google Wallet')
                ->description('Configuración avanzada.')
                ->icon('heroicon-o-wallet')
                ->collapsible()
                ->collapsed()
                ->schema([

                    TextInput::make('google_class_suffix')
                        ->label('Class Suffix')
                        ->helperText('Opcional')
                        ->unique(ignoreRecord: true),

                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->label('Negocio')
                    ->sortable()
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('name')
                    ->label('Programa')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_stamps')
                    ->label('Visitas')
                    ->suffix(' visitas')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('milestones_count')
                    ->label('Premios interm.')
                    ->counts('milestones')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('reward_title')
                    ->label('Premio Final')
                    ->limit(35),

                TextColumn::make('loyaltyCards_count')
                    ->label('Tarjetas')
                    ->counts('loyaltyCards')
                    ->sortable()
                    ->alignCenter(),

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
        return [];
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
