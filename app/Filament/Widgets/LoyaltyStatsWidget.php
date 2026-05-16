<?php

namespace App\Filament\Widgets;

use App\Models\LoyaltyCard;
use App\Models\RewardRedemption;
use App\Models\StampTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoyaltyStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalCards = LoyaltyCard::count();
        $activeCards = LoyaltyCard::where('is_completed', false)->count();
        $completedCards = LoyaltyCard::where('is_completed', true)->count();
        $totalStamps = StampTransaction::sum('stamps_added');
        $redemptions = RewardRedemption::count();
        $recentCards = LoyaltyCard::where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Tarjetas Activas', $activeCards)
                ->description('En progreso')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),

            Stat::make('Tarjetas Completadas', $completedCards)
                ->description('Listas para canjear')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Sellos Acumulados', number_format($totalStamps))
                ->description('Total histórico')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Premios Canjeados', $redemptions)
                ->description('Total histórico')
                ->descriptionIcon('heroicon-m-gift')
                ->color('info'),

            Stat::make('Total de Tarjetas', $totalCards)
                ->description('Todas las plataformas')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('gray'),

            Stat::make('Nuevas esta semana', $recentCards)
                ->description('Últimos 7 días')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
