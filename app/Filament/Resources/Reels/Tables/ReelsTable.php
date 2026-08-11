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
use Illuminate\Database\Eloquent\Builder;

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
                    }),
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
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray'),
                // Sorts on id, not created_at: a pasted batch of reels all share the
                // same second, and ordering by the timestamp would shuffle them.
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->tooltip(fn (Reel $record) => $record->created_at?->toDayDateTimeString())
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('id', $direction)),
            ])
            ->defaultSort('created_at', 'desc')
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
                        $skipped = [];

                        foreach ($urls as $url) {
                            $reel = Reel::firstOrCreate(
                                ['shortcode' => Reel::shortcodeFromUrl($url)],
                                ['url' => $url, 'status' => Reel::STATUS_PENDING, 'trip_id' => $data['trip_id']]
                            );

                            if ($reel->wasRecentlyCreated || $reel->status === Reel::STATUS_FAILED) {
                                ProcessReel::dispatch($reel);
                                $queued++;
                            } else {
                                $skipped[] = $reel->shortcode;
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
                                : 'Already in your list')
                            ->body($skipped === [] ? null : sprintf(
                                '%s %s already here, so nothing was re-run. Use "Re-process" on the row to run it again.',
                                implode(', ', $skipped),
                                count($skipped) === 1 ? 'is' : 'are',
                            ))
                            ->{$queued > 0 ? 'success' : 'info'}()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('retry')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->visible(fn (Reel $record) => $record->status === Reel::STATUS_FAILED)
                    ->action(fn (Reel $record) => ProcessReel::dispatch($record)),
                // Re-pasting a URL that's already here is deduped away, so this is
                // the only way to run a reel through an improved model or prompt.
                Action::make('reprocess')
                    ->label('Re-process')
                    ->icon('heroicon-m-sparkles')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Re-process this reel?')
                    ->modalDescription('The places already extracted from it are replaced by a fresh run. The video and transcript are reused, so this only re-runs the extraction.')
                    ->modalSubmitActionLabel('Re-process')
                    ->visible(fn (Reel $record) => $record->status === Reel::STATUS_DONE)
                    ->action(function (Reel $record): void {
                        $record->update(['status' => Reel::STATUS_PENDING, 'error' => null]);
                        ProcessReel::dispatch($record);

                        Notification::make()
                            ->title("Re-processing {$record->shortcode}")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
