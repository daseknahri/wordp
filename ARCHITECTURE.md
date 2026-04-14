# kuchniatwist Architecture

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
- `includes/class-kuchnia-twist-publisher.php` is the orchestration shell and module delegator
- `includes/class-kuchnia-twist-publisher-base.php` centralizes shared plugin constants
- `includes/class-kuchnia-twist-publisher-module.php` provides the module base used by all feature areas
- `includes/Settings/` owns settings defaults, normalization, and content-machine settings
- `includes/Contracts/` owns canonical package/channel normalization, compatibility mirrors, and contract drift helpers
- `includes/Jobs/` owns job queries, job events, filters, pagination, and normalized job records
- `includes/Admin/` owns selected-job review helpers, notice rendering, and admin presentation helpers
- `includes/Publishing/` owns canonical publish payload resolution, page-break sanitizing, and publish-time validation
- `includes/Rest/` owns worker-facing REST routes plus publish/progress/complete/fail endpoints
- `includes/Quality/` owns editorial quality summaries and selected-job review snapshots

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

Current internal structure:

- `autopost/index.mjs`
  - worker bootstrap and orchestration entrypoint
- `autopost/src/runtime/`
  - config, env parsing, shared utilities
- `autopost/src/runtime/settings.mjs`
  - content-machine + runtime settings assembly helpers
- `autopost/src/runtime/normalizers.mjs`
  - runtime normalization helpers
- `autopost/src/runtime/text.mjs`
  - text cleanup and slug helpers
- `autopost/src/runtime/lists.mjs`
  - list formatting + similarity helpers
- `autopost/src/runtime/html.mjs`
  - HTML normalization helpers
- `autopost/src/runtime/job-utils.mjs`
  - job-level helpers + distribution checks
- `autopost/src/runtime/time.mjs`
  - timestamp helpers
- `autopost/src/runtime/core.mjs`
  - core runtime helpers
- `autopost/src/runtime/worker-runner.mjs`
  - worker main-loop orchestration
- `autopost/src/content/`
  - content-type profile definitions
  - article-stage generation helpers
- `autopost/src/content/prompts.mjs`
  - publication profile and prompt assembly helpers
- `autopost/src/content/internal-links.mjs`
  - internal link target helpers for article content
- `autopost/src/content/social-signals.mjs`
  - article-derived social signal helpers for Facebook prompt and quality logic
- `autopost/src/content/quality-utils.mjs`
  - title/excerpt/SEO scoring helpers used by article quality checks
- `autopost/src/content/helpers.mjs`
  - composition layer for content pipeline helpers
- `autopost/src/content/page-flow.mjs`
  - page-flow normalization and label/summary extraction helpers
- `autopost/src/content/article-text.mjs`
  - opening-paragraph extraction helpers used by article quality checks
- `autopost/src/content/article-quality.mjs`
  - article-stage quality checks and pagination diagnostics
- `autopost/src/content/image-prompts.mjs`
  - image prompt assembly helpers
- `autopost/src/content/image-assets.mjs`
  - image generation and upload helpers
- `autopost/src/content/pages.mjs`
  - page merge and internal-link helpers
- `autopost/src/content/page-splitting.mjs`
  - page splitting and content extraction helpers
- `autopost/src/content/generation-runner.mjs`
  - article + social candidate generation runner
- `autopost/src/channels/`
  - channel adapter profile definitions
  - Facebook social-stage generation helpers
- `autopost/src/channels/facebook/`
  - Facebook candidate-pool quality summaries
  - Facebook winner selection and fallback-pack helpers
- `autopost/src/channels/facebook/angles.mjs`
  - Facebook angle normalization and sequencing helpers
- `autopost/src/channels/facebook/adapter-utils.mjs`
  - Facebook adapter normalization and post-message helpers
- `autopost/src/channels/facebook/helpers.mjs`
  - Facebook helper composition (adapter + publish + distribution wiring)
- `autopost/src/channels/facebook/api.mjs`
  - Facebook publish API helpers
- `autopost/src/channels/facebook/distribution.mjs`
  - page selection + legacy distribution helpers
- `autopost/src/channels/facebook/fingerprints.mjs`
  - social fingerprint helpers
- `autopost/src/channels/facebook/publish.mjs`
  - Facebook publishing flow helpers (tracked URLs + post/comment sequencing)
- `autopost/src/channels/facebook/social-variants.mjs`
  - Facebook hook/caption scoring and social-signal helpers
- `autopost/src/jobs/`
  - WordPress job claim/progress/heartbeat helpers
- `autopost/src/jobs/wordpress-jobs.mjs`
  - WordPress job client used by the worker runtime
- `autopost/src/jobs/helpers.mjs`
  - job orchestration composition helpers
- `autopost/src/jobs/package-generator.mjs`
  - Canonical content package + channel draft generation
- `autopost/src/jobs/orchestrator.mjs`
  - Job claim, generation, publish, and failure orchestration
- `autopost/src/quality/`
  - package/channel quality summaries
  - contract drift and readiness helpers
- `autopost/src/quality/validator-summary.mjs`
  - validator summary merge + quality gate assertions
- `autopost/src/contracts/helpers.mjs`
  - composition layer for contract helpers
- `autopost/src/quality/article-repair.mjs`
  - article-stage repair guidance
- `autopost/src/quality/article-validator.mjs`
  - article validation rules
- `autopost/src/quality/entrypoints.mjs`
  - quality + social selection entrypoints
- `autopost/src/contracts/`
  - contract-facing re-exports and shared shape helpers
- `autopost/src/contracts/generated-payload.mjs`
  - generated payload normalization and legacy parsing helpers
- `autopost/src/contracts/channel-adapters.mjs`
  - canonical content packages + channel adapter normalization helpers
- `autopost/src/contracts/channel-drafts.mjs`
  - Pinterest + Facebook Groups draft builders
- `autopost/src/clients/`
  - WordPress/Facebook/OpenAI request helpers

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
  - `channels.facebook_groups`
    - dormant manual-share draft scaffold
    - legacy `group_share_kit` mirror source
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
Future AI idea generation / autopilot posting is still dormant in this phase. Pinterest and Facebook Groups are scaffolded at the contract level, but Facebook Pages are the only live social adapter right now.

There is now also a dormant strict-contract policy path:

- `strict_contract_mode = off` by default
- old jobs stay backward-compatible
- new typed-contract jobs surface drift as warnings today
- later, the same drift checks can be escalated to blockers without changing the package/adapter model again

Typed jobs are explicitly flagged inside `content_machine.contracts` (`typed_contract_job`/`legacy_job` plus fallback hints), so contract drift checks only run for new typed jobs while older legacy payloads keep loading safely.

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

- keep plugin modules isolated and callable via the main orchestrator
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
