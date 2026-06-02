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
use Filament\Schemas\Components\Utilities\Get;
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
                ->description('Sube los 3 stickers para activar la versión visual (3 filas × 5 sellos). Sin stickers se muestra el contador de texto.')
                ->icon('heroicon-o-photo')
                ->collapsible()
                ->schema([

                    FileUpload::make('pass_background_image')
                        ->label('Fondo del Wallet')
                        ->helperText('PNG · Recomendado 1032×300 px · Fondo de Apple Wallet (strip) y Google Wallet (hero) cuando no hay stickers')
                        ->image()
                        ->disk('public')
                        ->directory('programs/backgrounds')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->imagePreviewHeight('100')
                        ->columnSpanFull(),

                    FileUpload::make('filled_stamp_image')
                        ->label('Sticker — Estampa Completa')
                        ->helperText('PNG transparente · Aparece cuando la visita ya está registrada · Recomendado 200×200 px')
                        ->image()
                        ->disk('public')
                        ->directory('programs/stamps')
                        ->acceptedFileTypes(['image/png', 'image/webp'])
                        ->imagePreviewHeight('80'),

                    FileUpload::make('empty_stamp_image')
                        ->label('Sticker — Estampa Vacía')
                        ->helperText('PNG transparente · Aparece en los sellos pendientes · Recomendado 200×200 px')
                        ->image()
                        ->disk('public')
                        ->directory('programs/stamps')
                        ->acceptedFileTypes(['image/png', 'image/webp'])
                        ->imagePreviewHeight('80'),

                    FileUpload::make('reward_badge_image')
                        ->label('Sticker — Premio / Badge')
                        ->helperText('PNG transparente · Se superpone en milestones y sello final · Recomendado 100×100 px')
                        ->image()
                        ->disk('public')
                        ->directory('programs/stamps')
                        ->acceptedFileTypes(['image/png', 'image/webp'])
                        ->imagePreviewHeight('80'),

                ])
                ->columns(2),

            // ─────────────────────────────────────────────────────────
            // GOOGLE WALLET
            // ─────────────────────────────────────────────────────────
            Section::make('Google Wallet')
                ->description('Configuración avanzada y notificaciones Android.')
                ->icon('heroicon-o-wallet')
                ->collapsible()
                ->collapsed()
                ->schema([

                    TextInput::make('google_class_suffix')
                        ->label('Class Suffix')
                        ->helperText('Opcional. Identificador único de la clase en Google Wallet.')
                        ->unique(ignoreRecord: true),

                ])
                ->columns(1),

            // ─────────────────────────────────────────────────────────
            // NOTIFICACIONES GOOGLE WALLET
            // ─────────────────────────────────────────────────────────
            Section::make('Notificaciones Google Wallet')
                ->description('Configura qué recibe el cliente en Android al registrar cada visita.')
                ->icon('heroicon-o-bell')
                ->collapsible()
                ->collapsed()
                ->schema([

                    Toggle::make('visit_notification_enabled')
                        ->label('Activar mensaje personalizado por visita')
                        ->helperText('Cuando está activo, se envía el mensaje configurado abajo al sumar cada visita en Google Wallet.')
                        ->inline(false)
                        ->live(),

                    TextInput::make('visit_notification_title')
                        ->label('Título del mensaje')
                        ->placeholder('Nueva visita registrada')
                        ->helperText('Variables: {first_name} · {business_name} · {program_name}')
                        ->maxLength(100)
                        ->visible(fn (Get $get) => (bool) $get('visit_notification_enabled')),

                    Textarea::make('visit_notification_message')
                        ->label('Cuerpo del mensaje')
                        ->placeholder('Llevas {stamps_collected}/{total_stamps} visitas. ¡Gracias por visitarnos!')
                        ->helperText('Variables disponibles: {first_name} {full_name} {stamps_collected} {total_stamps} {remaining_stamps} {business_name} {program_name} {next_reward} {reward_title}')
                        ->rows(3)
                        ->maxLength(500)
                        ->visible(fn (Get $get) => (bool) $get('visit_notification_enabled')),

                    Select::make('google_wallet_notification_mode')
                        ->label('Modo de notificación Android')
                        ->options([
                            'custom_message_only' => 'Solo mensaje personalizado — evita doble notificación (recomendado)',
                            'both'                => 'Ambas — balance del sistema + mensaje personalizado',
                        ])
                        ->default('custom_message_only')
                        ->native(false)
                        ->helperText('Sin mensaje personalizado activo siempre se usa la notificación de balance (sistema).')
                        ->visible(fn (Get $get) => (bool) $get('visit_notification_enabled')),

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
