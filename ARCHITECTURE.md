# Kuchnia Twist Architecture

This file captures the current project shape so the implementation plan survives session loss.

## Current boundaries

### Theme

Location: `wordpress/wp-content/themes/kuchnia-twist/`

Responsibility:

- public-facing design
- homepage, archive, search, article, and trust-page presentation
- editorial trust signals
- reading experience helpers

The theme should stay responsible for presentation and content framing only.

### Plugin

Location: `wordpress/wp-content/plugins/kuchnia-twist-publisher/`

Responsibility:

- wp-admin publishing UI
- publishing settings
- job creation and retry flow
- queue storage and job state
- internal REST endpoints used by the worker

The plugin is the control plane for the publishing system.

It now also owns:

- worker heartbeat status
- per-job ops events
- filtered job export from wp-admin
- seeded launch-page refresh tracking via stored seed hashes

Current structure:

- `kuchnia-twist-publisher.php` is the thin plugin loader
- `includes/class-kuchnia-twist-publisher.php` holds the current implementation

Next cleanup step:

- split the class file into smaller `includes/` modules after behavior is stable

### Worker

Location: `autopost/`

Responsibility:

- claim queued jobs from WordPress
- generate article payloads
- generate missing images
- publish the WordPress article
- publish the Facebook Page post
- add the first comment with the tracked link
- save the manual group-share kit
- send worker heartbeat snapshots back to WordPress

The worker is the execution engine. It should stay stateless except for short-lived runtime state.

## Operations model

### Worker heartbeat status

The worker reports to:

- `POST /wp-json/kuchnia-twist/v1/worker/heartbeat`

WordPress stores the latest snapshot in:

- `kuchnia_twist_worker_status`

Tracked fields:

- `worker_version`
- `enabled`
- `run_once`
- `poll_seconds`
- `startup_delay_seconds`
- `config_ok`
- `last_seen_at`
- `last_loop_result`
- `last_job_id`
- `last_job_status`
- `last_error`

The wp-admin dashboard treats the worker as stale when no heartbeat arrives within `max(90 seconds, poll_seconds * 3)`.

### Job events table

The plugin keeps a lightweight ops log in:

- `wp_kuchnia_twist_job_events`

Each row stores:

- `job_id`
- `event_type`
- `status`
- `stage`
- `message`
- `context_json`
- `created_at`

This is intentionally compact. It should store short operational context only, not full article HTML or large generated payloads.

### Operator console direction

The publisher screen is now an operator console:

- system status strip for worker/OpenAI/Facebook readiness
- paginated job list with search and filters
- selected job detail with snapshots, outputs, media, and retry actions
- per-job ops timeline
- filtered CSV export for reporting/debugging

This keeps WordPress as the source of truth for editorial and operational state.

### Seeded trust pages

The launch trust pages (`About`, `Contact`, `Privacy Policy`, `Cookie Policy`, and `Editorial Policy`) are now tracked with a stored seed hash.

- untouched seeded pages can be refreshed on later plugin versions
- manually edited pages are left alone once their current content no longer matches the stored seed hash
- older placeholder-style pages can still be refreshed through marker-based detection

### Theme metadata and schema

The theme owns the public metadata layer for launch.

- canonical URLs are emitted per page, including paginated archive/feed variants
- Open Graph and Twitter tags reflect the current page and preferred image when one exists
- search result pages are marked `noindex` through WordPress `wp_robots`
- JSON-LD emits a graph for the publisher, website, breadcrumbs, and the current page/article/recipe entity
- schema images use richer `ImageObject` data when real attachment metadata is available
- publisher schema can expose editorial/business contact points and social profiles when those settings are configured

## What does not need a rethink now

- WordPress remains the editorial control panel.
- The custom theme remains separate from publishing logic.
- The plugin remains the queue and settings layer.
- The worker remains the async executor.
- Coolify remains the deployment target.

This architecture is still the right one for the current phase.

## What should be improved next, without changing direction

### Theme track

- finish archive/search/index polish
- keep improving reading paths and trust cues
- validate responsive behavior in browser after each visual pass

### Plugin track

- split the single plugin file into `includes/` modules when the current production hardening pass is stable
- suggested split:
  - `class-settings.php`
  - `class-admin-pages.php`
  - `class-jobs-repository.php`
  - `class-job-events-repository.php`
  - `class-rest-api.php`
  - `class-media.php`
  - `class-bootstrap.php`
- keep the plugin entry file thin

### Worker track

- keep provider integrations isolated in helper functions or modules
- keep heartbeat/reporting separate from generation/publishing logic when modularization starts
- if more channels are added later, move provider-specific publishing into separate files

## Phase order

1. Run production hardening and validate the live pipeline.
2. Use heartbeat + ops log + export to catch real runtime issues.
3. Start phase-2 plugin cleanup by modularizing the plugin.
4. Extend the content machine only after the codebase is easier to maintain.

## Rule for future work

Do not move frontend presentation into the plugin.
Do not move queue or publishing logic into the theme.
Do not let the worker become the source of truth for editorial state.
