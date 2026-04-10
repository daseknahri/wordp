# Kuchnia Twist: Typed Content Publishing

This repo now ships a typed publishing system for `Kuchnia Twist`:

- a custom WordPress image that installs the `Kuchnia Twist` theme and publisher plugin
- a background worker that picks up queued jobs from WordPress
- one canonical multi-page article package for each generated post
- Facebook Page posting with the blog link added as the first comment
- a dormant Pinterest draft adapter scaffold for later expansion

The workflow is intentionally small and upgrade-friendly. WordPress stays the control panel, the worker does the background execution, and platform-specific social output hangs off a stable content package instead of being mixed into the article engine.

## What is included

- `wordpress/wp-content/themes/kuchnia-twist/`
  - editorial front page
  - stronger single-post layout
  - category archives for Recipes, Food Facts, and Food Stories
  - trust-ready footer and page links
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/`
  - content-machine-driven publishing UI in wp-admin
  - system-status dashboard with worker freshness checks
  - typed composer for `recipe` and `food_fact` jobs with optional per-job scheduling
  - event timeline and CSV export for filtered job history
  - settings screen for content prompts, image policy, OpenAI, and Facebook
  - queued job storage
  - job event storage
  - internal REST endpoints for the worker
- `autopost/`
  - queue worker that:
    - claims jobs from WordPress
    - generates a canonical article package with typed prompts
    - runs a single repair pass when validation fails
    - generates missing images only when the launch policy allows it
    - publishes the WordPress post immediately when no future schedule is set
    - waits for `publish_on` when a job is scheduled for later
    - generates channel-specific output from the canonical package
    - publishes the Facebook Page post
    - adds the CTA link as the first comment
    - sends heartbeat updates back to WordPress

## Local development

Start WordPress + MySQL:

```powershell
docker compose up -d --build
```

Open `http://localhost:8080` and finish the normal WordPress installer.

When you want the worker too:

```powershell
docker compose --profile autopost up -d --build
```

## Coolify deployment

1. Create a Docker Compose application from this repository.
2. Set Base Directory to `/`.
3. Set Compose Location to `compose.coolify.yaml`.
4. Attach your public domain only to the `wordpress` service.
5. Leave `db` and `autopost` without public domains.
6. Add the environment variables from `.env.example`.
7. Deploy once and finish the WordPress installer.
8. Open wp-admin and configure the plugin settings.
9. Enable the worker and redeploy.

If Coolify asks for a service port on `wordpress`, use `80`.

## Required environment variables

These must exist in Coolify:

```env
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=replace-me
MYSQL_ROOT_PASSWORD=replace-me-too
WORDPRESS_TABLE_PREFIX=wp_
WORDPRESS_DEBUG=0

CONTENT_PIPELINE_SHARED_SECRET=replace-with-a-long-random-secret

AUTOPOST_ENABLED=1
AUTOPOST_RUN_ONCE=0
AUTOPOST_STARTUP_DELAY_SECONDS=30
AUTOPOST_POLL_SECONDS=15
AUTOPOST_MIN_WORDS=1000
AUTOPOST_MAX_WORDS=1500
AUTOPOST_WORDPRESS_INTERNAL_URL=http://wordpress
WORDPRESS_URL=https://kuchniatwist.pl

OPENAI_API_KEY=sk-...
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-5-mini
```

`CONTENT_PIPELINE_SHARED_SECRET` must be the same for both the `wordpress` and `autopost` containers because the worker authenticates to the plugin with that secret.

## WordPress setup

After deployment, go to wp-admin:

1. Open `Kuchnia Twist -> Settings`
2. Fill in:
   - publication role, voice brief, and guardrails
   - recipe article/social guidance
   - food-fact article/social guidance
   - image style brief
   - image handling mode
   - OpenAI model and optional API key override
   - Facebook page library entries
   - UTM source and campaign prefix
3. Save settings

Then open `Kuchnia Twist` and create a job:

1. Choose `Recipe` or `Food Fact`
2. Enter the dish name or working title/topic seed
3. Optionally set a final title override
4. Upload a blog image
5. Upload a Facebook image
6. Select one or more active Facebook pages
7. Optionally set an exact publish date and time
8. Click `Queue Job`

The worker will pick up the queued job in the background.

## Publishing behavior

For each job:

1. The worker claims the queued job and runs the typed article stage.
2. If validation fails, the worker runs one repair pass using the validator error as feedback.
3. The article stage returns one canonical content package:
   - `content_type` / `topic_seed`
   - title / slug / excerpt / SEO description
   - multi-page article HTML plus `content_pages` and `page_flow`
   - image prompt / alt text
   - typed data such as recipe fields when the content type is `recipe`
4. The worker then builds channel adapters from that package:
   - `channels.facebook` for live Facebook variants and distribution
   - `channels.pinterest` as a dormant draft scaffold only
5. If the Facebook pack is weak or missing, the worker falls back to deterministic local variants so the article can still move forward.
6. If image handling is `Uploaded First` and one or both image slots are missing, the worker generates only the missing slot(s).
7. The worker calculates a layered quality summary with article/package quality, Facebook adapter quality, and overall editorial readiness.
   - hard integrity problems become `block`
   - softer content issues become `warn`
