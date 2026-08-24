# Reel2Trip Agent Guidelines

**Version:** 1.0  
**Last Updated:** August 24, 2026  
**Project:** Reel2Trip - Instagram Reel Place Extraction & Trip Planning  
**Stack:** Laravel 13 + Vue 3 + Filament 4 + Ollama + Whisper.cpp

---

## 1. Project Context & Architecture

### Purpose
Extract places from Instagram reels, organize them into cities, enrich with coordinates/ratings/hours, and enable trip planning.

### Tech Stack Overview
```
Backend:     Laravel 13 (PHP 8.3) + Queue (database/redis)
Frontend:    Vue 3 + Tailwind CSS + Vite 8
Admin Panel: Filament 4 (custom Amber theme)
AI/ML:       Ollama (qwen2.5:3b) + Whisper.cpp (transcription)
Database:    SQLite (dev) → MySQL/PostgreSQL (prod)
Testing:     PHPUnit 12.5 + Pest 4.7
Code Quality: Larastan + Pint
```

### Core Processing Pipeline
```
ProcessReel Job (Idempotent, 3 retries with exponential backoff)
├── InstagramDownloader (yt-dlp)     → Download video + caption
├── WhisperTranscriber (ffmpeg)       → Extract transcript
├── ReelPlaceExtractor (Ollama JSON)  → Parse places into JSON
└── EnrichPlace Job (per place)       → Google Places API → coords, rating, hours
```

---

## 2. Mandatory Rules & Patterns

### 2.1 Queue Job Design (CRITICAL)
- ALL processing in queued jobs, NEVER in request cycle
- Idempotent stage execution (safe to retry)
- Exponential backoff: 3 retries at 60s, 300s, 900s
- Delete previous results on retry to prevent duplicates

### 2.2 Ollama Integration (JSON MODE ONLY)
- Always use JSON format mode for reliable parsing
- Set OLLAMA_KEEP_ALIVE=5m to prevent model unload
- Use structured prompts with JSON output requirement
- Never use free-form text generation

### 2.3 Database Migrations (REQUIRED)
- ALL schema changes via Laravel migrations
- Use transactions for multi-step operations
- Add indexes for common queries
- Rollback capability for all changes

### 2.4 Filament Admin Panel (PREFERRED)
- Use resources for ALL CRUD operations
- Create List/Create/Edit pages per resource
- Add filters, actions, and bulk operations
- Follow Filament theme patterns

### 2.5 Error Handling (CUSTOM EXCEPTIONS)
- Create custom exception classes
- Log errors with full context
- Implement graceful degradation where possible
- Never hardcode error messages

---

## 3. Code Standards

### PHP Style (PSR-12)
- Type hints required everywhere
- Use camelCase for methods/variables
- Use snake_case for database columns
- Use PascalCase for class names

### Testing (Minimum 80% coverage)
- Write tests BEFORE implementation (TDD)
- Use Pest syntax when possible
- Mock external APIs (Ollama, Google Places)
- Test database runs on :memory: SQLite

### Common Mistakes to Avoid
1. Processing in request cycle (use jobs)
2. Hardcoding credentials (use .env)
3. Unreliable Ollama parsing (use JSON mode)
4. Missing transaction wrapping (use DB::transaction)
5. Synchronous FFmpeg calls (use Process with async)
6. Ignoring Filament conventions (always use resources)
7. Direct SQL updates (use Eloquent)
8. Not handling rate limits (use backoff/delay)

---

## 4. Debugging & Troubleshooting

### Check Queue Status
```bash
php artisan queue:failed
php artisan queue:work --verbose
php artisan queue:retry
```

### Check Ollama
```bash
curl http://localhost:11434/api/tags
ollama ps
```

### Check Database
```bash
php artisan migrate:status
php artisan tinker
```

---

## 5. Learning & Updates

When you discover patterns or solve issues:
```
/learn: [description of what you learned]
```

This automatically updates AGENTS.md for future sessions.

