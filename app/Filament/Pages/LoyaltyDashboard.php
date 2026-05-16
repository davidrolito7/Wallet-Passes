<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LoyaltyStatsWidget;
use App\Filament\Widgets\TopProgramsWidget;
use Filament\Pages\Dashboard;

class LoyaltyDashboard extends Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Panel de Lealtad';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            LoyaltyStatsWidget::class,
            TopProgramsWidget::class,
        ];
    }
}
