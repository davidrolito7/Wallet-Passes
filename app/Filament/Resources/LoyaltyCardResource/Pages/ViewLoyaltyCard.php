<?php

namespace App\Filament\Resources\LoyaltyCardResource\Pages;

use App\Filament\Resources\LoyaltyCardResource;
use App\Models\LoyaltyCard;
use App\Services\AppleWalletService;
use App\Services\LoyaltyService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewLoyaltyCard extends ViewRecord
{
    protected static string $resource = LoyaltyCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_stamp')
                ->label('+ Agregar Sello')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Agregar sello')
                ->modalDescription(fn () => 'Progreso actual: ' . $this->record->progressText())
                ->action(function () {
                    $result = app(LoyaltyService::class)->addStamp($this->record, 1, null, auth()->guard()->user()?->name);
                    $this->refreshFormData([]);

                    $title = 'Sello agregado — ' . $result['card']->progressText();
                    $body  = $result['milestones']->isNotEmpty()
                        ? 'Premio desbloqueado: ' . $result['milestones']->pluck('reward_title')->join(', ')
                        : null;

                    Notification::make()->title($title)->body($body)->success()->send();
                })
                ->visible(fn () => ! $this->record->is_completed),

            Action::make('redeem')
                ->label('Canjear Premio')
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Canjear premio')
                ->modalDescription(fn () => 'Se registrará el canje y se reiniciará el ciclo.')
                ->action(function () {
                    app(LoyaltyService::class)->redeemReward($this->record, auth()->guard()->user()?->name);
                    $this->refreshFormData([]);
                    Notification::make()->title('Premio canjeado — ciclo reiniciado')->success()->send();
                })
                ->visible(fn () => $this->record->is_completed),

            Action::make('google_wallet')
                ->label('Google Wallet')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->googlePass()?->addToWalletUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->google_pass_id),

            Action::make('apple_wallet_download')
                ->label('Apple Wallet')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('loyalty.apple', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->apple_pass_id),

            Action::make('generate_apple_pass')
                ->label('Generar Apple Pass')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generar Apple Wallet Pass')
                ->modalDescription('Se generará el archivo .pkpass para esta tarjeta. El cliente podrá descargarlo desde su landing.')
                ->action(function () {
                    $ok = app(LoyaltyService::class)->generateApplePass($this->record);
                    $this->refreshFormData([]);

                    if ($ok) {
                        Notification::make()->title('Apple Pass generado correctamente')->success()->send();
                    } else {
                        Notification::make()->title('Apple Wallet no está configurado')->body('Verifica las variables MOBILE_PASS_APPLE_* en el .env')->danger()->send();
                    }
                })
                ->visible(fn () => ! $this->record->apple_pass_id && app(AppleWalletService::class)->isConfigured()),

            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([

                // ── Columna izquierda: Titular ────────────────────────────
                Section::make('Titular')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('holder_name')
                            ->label('Nombre')
                            ->weight('bold'),

                        TextEntry::make('holder_email')
                            ->label('Email')
                            ->placeholder('—'),

                        TextEntry::make('loyaltyProgram.business.name')
                            ->label('Negocio'),

                        TextEntry::make('loyaltyProgram.name')
                            ->label('Programa')
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(1)
                    ->columnSpan(1),

                // ── Columna derecha: Estado y progreso ────────────────────
                Section::make('Progreso')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        TextEntry::make('stamps_progress')
                            ->label('Visitas')
                            ->state(fn (LoyaltyCard $record) => $record->progressText())
                            ->badge()
                            ->color(fn (LoyaltyCard $record) => $record->is_completed ? 'success' : 'primary'),

                        TextEntry::make('is_completed')
                            ->label('Estado')
                            ->formatStateUsing(fn ($state) => $state ? 'Lista para canjear' : 'En progreso')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),

                        TextEntry::make('next_reward')
                            ->label('Próximo Premio')
                            ->state(fn (LoyaltyCard $record) => $record->nextRewardText())
                            ->badge()
                            ->color(fn (LoyaltyCard $record) => $record->is_completed ? 'success' : 'warning')
                            ->columnSpanFull(),

                        TextEntry::make('last_stamp_at')
                            ->label('Último Sello')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('completed_at')
                            ->label('Completada el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                // ── Premios del programa (tabla) ──────────────────────────
                Section::make('Premios del Programa')
                    ->icon('heroicon-o-trophy')
                    ->schema([
                        RepeatableEntry::make('program_rewards')
                            ->label('')
                            ->state(function (LoyaltyCard $record) {
                                $program   = $record->loyaltyProgram;
                                $collected = $record->stamps_collected;

                                $rows = $program->milestones->map(fn ($m) => [
                                    'visit'      => $m->stamp_count,
                                    'reward'     => $m->reward_title,
                                    'repeatable' => $m->is_repeatable ? 'Sí' : '—',
                                    'status'     => $collected >= $m->stamp_count ? 'Obtenido' : 'Pendiente',
                                ])->toArray();

                                $rows[] = [
                                    'visit'      => $program->total_stamps,
                                    'reward'     => $program->reward_title . ' · Premio final',
                                    'repeatable' => '—',
                                    'status'     => $collected >= $program->total_stamps ? 'Obtenido' : 'Pendiente',
                                ];

                                return $rows;
                            })
                            ->schema([
                                TextEntry::make('visit')
                                    ->label('Visita #')
                                    ->badge()
                                    ->color('primary'),

                                TextEntry::make('reward')
                                    ->label('Premio'),

                                TextEntry::make('repeatable')
                                    ->label('Repetible'),

                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state) => $state === 'Obtenido' ? 'success' : 'gray'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                // ── Historial de sellos (tabla) ───────────────────────────
                Section::make('Historial de Sellos')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        RepeatableEntry::make('stamp_history')
                            ->label('')
                            ->state(function (LoyaltyCard $record) {
                                return $record->stampTransactions()
                                    ->latest()
                                    ->take(25)
                                    ->get()
                                    ->map(fn ($t) => [
                                        'date'   => $t->created_at->format('d/m/Y H:i'),
                                        'added'  => '+' . $t->stamps_added,
                                        'total'  => $t->stamps_after . ' acumulados',
                                    ])
                                    ->toArray();
                            })
                            ->schema([
                                TextEntry::make('date')->label('Fecha'),
                                TextEntry::make('added')->label('Sellos')->badge()->color('success'),
                                TextEntry::make('total')->label('Total'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
