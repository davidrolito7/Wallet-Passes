<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyCardResource\Pages;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\AppleWalletService;
use App\Services\LoyaltyService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LoyaltyCardResource extends Resource
{
    protected static ?string $model = LoyaltyCard::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Tarjetas';

    protected static ?string $modelLabel = 'Tarjeta de Lealtad';

    protected static ?string $pluralModelLabel = 'Tarjetas de Lealtad';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Programa')->schema([
                Select::make('loyalty_program_id')
                    ->label('Programa de Lealtad')
                    ->options(
                        LoyaltyProgram::with('business')
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => $p->business->name . ' — ' . $p->name])
                    )
                    ->required()
                    ->searchable()
                    ->columnSpanFull(),
            ]),

            Section::make('Datos del Titular')->schema([
                TextInput::make('holder_name')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(255),

                TextInput::make('holder_email')
                    ->label('Correo electrónico')
                    ->email()
                    ->maxLength(255),

                TextInput::make('holder_identifier')
                    ->label('Identificador (QR / dispositivo)')
                    ->maxLength(255)
                    ->helperText('Opcional. Se usa para escanear la tarjeta desde la app.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loyaltyProgram.business.name')
                    ->label('Negocio')
                    ->sortable()
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('loyaltyProgram.name')
                    ->label('Programa')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('holder_name')
                    ->label('Titular')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('holder_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stamps_collected')
                    ->label('Progreso')
                    ->formatStateUsing(fn (LoyaltyCard $record) => $record->progressText())
                    ->badge()
                    ->color(fn (LoyaltyCard $record) => $record->is_completed ? 'success' : 'primary')
                    ->sortable(),

                TextColumn::make('next_reward_hint')
                    ->label('Siguiente Premio')
                    ->state(fn (LoyaltyCard $record) => $record->nextRewardText())
                    ->limit(40)
                    ->tooltip(fn (LoyaltyCard $record) => $record->nextRewardText()),

                IconColumn::make('is_completed')
                    ->label('Completada')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('apple_pass_id')
                    ->label('Apple')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->tooltip(fn ($state) => $state ? 'Pass generado' : 'Sin Apple Pass')
                    ->toggleable(),

                IconColumn::make('google_pass_id')
                    ->label('Google')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->tooltip(fn ($state) => $state ? 'Pass generado' : 'Sin Google Pass')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_stamp_at')
                    ->label('Último Sello')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('loyalty_program_id')
                    ->label('Programa')
                    ->options(
                        LoyaltyProgram::with('business')
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => $p->business->name . ' — ' . $p->name])
                    ),
                TernaryFilter::make('is_completed')->label('Completadas'),
            ])
            ->actions([
                Action::make('add_stamp')
                    ->label('+ Sello')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Agregar sello')
                    ->modalDescription(fn (LoyaltyCard $record) => $record->holder_name . ' · ' . $record->progressText())
                    ->action(function (LoyaltyCard $record) {
                        $result = app(LoyaltyService::class)->addStamp($record, 1, null, auth()->guard()->user()?->name);

                        $title = 'Sello agregado — ' . $result['card']->progressText();

                        if ($result['milestones']->isNotEmpty()) {
                            $title .= ' — Premio: ' . $result['milestones']->pluck('reward_title')->join(', ');
                        }

                        Notification::make()->title($title)->success()->send();
                    })
                    ->visible(fn (LoyaltyCard $record) => ! $record->is_completed),

                Action::make('redeem')
                    ->label('Canjear')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Canjear premio')
                    ->modalDescription(fn (LoyaltyCard $record) => '"' . $record->loyaltyProgram->reward_title . '" para ' . $record->holder_name)
                    ->action(function (LoyaltyCard $record) {
                        app(LoyaltyService::class)->redeemReward($record, auth()->guard()->user()?->name);
                        Notification::make()->title('Premio canjeado')->success()->send();
                    })
                    ->visible(fn (LoyaltyCard $record) => $record->is_completed),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('generate_apple_passes')
                        ->label('Generar Apple Passes')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Generar Apple Wallet Passes')
                        ->modalDescription('Se generará el .pkpass para las tarjetas seleccionadas que no tengan uno aún.')
                        ->action(function (Collection $records) {
                            if (! app(AppleWalletService::class)->isConfigured()) {
                                Notification::make()->title('Apple Wallet no configurado')->body('Verifica las variables MOBILE_PASS_APPLE_* en el .env')->danger()->send();
                                return;
                            }

                            $loyalty = app(LoyaltyService::class);
                            $generated = 0;

                            foreach ($records as $card) {
                                if ($loyalty->generateApplePass($card)) {
                                    $generated++;
                                }
                            }

                            Notification::make()
                                ->title("Apple Passes generados: {$generated} de {$records->count()}")
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => app(AppleWalletService::class)->isConfigured()),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoyaltyCards::route('/'),
            'create' => Pages\CreateLoyaltyCard::route('/create'),
            'edit'   => Pages\EditLoyaltyCard::route('/{record}/edit'),
            'view'   => Pages\ViewLoyaltyCard::route('/{record}'),
        ];
    }
}
