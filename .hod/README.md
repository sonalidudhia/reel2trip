# Hodstack Configuration for Reel2Trip

This directory contains hodstack configuration for reel2trip project.

## Files

- **AGENTS.md** - Agent rules and project guidelines
- **config.yaml** - Hodstack configuration
- **skills/** - Reusable automated tasks
  - queue-process.sh - Process reels
  - place-enrich.sh - Enrich places
  - test-suite.sh - Run tests
  - db-migrate.sh - Database migrations
  - dev-setup.sh - Development setup
  - filament-crud.sh - Create admin resources

## Quick Start

```bash
# List available skills
hod list

# Process a reel
hod queue-process 1

# Run tests
hod test-suite

# Setup environment
hod dev-setup
```

## Documentation

See AGENTS.md for detailed project guidelines and patterns.
