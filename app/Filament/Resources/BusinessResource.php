<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessResource\Pages;
use App\Models\Business;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Negocios';

    protected static ?string $modelLabel = 'Negocio';

    protected static ?string $pluralModelLabel = 'Negocios';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Información del Negocio')->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                FileUpload::make('logo_url')
                    ->label('Logo del Negocio')
                    ->helperText('PNG con fondo transparente · Recomendado: 320×100 px')
                    ->image()
                    ->disk('public')
                    ->directory('businesses/logos')
                    ->acceptedFileTypes(['image/png', 'image/webp'])
                    ->imagePreviewHeight('80')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ])->columns(2),

            Section::make('Colores de Marca')->schema([
                ColorPicker::make('primary_color')
                    ->label('Color Primario')
                    ->default('#1a1a2e'),

                ColorPicker::make('secondary_color')
                    ->label('Color Secundario')
                    ->default('#ffffff'),

                ColorPicker::make('label_color')
                    ->label('Color de Etiquetas')
                    ->default('#cccccc'),
            ])->columns(3),

            Section::make('Acceso y Contacto')->schema([
                TextInput::make('login_email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('El negocio usará este correo para entrar al portal.'),

                TextInput::make('password')
                    ->label('Contraseña')
                    ->default(fn () => Str::password(12))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('Auto-generada. Cópiala antes de guardar. Deja vacía al editar para no cambiarla.'),

                TextInput::make('contact_phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(50),

                TextInput::make('instagram_url')
                    ->label('Instagram')
                    ->url()
                    ->placeholder('https://instagram.com/tu_negocio')
                    ->maxLength(255),
            ])->columns(3),

            Section::make('Ubicación')
                ->description('Si se configura, la tarjeta aparece como notificación fija en la pantalla de bloqueo cuando el cliente se acerca al negocio (Apple Wallet y Google Wallet).')
                ->schema([
                    TextInput::make('latitude')
                        ->label('Latitud')
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->placeholder('19.432608')
                        ->helperText('En Google Maps: clic derecho sobre el negocio → copiar coordenadas.'),

                    TextInput::make('longitude')
                        ->label('Longitud')
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->placeholder('-99.133209'),

                    TextInput::make('location_radius_meters')
                        ->label('Radio de activación')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(5000)
                        ->default(150)
                        ->suffix('metros')
                        ->helperText('Solo aplica a Apple Wallet. Google Wallet decide el radio automáticamente.'),

                    TextInput::make('location_relevant_text')
                        ->label('Mensaje al acercarse')
                        ->maxLength(128)
                        ->placeholder('¡Bienvenido! Muestra tu tarjeta de lealtad.')
                        ->helperText('Solo aplica a Apple Wallet. Máximo 128 caracteres.')
                        ->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('login_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                ColorColumn::make('primary_color')
                    ->label('Color'),

                TextColumn::make('loyaltyPrograms_count')
                    ->label('Programas')
                    ->counts('loyaltyPrograms')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            'index'  => Pages\ListBusinesses::route('/'),
            'create' => Pages\CreateBusiness::route('/create'),
            'edit'   => Pages\EditBusiness::route('/{record}/edit'),
        ];
    }
}
