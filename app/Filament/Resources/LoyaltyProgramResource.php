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
use Filament\Schemas\Components\Utilities\Get;
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

            // ── Datos generales ─────────────────────────────────────────────
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

            // ── Configuración de sellos ──────────────────────────────────────
            Section::make('Configuración de Sellos')->schema([
                TextInput::make('total_stamps')
                    ->label('Total de Visitas Requeridas')
                    ->numeric()
                    ->default(10)
                    ->minValue(1)
                    ->maxValue(50)
                    ->required()
                    ->live()
                    ->helperText('Número de visitas necesarias para completar la tarjeta.'),

                Select::make('stamp_icon')
                    ->label('Sticker por Visita')
                    ->options([
                        'coffee' => '☕ Café',
                        'star'   => '⭐ Estrella',
                        'stamp'  => '🔵 Sello',
                        'heart'  => '❤️ Corazón',
                        'fire'   => '🔥 Fuego',
                        'crown'  => '👑 Corona',
                        'gem'    => '💎 Gema',
                        'bolt'   => '⚡ Rayo',
                    ])
                    ->default('coffee')
                    ->required()
                    ->helperText('Icono que se marca en cada visita completada.'),

                TextInput::make('stamp_icon_url')
                    ->label('URL de Icono Personalizado')
                    ->url()
                    ->maxLength(2048)
                    ->helperText('Opcional — solo si usas un icono personalizado.'),
            ])->columns(3),

            // ── Diseño de tarjeta ────────────────────────────────────────────
            Section::make('Diseño de Tarjeta')
                ->description('Elige un tema visual y sube tus propias imágenes de sello. Si no subes imágenes, se usa el estilo del tema seleccionado.')
                ->schema([
                    // ── Tema y fuente ──────────────────────────────────────
                    Select::make('stamp_style')
                        ->label('Tema Visual')
                        ->options([
                            'minimal' => '⬜ Minimal — limpio, usa tus colores de marca',
                            'luxury'  => '✨ Luxury — fondo oscuro, sellos dorados',
                            'neon'    => '💡 Neon — fondo negro, brillo eléctrico',
                            'coffee'  => '☕ Coffee — tonos café cálidos',
                            'retro'   => '📮 Retro — look de sello postal vintage',
                        ])
                        ->default('minimal')
                        ->required()
                        ->live()
                        ->helperText('El tema se aplica solo si no subes imágenes personalizadas.'),

                    Select::make('card_font')
                        ->label('Fuente de Textos')
                        ->options([
                            'roboto'      => 'Roboto (predeterminada)',
                            'montserrat'  => 'Montserrat',
                            'opensans'    => 'Open Sans',
                            'ubuntu'      => 'Ubuntu',
                        ])
                        ->default('roboto')
                        ->required()
                        ->helperText('Requiere el archivo .ttf en resources/fonts/.'),

                    TextInput::make('stamp_scale')
                        ->label('Escala de Sellos')
                        ->numeric()
                        ->minValue(0.5)
                        ->maxValue(1.5)
                        ->step(0.05)
                        ->default(1.0)
                        ->helperText('0.5 = mitad del tamaño, 1.0 = normal, 1.5 = grande'),

                    TextInput::make('stamp_spacing')
                        ->label('Espaciado (%)')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(40)
                        ->default(15)
                        ->suffix('%')
                        ->helperText('Espacio entre sellos. Más alto = más separados.'),

                    // ── Assets de imagen personalizados ────────────────────
                    FileUpload::make('filled_stamp_image')
                        ->label('Imagen: Sello Completado')
                        ->image()
                        ->disk('public')
                        ->directory('stamps/filled')
                        ->acceptedFileTypes(['image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->imagePreviewHeight('80')
                        ->helperText('PNG/WebP con transparencia. Recomendado: 200×200 px.')
                        ->columnSpan(1),

                    FileUpload::make('empty_stamp_image')
                        ->label('Imagen: Sello Vacío')
                        ->image()
                        ->disk('public')
                        ->directory('stamps/empty')
                        ->acceptedFileTypes(['image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->imagePreviewHeight('80')
                        ->helperText('PNG/WebP con transparencia. Recomendado: 200×200 px.')
                        ->columnSpan(1),

                    FileUpload::make('reward_badge_image')
                        ->label('Imagen: Badge de Premio')
                        ->image()
                        ->disk('public')
                        ->directory('stamps/rewards')
                        ->acceptedFileTypes(['image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->imagePreviewHeight('80')
                        ->helperText('Insignia que aparece sobre sellos con premio. Recomendado: 60×60 px.')
                        ->columnSpan(1),
                ])
                ->columns(4)
                ->collapsible(),

            // ── Premios ──────────────────────────────────────────────────────
            Section::make('Premios')
                ->description('Configura los premios para cada visita. Puedes agregar tantos como quieras — por ejemplo, un café a la 3ª visita, un descuento a la 7ª y el premio grande al completar.')
                ->schema([
                    // ── Premios intermedios (milestones) ────────────────────
                    Repeater::make('milestones')
                        ->label('Premios por Visita')
                        ->relationship()
                        ->schema([
                            TextInput::make('stamp_count')
                                ->label('En la visita #')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->live()
                                ->helperText(fn (Get $get): string => 'Número de visita (máx. ' . ($get('../../total_stamps') ?: '?') . ')'),

                            TextInput::make('reward_title')
                                ->label('Premio')
                                ->placeholder('Ej: Cookie gratis, 10% de descuento')
                                ->required()
                                ->maxLength(255),

                            Textarea::make('reward_description')
                                ->label('Descripción del premio (opcional)')
                                ->rows(2)
                                ->columnSpanFull(),

                            Toggle::make('is_repeatable')
                                ->label('Se repite cada ciclo')
                                ->helperText('Si está activo, el premio se entrega cada vez que se alcance este número de visita.')
                                ->default(false),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Agregar premio intermedio')
                        ->reorderable(false)
                        ->collapsible()
                        ->collapsed(false)
                        ->itemLabel(fn (array $state): ?string =>
                            filled($state['stamp_count']) && filled($state['reward_title'])
                                ? 'Visita #' . $state['stamp_count'] . ' — ' . $state['reward_title']
                                : null
                        ),

                    // ── Premio final ────────────────────────────────────────
                    Section::make('Premio Final (al completar la tarjeta)')->schema([
                        TextInput::make('reward_title')
                            ->label('Premio')
                            ->placeholder('Ej: Café gratis, Producto gratis')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('reward_description')
                            ->label('Descripción')
                            ->placeholder('Ej: 1 bebida de tu elección sin costo')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->compact(),
                ]),

            // ── Google Wallet ────────────────────────────────────────────────
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
                    ->label('Sticker')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'coffee' => '☕',
                        'star'   => '⭐',
                        'stamp'  => '🔵',
                        'heart'  => '❤️',
                        'fire'   => '🔥',
                        'crown'  => '👑',
                        'gem'    => '💎',
                        'bolt'   => '⚡',
                        default  => '●',
                    }),

                TextColumn::make('total_stamps')
                    ->label('Visitas')
                    ->suffix(' visitas')
                    ->sortable(),

                TextColumn::make('milestones_count')
                    ->label('Premios')
                    ->counts('milestones')
                    ->suffix(' configurados')
                    ->sortable(),

                TextColumn::make('reward_title')
                    ->label('Premio Final')
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
