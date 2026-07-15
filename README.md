# reel2trip — Instagram reels → structured trip plan

Paste reel URLs, get back a database of places (food / sights / tips) with
coordinates, ratings, price levels and opening hours, ready to cluster into
day plans.

## Pipeline

```
POST /reels (paste URLs)
        │
        ▼
ProcessReel job (queued, retries with backoff)
  1. InstagramDownloader   yt-dlp → video + caption
  2. WhisperTranscriber    ffmpeg → mp3 → Whisper API → transcript
  3. ClaudePlaceExtractor  caption+transcript → Claude → JSON places
        │
        ▼ (one per place)
EnrichPlace job
  4. GooglePlacesEnricher  name+city → lat/lng, rating, price, hours
```

## Setup

1. Drop these files into a fresh Laravel 11/12 app (paths match the standard
   structure). Merge `config/services-snippet.php` into `config/services.php`
   and `.env.example-snippet` into your `.env`.

2. System dependencies on the box that runs the queue worker:

   ```bash
   sudo apt install ffmpeg
   pipx install yt-dlp        # or: pip install -U yt-dlp
   ```

3. Migrate and seed your cities:

   ```bash
   php artisan migrate
   php artisan tinker
   >>> TripCity::create(['name' => 'Lisbon',    'country' => 'Portugal', 'days' => 3]);
   >>> TripCity::create(['name' => 'Porto',     'country' => 'Portugal', 'days' => 2]);
   >>> TripCity::create(['name' => 'Barcelona', 'country' => 'Spain',    'days' => 4]);
   ```

4. Run the worker:

   ```bash
   php artisan queue:work --timeout=300
   ```

5. POST reel URLs (one per line) to `/reels`, then watch
   `/api/reels` and `/api/cities/{id}/places` fill up.

## API keys

| Key | Used for | Notes |
|---|---|---|
| `ANTHROPIC_API_KEY` | place extraction | pennies per reel |
| `OPENAI_API_KEY` | Whisper transcription | ~$0.006/min of audio |
| `GOOGLE_PLACES_API_KEY` | enrichment | enable Places API; free tier covers a trip's worth easily |
| `INSTAGRAM_COOKIES_FILE` | optional | Netscape cookies export; helps yt-dlp past login walls |

## Design decisions worth knowing

- **One idempotent job, not a chain.** Each stage checks whether its output
  already exists, so a retry after an IG rate-limit doesn't re-pay for
  Whisper/Claude calls that already succeeded. Extraction wipes and rewrites
  its places on retry so you never get duplicates.
- **`city_guess` vs `trip_city_id`.** The extractor records what the reel
  *said*; the job then tries an exact match against your seeded cities.
  Unmatched places keep their guess so you can assign them manually.
- **Tips are places too** (`category = tip`, no enrichment). They show up in
  the city list next to the food and sights, which is where you want them
  when planning a day.
- **Unenriched places are left visible**, not errored — Google sometimes
  can't find "that blue door restaurant", and a human fixes that in seconds.

## What's deliberately not here yet (your phase 2/3)

- **Frame OCR** — `ffmpeg -i reel.mp4 -vf fps=0.5 frames/%03d.png` +
  Tesseract, write result to `reels.ocr_text`. `combinedText()` already
  includes it, so the extractor picks it up with zero further changes.
- **Day planner** — with lat/lng in hand: k-means (or plain greedy
  nearest-neighbor) into `days` clusters per city, write cluster index to
  `places.planned_day`, sort within day by opening hours.
- **Map view** — Leaflet + OSM tiles reading `/api/cities/{id}/places`.

## Rate-limit etiquette

Keep it personal-use: your own saved reels, a handful at a time, with the
built-in sleeps and backoff. Downloading via yt-dlp is against Instagram's
ToS, so don't turn this endpoint into a public service.
