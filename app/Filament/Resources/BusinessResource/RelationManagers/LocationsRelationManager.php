<?php

namespace App\Filament\Resources\BusinessResource\RelationManagers;

use App\Models\BusinessLocation;
use App\Services\GoogleMapsLinkResolver;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    protected static ?string $title = 'Ubicaciones';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()
                ->description('La tarjeta aparece como notificación fija en la pantalla de bloqueo cuando el cliente se acerca a este local (Apple Wallet y Google Wallet). Apple limita esto a un radio real de ~100 m sin importar la ubicación configurada, y a un máximo de 10 ubicaciones por tarjeta.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del local')
                        ->placeholder('Ej: Sucursal Centro')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('maps_link')
                        ->label('Enlace de Google Maps')
                        ->url()
                        ->placeholder('https://maps.app.goo.gl/xxxxx')
                        // El enlace original que se pegó no se guarda — solo las coordenadas ya
                        // resueltas — así que al editar se precarga un enlace equivalente
                        // generado desde lat/long, para que se vea que ya hay una ubicación guardada.
                        ->afterStateHydrated(fn (TextInput $component, ?BusinessLocation $record) => $component->state($record?->mapsUrl()))
                        ->helperText('Abre el local en Google Maps, toca "Compartir" → "Copiar enlace" y pégalo aquí. Extraemos las coordenadas automáticamente. Deja el enlace ya cargado tal cual para conservar la ubicación actual.')
                        ->required(fn (?BusinessLocation $record) => ! $record)
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    TextInput::make('relevant_text')
                        ->label('Mensaje al acercarse')
                        ->maxLength(128)
                        ->placeholder('¡Bienvenido! Muestra tu tarjeta de lealtad.')
                        ->helperText('Solo aplica a Apple Wallet. Máximo 128 caracteres.')
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->helperText('Desactívala para dejar de notificar en este local sin borrarlo.'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->default('—'),

                TextColumn::make('latitude')
                    ->label('Latitud'),

                TextColumn::make('longitude')
                    ->label('Longitud'),

                TextColumn::make('relevant_text')
                    ->label('Mensaje al acercarse')
                    ->limit(40)
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar ubicación')
                    ->mutateFormDataUsing(fn (array $data) => $this->resolveMapsLink($data)),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $this->resolveMapsLink($data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveMapsLink(array $data): array
    {
        $mapsLink = $data['maps_link'] ?? null;
        unset($data['maps_link']);

        if (filled($mapsLink)) {
            try {
                [$data['latitude'], $data['longitude']] = app(GoogleMapsLinkResolver::class)->extract($mapsLink);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('No se pudo leer el enlace de Google Maps')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                throw new Halt();
            }
        }

        return $data;
    }
}
