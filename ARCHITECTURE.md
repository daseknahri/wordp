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
- content machine presets and cadence settings
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
- generate canonical article packages from code-owned typed prompt presets
- run a single repair pass when validation fails
- generate missing images
- publish immediately when a manual typed job has no future schedule
- wait for a job's exact `publish_on` timestamp when it is scheduled for later
- generate platform-specific channel outputs from the canonical package
- publish the Facebook Page post
- add the first comment with the tracked link
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
- selected job detail with snapshots, outputs, media, retry actions, quality score, and failed checks
- selected job detail with prompt version, publication profile, preset, validator summary, and per-variant hook angles
- per-job ops timeline
- filtered CSV export for reporting/debugging

This keeps WordPress as the source of truth for editorial and operational state.

### Content machine

The active AI lane is now a typed content engine centered around one canonical article package plus channel adapters:

- `publication_profile`
  - publication role
  - voice brief
  - consolidated guardrails
- `manual typed composer`
  - `recipe`
    - dish name
  - `food_fact`
    - working title / topic seed
  - optional final title override
  - blog image
  - Facebook image
  - selected Facebook pages
  - optional per-job publish time
- `article engine`
  - returns one canonical `content_package`
  - `content_type`
  - `topic_seed`
  - title / slug / excerpt / SEO description
  - `content_html`
  - `content_pages`
  - `page_flow`
  - image prompt / alt text
  - typed data such as `recipe` only when required by the content type
- `content-type profiles`
  - `recipe`
    - recipe validation
    - recipe multipage rendering
  - `food_fact`
    - editorial validation
    - editorial multipage rendering
  - `food_story`
    - dormant compatibility path
- `channel adapters`
  - `channels.facebook`
    - candidate pool
    - selected variants
    - page-level distribution records
    - final post/comment messages
  - `channels.pinterest`
    - dormant draft scaffold only for now
- `image handling`
  - uploaded first, generate only missing slot(s)
  - manual only
- `facebook page library`
  - label
  - page id
  - page access token
  - active toggle
- `quality model`
  - package/article quality
  - channel-specific quality
  - overall readiness
  - `block` for hard integrity failures
  - `warn` for softer quality issues that should stay visible without stopping publish
- `cadence`
  - generate now
  - publish immediately if no future time is set
  - wait for the exact per-job `publish_on` timestamp otherwise
- `models`
  - primary text model
  - image model
  - one internal repair pass

WordPress stores the operator-facing guidance and target pages for each job. The worker owns the runtime prompt contract, produces the canonical content package, then builds channel-specific output from that package.
The worker persists final assembled Facebook post messages, page-level first-comment messages, package quality, and channel quality so wp-admin can review the exact planned/posted copy instead of only raw fragments.
Future AI idea generation / autopilot posting is still dormant in this phase. Pinterest is scaffolded at the contract level, but Facebook is the only live social adapter right now.

### Scheduling model

Jobs now move through these main states:

- `queued`
- `generating`
- `scheduled`
- `publishing_blog`
- `publishing_facebook`
- `completed`
- `failed`
- `partial_failure`

Generated jobs use `publish_on` as the exact UTC publish timestamp.

- normal jobs generate now and either publish immediately or wait for an exact future timestamp
- retries bypass future scheduling and become due immediately
- operator controls can publish now, reschedule a job, or cancel a scheduled release

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
3. Stabilize the content machine and daily scheduling in production.
4. Start phase-2 plugin cleanup by modularizing the plugin.

## Rule for future work

Do not move frontend presentation into the plugin.
Do not move queue or publishing logic into the theme.
Do not let the worker become the source of truth for editorial state.
