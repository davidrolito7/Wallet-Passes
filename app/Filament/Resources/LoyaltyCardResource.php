<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyCardResource\Pages;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
            Section::make('Datos del Titular')->schema([
                Select::make('loyalty_program_id')
                    ->label('Programa')
                    ->options(
                        LoyaltyProgram::with('business')
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => $p->business->name . ' — ' . $p->name])
                    )
                    ->required()
                    ->searchable(),

                TextInput::make('holder_name')
                    ->label('Nombre del Titular')
                    ->required()
                    ->maxLength(255),

                TextInput::make('holder_email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('holder_identifier')
                    ->label('Identificador (dispositivo/QR)')
                    ->maxLength(255),
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
                    ->searchable(),

                TextColumn::make('loyaltyProgram.name')
                    ->label('Programa')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('holder_name')
                    ->label('Titular')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stamps_collected')
                    ->label('Progreso')
                    ->formatStateUsing(fn (LoyaltyCard $record) => $record->progressText())
                    ->sortable(),

                TextColumn::make('stamp_visual')
                    ->label('Sellos')
                    ->state(fn (LoyaltyCard $record) => $record->stampVisual())
                    ->fontFamily('mono'),

                IconColumn::make('is_completed')
                    ->label('Completada')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

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
                    ->modalDescription(fn (LoyaltyCard $record) => 'Agregar un sello a ' . $record->holder_name . '. Progreso actual: ' . $record->progressText())
                    ->action(function (LoyaltyCard $record) {
                        $result = app(LoyaltyService::class)->addStamp($record, 1, null, auth()->guard()->user()?->name);

                        $title = 'Sello agregado — ' . $result['card']->progressText();

                        if ($result['milestones']->isNotEmpty()) {
                            $rewards = $result['milestones']->pluck('reward_title')->join(', ');
                            $title .= ' 🎁 Premio desbloqueado: ' . $rewards;
                        }

                        Notification::make()->title($title)->success()->send();
                    })
                    ->visible(fn (LoyaltyCard $record) => ! $record->is_completed),

                Action::make('redeem')
                    ->label('Canjear Premio')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Canjear premio')
                    ->modalDescription(fn (LoyaltyCard $record) => 'Canjear "' . $record->loyaltyProgram->reward_title . '" para ' . $record->holder_name)
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
