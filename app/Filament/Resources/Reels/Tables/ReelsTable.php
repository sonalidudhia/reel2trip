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
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
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
                    })
                    ->icon(fn (string $state) => match ($state) {
                        Reel::STATUS_DONE => 'heroicon-m-check-circle',
                        Reel::STATUS_FAILED => 'heroicon-m-exclamation-triangle',
                        Reel::STATUS_PENDING => 'heroicon-m-clock',
                        default => 'heroicon-m-arrow-path',
                    })
                    ->sortable(),
                TextColumn::make('shortcode')
                    ->label('Reel')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition(IconPosition::After)
                    ->color('primary')
                    ->searchable()
                    ->url(fn (Reel $record) => $record->url)
                    ->openUrlInNewTab()
                    // The failure reason is the only thing you want to read on a
                    // failed row, so it rides along under the shortcode.
                    ->description(fn (Reel $record) => $record->error),
                TextColumn::make('trip.name')
                    ->label('Trip')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('places_count')
                    ->counts('places')
                    ->label('Places')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->tooltip(fn (Reel $record) => $record->created_at?->toDayDateTimeString())
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->emptyStateIcon('heroicon-o-film')
            ->emptyStateHeading('No reels yet')
            ->emptyStateDescription('Paste Instagram reel links with "Add reels" and the pipeline takes it from there.')
            ->poll(fn () => Reel::query()->whereIn('status', self::NON_TERMINAL_STATUSES)->exists() ? '5s' : null)
            ->headerActions([
                Action::make('addReels')
                    ->label('Add reels')
                    ->icon('heroicon-m-plus')
                    ->schema([
                        Select::make('trip_id')
                            ->label('Trip')
                            ->options(fn () => Trip::where('user_id', auth()->id())->pluck('name', 'id'))
                            ->default(fn () => Trip::where('user_id', auth()->id())->latest()->value('id'))
                            ->required(),
                        Textarea::make('urls')
                            ->label('Reel URLs (one per line)')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $urls = collect(preg_split('/\s+/', trim($data['urls'])))
                            ->filter(fn ($url) => str_contains($url, 'instagram.com'))
                            ->unique();

                        $queued = 0;
                        $alreadyDone = 0;

                        foreach ($urls as $url) {
                            $reel = Reel::firstOrCreate(
                                ['shortcode' => Reel::shortcodeFromUrl($url)],
                                ['url' => $url, 'status' => Reel::STATUS_PENDING, 'trip_id' => $data['trip_id']]
                            );

                            if ($reel->wasRecentlyCreated || $reel->status === Reel::STATUS_FAILED) {
                                ProcessReel::dispatch($reel);
                                $queued++;
                            } else {
                                $alreadyDone++;
                            }
                        }

                        if ($urls->isEmpty()) {
                            Notification::make()
                                ->title('No Instagram URLs found in that text')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($queued > 0
                                ? "Queued {$queued} reel(s) for processing"
                                : 'Nothing queued')
                            ->body($alreadyDone > 0 ? "{$alreadyDone} URL(s) were already processed (or in progress) and were skipped." : null)
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('retry')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
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
