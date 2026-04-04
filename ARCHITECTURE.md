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

The worker is the execution engine. It should stay stateless except for short-lived runtime state.

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

- split the single plugin file into `includes/` modules when phase 2 starts
- suggested split:
  - `class-settings.php`
  - `class-admin-pages.php`
  - `class-jobs-repository.php`
  - `class-rest-api.php`
  - `class-media.php`
  - `class-bootstrap.php`
- keep the plugin entry file thin

### Worker track

- keep provider integrations isolated in helper functions or modules
- if more channels are added later, move provider-specific publishing into separate files

## Phase order

1. Finish public blog polish.
2. Validate local and Coolify deployment again.
3. Start phase-2 plugin cleanup by modularizing the plugin.
4. Extend the content machine only after the codebase is easier to maintain.

## Rule for future work

Do not move frontend presentation into the plugin.
Do not move queue or publishing logic into the theme.
Do not let the worker become the source of truth for editorial state.
