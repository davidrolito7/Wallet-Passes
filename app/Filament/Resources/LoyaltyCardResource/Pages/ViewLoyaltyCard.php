<?php

namespace App\Filament\Resources\LoyaltyCardResource\Pages;

use App\Filament\Resources\LoyaltyCardResource;
use App\Models\LoyaltyCard;
use App\Services\LoyaltyService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
                ->action(function () {
                    $result = app(LoyaltyService::class)->addStamp($this->record, 1, null, auth()->user()?->name);
                    $this->refreshFormData([]);

                    $title = 'Sello agregado — ' . $result['card']->progressText();
                    $body = null;

                    if ($result['milestones']->isNotEmpty()) {
                        $body = '🎁 Premio desbloqueado: ' . $result['milestones']->pluck('reward_title')->join(', ');
                    }

                    Notification::make()->title($title)->body($body)->success()->send();
                })
                ->visible(fn () => ! $this->record->is_completed),

            Action::make('redeem')
                ->label('Canjear Premio')
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    app(LoyaltyService::class)->redeemReward($this->record, auth()->user()?->name);
                    $this->refreshFormData([]);
                    Notification::make()->title('Premio canjeado — ciclo reiniciado')->success()->send();
                })
                ->visible(fn () => $this->record->is_completed),

            Action::make('google_wallet')
                ->label('Ver en Google Wallet')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->googlePass()?->addToWalletUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->google_pass_id),

            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Titular')->schema([
                TextEntry::make('holder_name')->label('Nombre'),
                TextEntry::make('holder_email')->label('Email'),
                TextEntry::make('loyaltyProgram.business.name')->label('Negocio'),
                TextEntry::make('loyaltyProgram.name')->label('Programa'),
            ])->columns(2),

            Section::make('Progreso')->schema([
                TextEntry::make('stamps_progress')
                    ->label('Sellos')
                    ->state(fn (LoyaltyCard $record) => $record->progressText())
                    ->badge()
                    ->color(fn (LoyaltyCard $record) => $record->is_completed ? 'success' : 'primary'),

                TextEntry::make('next_reward')
                    ->label('Próximo Premio')
                    ->state(fn (LoyaltyCard $record) => $record->nextRewardText())
                    ->badge()
                    ->color(fn (LoyaltyCard $record) => $record->is_completed ? 'success' : 'warning'),

                TextEntry::make('is_completed')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => $state ? 'Lista para canjear' : 'En progreso')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextEntry::make('stamp_visual')
                    ->label('Visualización  (★ = premio)')
                    ->state(fn (LoyaltyCard $record) => $record->stampVisual())
                    ->fontFamily('mono')
                    ->columnSpanFull(),

                TextEntry::make('last_stamp_at')
                    ->label('Último Sello')
                    ->dateTime('d/m/Y H:i'),

                TextEntry::make('completed_at')
                    ->label('Completada el')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])->columns(3),

            Section::make('Premios del Programa')->schema([
                TextEntry::make('milestones_list')
                    ->label('')
                    ->state(function (LoyaltyCard $record) {
                        $program    = $record->loyaltyProgram;
                        $milestones = $program->milestones;
                        $collected  = $record->stamps_collected;

                        $lines = [];

                        if ($milestones->isNotEmpty()) {
                            foreach ($milestones as $m) {
                                $done    = $collected >= $m->stamp_count;
                                $marker  = $done ? '✓' : '○';
                                $repeat  = $m->is_repeatable ? '  (repetible)' : '';
                                $lines[] = "{$marker}  Sello #{$m->stamp_count}: {$m->reward_title}{$repeat}";
                            }
                            $lines[] = '';
                        }

                        $doneMain = $collected >= $program->total_stamps;
                        $marker   = $doneMain ? '★' : '☆';
                        $lines[]  = "{$marker}  Sello #{$program->total_stamps} (final): {$program->reward_title}";

                        return implode("\n", $lines);
                    })
                    ->columnSpanFull(),
            ])->collapsible(),

            Section::make('Historial de Sellos')->schema([
                TextEntry::make('stamp_history')
                    ->label('')
                    ->state(function (LoyaltyCard $record) {
                        $transactions = $record->stampTransactions()
                            ->latest()
                            ->take(20)
                            ->get();

                        if ($transactions->isEmpty()) {
                            return 'Sin sellos aún.';
                        }

                        return $transactions
                            ->map(fn ($t) => $t->created_at->format('d/m/Y H:i') . ' — +' . $t->stamps_added . ' (total: ' . $t->stamps_after . ')')
                            ->join("\n");
                    })
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
