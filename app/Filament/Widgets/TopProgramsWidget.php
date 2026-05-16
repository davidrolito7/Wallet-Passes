<?php

namespace App\Filament\Widgets;

use App\Models\LoyaltyProgram;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProgramsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Programas más activos';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LoyaltyProgram::withCount(['loyaltyCards', 'completedCards'])
                    ->with('business')
                    ->orderByDesc('loyalty_cards_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('business.name')->label('Negocio'),
                TextColumn::make('name')->label('Programa'),
                TextColumn::make('stamp_icon')
                    ->label('Icono')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'coffee' => '☕',
                        'star'   => '⭐',
                        'stamp'  => '🔵',
                        'heart'  => '❤️',
                        default  => '●',
                    }),
                TextColumn::make('loyalty_cards_count')->label('Tarjetas')->sortable(),
                TextColumn::make('completed_cards_count')->label('Completadas')->sortable(),
                TextColumn::make('reward_title')->label('Premio')->limit(30),
            ]);
    }
}