8. The job moves to `scheduled`.
9. If no future `publish_on` was requested, the worker continues straight into publish.
10. If a future `publish_on` was requested, the worker waits until that exact timestamp in the WordPress timezone before publish.
11. Right before WordPress publish, the worker reruns the blocking quality gate.
    - `block` stops blog publish
    - `warn` still publishes and stays visible in wp-admin
12. If the package passes, the worker publishes the WordPress post.
13. The worker publishes one different Facebook post variant to each selected page.
14. The tracked article link is posted as the first comment on each selected page post.

If the blog publishes but one or more Facebook pages fail, the WordPress post stays live and the job is marked as `partial_failure`. You can retry it from wp-admin without duplicating the article or re-posting to pages that already succeeded.

Retries for failed or partial jobs bypass the manual schedule and move back into an immediate `scheduled` state so the worker can repair them on the next pass.

## Operations and worker health

- The worker sends a heartbeat to WordPress on boot, after startup delay, and after every loop.
- WordPress stores the latest worker snapshot in the `kuchnia_twist_worker_status` option.
- The dashboard marks the worker as stale if no heartbeat arrives within `max(90 seconds, poll_seconds * 3)`.
- Missing Facebook credentials do not block queueing because the publish policy keeps the blog live even if Facebook fails later.
- Missing OpenAI credentials or a stale worker heartbeat show warnings in wp-admin before you queue jobs.
- The job list can be filtered and exported as CSV from the current filtered result set. CSV export intentionally omits article HTML, raw payload blobs, and long generated copy.
- The selected-job view shows prompt version, profile, preset, repair attempts, distribution source, quality status, blocking vs warning checks, hook angles, final planned Facebook post messages, first-comment previews, and operator controls for `Publish Now`, `Reschedule`, and `Cancel Release`.

## Production smoke test

After each deploy:

1. Open `Kuchnia Twist` in wp-admin and confirm the worker status strip shows a recent heartbeat.
2. Open `Kuchnia Twist -> Settings` and confirm:
   - worker secret is present
   - OpenAI is ready
   - Facebook is ready, or the warning is expected
3. Queue one valid typed job with:
   - either a recipe dish name or a food-fact working title
   - optional title override
   - optional exact publish date/time
   - either both uploaded images, one uploaded image, or no uploaded images depending on the image-handling mode you want to test
4. Watch the job move through:
   - `queued`
   - `generating`
   - `scheduled`
   - `publishing_blog`
   - `publishing_facebook`
   - `completed`
5. Confirm the selected job shows the expected `publish_on` behavior and the prompt metadata you expect.
6. Use `Publish Now` if you want to force a future-scheduled smoke test through immediately.
7. Open the published post and confirm the article is live.
8. Confirm the Facebook post and first comment were created.
9. If Facebook fails after blog publish, confirm the job becomes `partial_failure` and retry it from wp-admin.

## Public launch QA

After the deploy is live, review the public site at:

- `360px`
- `390px`
- `430px`
- `768px`
- `1024px`
- `1440px`

Acceptance checklist:

- mobile nav opens, closes, and stays visible
- homepage first viewport feels content-led rather than chrome-led
- homepage sections do not leave oversized empty gaps
- archive and search pages scan cleanly on mobile and desktop
- no decorative placeholder artwork appears on homepage, archive, search, or related-story cards
- single posts show a clear excerpt, useful support links, and consistent trust signals
- About, Contact, Privacy Policy, Cookie Policy, and Editorial Policy read like live publication pages
- launch posts and trust pages have distinct excerpts and meta descriptions
- canonical, Open Graph, and Twitter metadata reflect the current page, with real image alt text and dimensions where available, and search results stay `noindex`
- the launch head output also sets a warm browser `theme-color` and icon hints for mobile/browser chrome
- the theme emits a structured-data graph for the publisher, website, breadcrumbs, and page/article/recipe entity, using richer image objects when real media is available
- search `noindex` is handled through WordPress `wp_robots`, and publisher schema includes editorial/business contact points when those settings are filled
- reveal-on-scroll sections still render normally when JavaScript is unavailable, and the search/menu overlays behave like proper dialogs when JavaScript is present
- above-the-fold hero and lead images use eager/high-priority loading without falling back to oversized full-size homepage images
- the site still contains no ad placeholders, sponsor placeholders, or `ads.txt` before a real AdSense account exists

## Notes

- The plugin seeds `About`, `Contact`, `Privacy Policy`, `Cookie Policy`, and `Editorial Policy` with launch-ready copy and media, and now keeps a seed hash so later launch-copy improvements can refresh untouched seeded pages without overwriting manual edits.
- The theme is switched automatically to `Kuchnia Twist` on first load.
- The active publishing machine is a manual typed engine right now. `recipe` and `food_fact` are active, future AI idea generation / autopilot posting is not active yet, and older dormant paths are kept only for compatibility.
- Facebook Groups are manual in phase 1. The system prepares the copy, but it does not auto-post into groups.
- The worker is stateless now. It polls for queued jobs instead of keeping its own post history file.

## Validation commands

```powershell
node --check autopost/index.mjs
docker compose config
docker compose -f compose.coolify.yaml config
```
