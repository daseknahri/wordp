# Content Machine Extraction Map

## Goal

Keep one portable content-and-posting core that can be reused on future sites, while leaving WordPress, theme, branding, and launch-content behavior in a site adapter layer.

## Current Split

### Portable Core Candidates

- `autopost/src/content/*`
- `autopost/src/contracts/*`
- `autopost/src/quality/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/Contracts/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/Quality/*`

### Site / Control Plane Adapters

- `autopost/src/clients/wordpress.mjs`
- `autopost/src/jobs/wordpress-jobs.mjs`
- `autopost/src/channels/facebook/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/Site/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/Rest/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/Admin/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/Publishing/*`
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/includes/launch-content.php`

## New Seam Added

The worker now reads an explicit `content_machine.site_policy` object instead of hardcoding one site's internal-link shortcode, link library, journal label, and page-break marker.

Current site-policy responsibilities:

- publication name
- internal link minimum count
- internal link shortcode tag
- internal link library by content type
- journal follow-on label
- pagination marker

This keeps today's behavior unchanged for Kuchnia Twist while making future site overrides possible without rewriting the content core.

The WordPress plugin now also resolves page splitting, page joining, internal-link shortcode counting, shortcode registration, and minimum internal-link quality checks from the same site-policy surface instead of hardcoding `<!--nextpage-->` and `kuchnia_twist_link` in multiple validation paths.

The worker now keeps only generic internal-link fallback defaults. The real archive map, shortcode tag, and required internal-link count should come from the site adapter's `site_policy`, not from the shared worker core.

The worker now also reads an explicit `content_machine.platform_policy` object for transport and tracked-link behavior.

Current platform-policy responsibilities:

- WordPress REST namespace
- job lifecycle route templates
- media upload route template
- worker secret header name
- delivery tracking defaults such as `utm_source` and `utm_campaign_prefix`

This keeps the worker from hardcoding one plugin's REST shape in its client code.

The worker now also reads an explicit `content_machine.posting_policy` object for publish sequencing.

Current posting-policy responsibilities:

- primary publish action and stage
- primary executor key
- channel publish order
- channel enablement and stage names
- channel executor keys
- channel target requirements

This keeps the current "publish blog, then Facebook" behavior intact while moving that sequence out of hidden worker control flow and into an adapter-owned policy surface.

The current adapter policy can already describe dormant channels like `facebook_groups` and `pinterest` without forcing the worker core to hardcode them into its plan builder.

The posting plan now resolves step identity separately from executor identity. A site can keep a policy step like `facebook` while swapping the concrete executor key underneath it, which makes future channel adapters easier to plug in without changing the posting machine itself.

Dormant executor keys should still be registered explicitly. If a future site enables a channel before its executor exists, the worker should fail with a clear adapter-level error instead of a vague missing-step failure.

Queued jobs should carry their own `site_policy`, `platform_policy`, and `posting_policy` snapshot so future site-specific changes do not silently rewrite the execution rules of already-created jobs.

After a job is claimed, the worker now continues to use that job's own platform policy for progress, fail, complete, and publish callbacks instead of falling back to process-wide defaults. This matters if future sites share the worker core but expose different adapter routes or headers.

The worker also now has a first explicit delivery boundary in:

- `autopost/src/jobs/posting-machine.mjs`

That facade owns the current blog-publish + Facebook-publish flow, while the job orchestrator stays focused on claim, generation, validation, and handoff.

The worker now also routes concrete publish work through explicit posting executors in:

- `autopost/src/jobs/posting-executors.mjs`
- `autopost/src/jobs/wordpress-posting-executor.mjs`
- `autopost/src/channels/facebook/posting-executor.mjs`
- `autopost/src/jobs/posting-targets.mjs`

That keeps the posting machine focused on plan execution, lifecycle updates, and terminal state handling, while the site-specific publish details and target selection stay in adapter modules.

The social-generation and quality-review lanes now also consume a single lighter Facebook target profile in the reusable content path. Prompt building, candidate-pool generation, and package-quality scoring mostly rely on adapter-supplied target count and labels instead of carrying full selected-page payloads through the content machine.

The job orchestrator now follows that same boundary more closely. It mainly carries the lighter Facebook target profile for coverage and quality work, while raw selected-page arrays stay inside the adapter and publish path where they are actually needed.

The Facebook phase helper and Facebook posting executor now also share one adapter-provided distribution context for target resolution and legacy distribution seeding, instead of rebuilding that prep separately in the jobs layer.

The Facebook posting executor now also uses an adapter-provided distribution-result helper for final caption, URL, and post/comment state shaping, so the jobs layer no longer owns that Facebook-specific result format.

The Facebook publish helper now also owns the partial-failure summary text for page-level delivery failures, so the generic runtime no longer needs to understand Facebook's failed-page payload shape.

The posting machine now also keeps a generic `publication`, `deliveries`, and `targets` view alongside today's legacy mirrors. That lets new executors move toward adapter-agnostic state handoff without breaking the current WordPress and Facebook flow.

The job orchestrator now prefers that generic posting result view when a publish step completes, then falls back to today's legacy fields only for compatibility. That makes the generic contract part of the live handoff instead of just extra metadata.

The dormant Facebook Groups manual-share draft now also has a generic home under `deliveries.facebook_groups.draft`, while the old `group_share_kit` mirror still stays in place for compatibility with the current WordPress storage and review flow.

The worker's complete and fail callbacks now also derive their legacy WordPress payload mirrors from that generic `publication` / `deliveries` state in one place, instead of rebuilding Facebook-specific fields separately at each callback site.

The WordPress job transport now also accepts the generic `publication` / `deliveries` callback shape and derives the old callback fields from it before sending the request. That keeps the legacy field mapping inside the site adapter instead of forcing the worker core to speak WordPress-shaped payloads forever.

The orchestrator now also carries `publication`, `deliveries`, and `targets` through scheduled-progress, publish handoff, and outer failure reporting instead of only reconstructing generic state after publish results come back.

The generated-payload normalization lane now also funnels old top-level social mirrors through one `legacy_channel_mirrors` container before canonical sync. That keeps backward compatibility for older jobs while making the canonical `channels.*` containers the real source of truth for new machine work.

The initial blog-publish handoff now also sends the generic delivery shape into the WordPress adapter, so even the first publish step can stay generic-first while the plugin derives flat mirrors only if it still needs them.

## Move Now

- keep pushing site assumptions into `content_machine.site_policy`
- keep pushing transport assumptions into `content_machine.platform_policy`
- keep pushing publish-order assumptions into `content_machine.posting_policy`
- treat `site_policy` as the only place for internal-link and pagination conventions
- treat `platform_policy` as the only place for job transport and delivery-link conventions
- treat `posting_policy` as the only place for publish sequencing and stage naming
- keep `content_package` and `channels.*` canonical and adapter-shaped

## Move Next

- keep the posting machine policy-driven and executor-driven:
  - move more channel-specific target shaping behind adapter executors
  - keep WordPress job transport behind the posting boundary
  - move image upload handoff behind a delivery/media port
  - keep channel delivery adapters swappable per site
- split plugin settings into:
  - reusable content-machine config
  - site profile / delivery profile
- keep plugin table names and site bootstrap behavior centralized in the site adapter

## Leave In Site Adapter

- theme switching
- branding migration
- launch pages and seeded posts
- categories and term setup
- shortcodes
- admin UI
- REST routes
- attachment import

## Target Long-Term Shape

### Worker

- `content-machine/`
  - prompts
  - article generation
  - social generation
  - pagination
  - validation
  - canonical package shaping
- `posting-machine/`
  - job lifecycle
  - publish orchestration
  - channel delivery
  - adapter clients
- `app/worker/`
  - composition root only

### Plugin

- `engine/`
  - contracts
  - quality
  - generic job schema helpers
- `site-adapter/`
  - settings defaults
  - identity
  - launch content
  - publishing
  - WordPress transport

## Practical Rule

If a module needs to know:

- the shortcode tag
- site name or site email
- theme slug
- launch post slugs
- table names
- WordPress options / terms / attachments

it belongs in a site adapter layer, not in the reusable content machine.

## Recommended New-Site Workflow

For the next publication, do not fork the whole product into a permanently separate codebase unless speed matters more than maintainability.

Preferred approach:

1. Keep one shared machine core:
   - worker content engine
   - worker posting machine
   - contracts
   - quality checks
   - channel adapters
2. Create one new site adapter profile:
   - site policy
   - platform policy
   - identity defaults
   - page/library seeds
3. Build only the site-specific surfaces:
   - theme/templates
   - design system
   - site copy
   - media and trust pages

Fast-launch option:

- Clone the existing WordPress site to a new domain when you need to move quickly.
- But keep the machine code shared and avoid letting each cloned site drift in its own custom logic.

Practical rule:

- clone for speed
- share the machine for long-term scale
