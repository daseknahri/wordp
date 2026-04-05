# Kuchnia Twist: WordPress + Facebook Publishing

This repo now ships a simple phase-1 publishing system for `Kuchnia Twist`:

- a custom WordPress image that installs the `Kuchnia Twist` theme and publisher plugin
- a background worker that picks up queued jobs from WordPress
- one-click article publishing from wp-admin
- Facebook Page posting with the blog link added as the first comment
- a manual group-share kit for Facebook food groups

The workflow is intentionally small and upgrade-friendly. WordPress stays the control panel, and the worker does the background execution.

## What is included

- `wordpress/wp-content/themes/kuchnia-twist/`
  - editorial front page
  - stronger single-post layout
  - category archives for Recipes, Food Facts, and Food Stories
  - trust-ready footer and page links
- `wordpress/wp-content/plugins/kuchnia-twist-publisher/`
  - topic-list-driven publishing UI in wp-admin
  - system-status dashboard with worker freshness checks
  - event timeline and CSV export for filtered job history
  - settings screen for OpenAI + Facebook
  - queued job storage
  - job event storage
  - internal REST endpoints for the worker
- `autopost/`
  - queue worker that:
    - claims jobs from WordPress
    - generates the article
    - generates missing images only when the launch policy allows it
    - publishes the WordPress post
    - publishes the Facebook Page post
    - adds the CTA link as the first comment
    - stores a manual group-share kit
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
   - topic list
   - brand voice
   - article prompt guidance
   - OpenAI model and optional API key override
   - image style guidance
   - Facebook Page ID
   - Facebook Page access token
   - UTM source and campaign prefix
3. Save settings

Then open `Kuchnia Twist` and create a job:

1. Select a topic
2. Select `Recipe`, `Food Fact`, or `Food Story`
3. Set the final title when launch mode is `manual_only`
4. Upload a blog image
5. Upload a Facebook image
6. Click `Queue Job`

The worker will pick up the queued job in the background.

## Publishing behavior

For each job:

1. The worker generates the article package with OpenAI.
2. If the site is in an AI-fallback image mode and images are missing, it generates them too.
3. The WordPress article is published immediately.
4. A Facebook Page post is published.
5. The article link is posted as the first Facebook comment.
6. A manual group-share kit is saved in the job record for your food groups.

If the blog publishes but Facebook fails, the WordPress post stays live and the job is marked as `partial_failure`. You can retry it from wp-admin without duplicating the article.

## Operations and worker health

- The worker sends a heartbeat to WordPress on boot, after startup delay, and after every loop.
- WordPress stores the latest worker snapshot in the `kuchnia_twist_worker_status` option.
- The dashboard marks the worker as stale if no heartbeat arrives within `max(90 seconds, poll_seconds * 3)`.
- Missing Facebook credentials do not block queueing because the publish policy keeps the blog live even if Facebook fails later.
- Missing OpenAI credentials or a stale worker heartbeat show warnings in wp-admin before you queue jobs.
- The job list can be filtered and exported as CSV from the current filtered result set. CSV export intentionally omits article HTML, raw payload blobs, and long generated copy.

## Production smoke test

After each deploy:

1. Open `Kuchnia Twist` in wp-admin and confirm the worker status strip shows a recent heartbeat.
2. Open `Kuchnia Twist -> Settings` and confirm:
   - worker secret is present
   - OpenAI is ready
   - Facebook is ready, or the warning is expected
3. Queue one valid `manual_only` job with:
   - a final title
   - a blog hero image
   - a Facebook image
4. Watch the job move through:
   - `queued`
   - `generating`
   - `publishing_blog`
   - `publishing_facebook`
   - `completed`
5. Open the published post and confirm the article is live.
6. Confirm the Facebook post and first comment were created.
7. If Facebook fails after blog publish, confirm the job becomes `partial_failure` and retry it from wp-admin.

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
- Facebook Groups are manual in phase 1. The system prepares the copy, but it does not auto-post into groups.
- The worker is stateless now. It polls for queued jobs instead of keeping its own post history file.

## Validation commands

```powershell
node --check autopost/index.mjs
docker compose config
docker compose -f compose.coolify.yaml config
```
