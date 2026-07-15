<?php

namespace App\Filament\Resources\Reels\Tables;

use App\Jobs\ProcessReel;
use App\Models\Reel;
use App\Models\Trip;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReelsTable
{
    private const NON_TERMINAL_STATUSES = [
        Reel::STATUS_PENDING,
        Reel::STATUS_DOWNLOADING,
        Reel::STATUS_TRANSCRIBING,
        Reel::STATUS_EXTRACTING,
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Reel::STATUS_DONE => 'success',
                        Reel::STATUS_FAILED => 'danger',
                        Reel::STATUS_PENDING => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('shortcode')
                    ->url(fn (Reel $record) => $record->url)
                    ->openUrlInNewTab(),
                TextColumn::make('places_count')
                    ->counts('places')
                    ->label('Places'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->poll(fn () => Reel::query()->whereIn('status', self::NON_TERMINAL_STATUSES)->exists() ? '5s' : null)
            ->headerActions([
                Action::make('addReels')
                    ->label('Add reels')
                    ->schema([
                        Select::make('trip_id')
                            ->label('Trip')
                            ->options(fn () => Trip::where('user_id', auth()->id())->pluck('name', 'id'))
                            ->required(),
                        Textarea::make('urls')
                            ->label('Reel URLs (one per line)')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $urls = collect(preg_split('/\s+/', $data['urls']))
                            ->filter(fn ($url) => str_contains($url, 'instagram.com'))
                            ->unique();

                        foreach ($urls as $url) {
                            $reel = Reel::firstOrCreate(
                                ['shortcode' => Reel::shortcodeFromUrl($url)],
                                ['url' => $url, 'status' => Reel::STATUS_PENDING, 'trip_id' => $data['trip_id']]
                            );

                            if ($reel->wasRecentlyCreated || $reel->status === Reel::STATUS_FAILED) {
                                ProcessReel::dispatch($reel);
                            }
                        }
                    }),
            ])
            ->recordActions([
                Action::make('retry')
                    ->visible(fn (Reel $record) => $record->status === Reel::STATUS_FAILED)
                    ->action(fn (Reel $record) => ProcessReel::dispatch($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
