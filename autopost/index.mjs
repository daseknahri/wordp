import { mkdir, readFile, writeFile } from "node:fs/promises";
import { Buffer } from "node:buffer";
import { setTimeout as sleep } from "node:timers/promises";

const HOUR_MS = 60 * 60 * 1000;
const DEFAULT_STATE = {
  lastRunAt: null,
  topicIndex: 0,
  recentSlugs: [],
  lastPost: null,
};

const config = {
  enabled: toBool(process.env.AUTOPOST_ENABLED, false),
  runOnce: toBool(process.env.AUTOPOST_RUN_ONCE, false),
  startupDelaySeconds: toNumber(process.env.AUTOPOST_STARTUP_DELAY_SECONDS, 30),
  intervalHours: toNumber(process.env.AUTOPOST_INTERVAL_HOURS, 24),
  status: process.env.AUTOPOST_STATUS || "draft",
  siteName: process.env.AUTOPOST_SITE_NAME || "My WordPress Blog",
  tone: process.env.AUTOPOST_TONE || "clear, practical, and human",
  authorVoice: process.env.AUTOPOST_AUTHOR_VOICE || "helpful editor",
  topics: csv(process.env.AUTOPOST_TOPICS),
  minWords: toNumber(process.env.AUTOPOST_MIN_WORDS, 900),
  maxWords: toNumber(process.env.AUTOPOST_MAX_WORDS, 1400),
  wordpressUrl: trimTrailingSlash(process.env.WORDPRESS_URL || ""),
  wordpressUsername: process.env.WORDPRESS_USERNAME || "",
  wordpressAppPassword: (process.env.WORDPRESS_APP_PASSWORD || "").replace(/\s+/g, ""),
  openaiApiKey: process.env.OPENAI_API_KEY || "",
  openaiBaseUrl: trimTrailingSlash(process.env.OPENAI_BASE_URL || "https://api.openai.com/v1"),
  openaiModel: process.env.OPENAI_MODEL || "gpt-5-mini",
  stateDir: process.env.AUTOPOST_STATE_DIR || "/app/data",
};

async function main() {
  log("worker booting");

  if (config.startupDelaySeconds > 0) {
    log(`waiting ${config.startupDelaySeconds}s before first check`);
    await sleep(config.startupDelaySeconds * 1000);
  }

  await mkdir(config.stateDir, { recursive: true });

  if (!config.enabled) {
    log("AUTOPOST_ENABLED is off; worker will stay idle");
    await idleLoop();
    return;
  }

  if (!hasRequiredConfig()) {
    log("missing required environment values; worker will stay idle");
    await idleLoop();
    return;
  }

  do {
    try {
      await maybeCreatePost();
    } catch (error) {
      log(`run failed: ${formatError(error)}`);
    }

    if (config.runOnce) {
      log("AUTOPOST_RUN_ONCE finished; worker will stay idle until the next deploy");
      await idleLoop();
      return;
    }

    log(`sleeping for ${config.intervalHours} hour(s)`);
    await sleep(config.intervalHours * HOUR_MS);
  } while (true);
}

async function maybeCreatePost() {
  const state = await loadState();
  const lastRunAt = state.lastRunAt ? Date.parse(state.lastRunAt) : 0;
  const nextRunAt = lastRunAt + config.intervalHours * HOUR_MS;

  if (lastRunAt && Date.now() < nextRunAt) {
    const minutes = Math.ceil((nextRunAt - Date.now()) / 60000);
    log(`skipping; next run window opens in about ${minutes} minute(s)`);
    return;
  }

  await assertWordPressReady();

  const topic = pickTopic(state.topicIndex);
  log(`generating post for topic: ${topic}`);

  const draft = await generatePost(topic);
  const slug = ensureUniqueSlug(normalizeSlug(draft.slug || draft.title), state.recentSlugs);
  const published = await createWordPressPost({
    title: draft.title,
    excerpt: draft.excerpt,
    content: draft.contentHtml,
    slug,
    status: config.status,
  });

  const nextState = {
    lastRunAt: new Date().toISOString(),
    topicIndex: state.topicIndex + 1,
    recentSlugs: [slug, ...state.recentSlugs].slice(0, 25),
    lastPost: {
      id: published.id,
      link: published.link,
      status: published.status,
      slug,
      title: draft.title,
      topic,
    },
  };

  await saveState(nextState);
  log(`created post #${published.id} with status "${published.status}"`);
  if (published.link) {
    log(`post link: ${published.link}`);
  }
}

async function assertWordPressReady() {
  const response = await fetch(`${config.wordpressUrl}/wp-json/`);
  if (!response.ok) {
    throw new Error(`WordPress is not ready yet (${response.status})`);
  }
}

async function generatePost(topic) {
  const body = {
    model: config.openaiModel,
    messages: [
      {
        role: "system",
        content:
          "You write high-quality blog posts for WordPress. Return valid JSON only with keys title, slug, excerpt, and contentHtml. contentHtml must be clean HTML for the WordPress editor, without html/body tags.",
      },
      {
        role: "user",
        content: [
          `Site name: ${config.siteName}`,
          `Topic: ${topic}`,
          `Tone: ${config.tone}`,
          `Author voice: ${config.authorVoice}`,
          `Length target: ${config.minWords}-${config.maxWords} words`,
          "Requirements:",
          "- Original, specific, practical writing",
          "- Include a short introduction",
          "- Use H2 sections",
          "- Include a conclusion",
          "- Avoid filler and fake statistics",
          "- Do not mention AI or that the article was generated",
        ].join("\n"),
      },
    ],
    temperature: 0.9,
  };

  const response = await fetch(`${config.openaiBaseUrl}/chat/completions`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${config.openaiApiKey}`,
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    throw new Error(`OpenAI request failed with status ${response.status}: ${await response.text()}`);
  }

  const payload = await response.json();
  const content = payload?.choices?.[0]?.message?.content;
  if (!content || typeof content !== "string") {
    throw new Error("OpenAI response did not include message content");
  }

  const parsed = parseJsonObject(content);
  assertField(parsed.title, "title");
  assertField(parsed.excerpt, "excerpt");
  assertField(parsed.contentHtml, "contentHtml");

  return parsed;
}

async function createWordPressPost(post) {
  const response = await fetch(`${config.wordpressUrl}/wp-json/wp/v2/posts`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Basic ${Buffer.from(
        `${config.wordpressUsername}:${config.wordpressAppPassword}`,
      ).toString("base64")}`,
    },
    body: JSON.stringify(post),
  });

  if (!response.ok) {
    throw new Error(`WordPress post creation failed with status ${response.status}: ${await response.text()}`);
  }

  return response.json();
}

function pickTopic(topicIndex) {
  if (config.topics.length === 0) {
    return `${config.siteName} publishing strategy`;
  }
  return config.topics[topicIndex % config.topics.length];
}

function hasRequiredConfig() {
  return Boolean(
    config.wordpressUrl &&
      config.wordpressUsername &&
      config.wordpressAppPassword &&
      config.openaiApiKey,
  );
}

async function loadState() {
  try {
    const raw = await readFile(stateFilePath(), "utf8");
    return { ...DEFAULT_STATE, ...JSON.parse(raw) };
  } catch {
    return { ...DEFAULT_STATE };
  }
}

async function saveState(state) {
  await writeFile(stateFilePath(), JSON.stringify(state, null, 2));
}

function stateFilePath() {
  return `${config.stateDir}/state.json`;
}

async function idleLoop() {
  do {
    await sleep(config.intervalHours * HOUR_MS);
  } while (true);
}

function parseJsonObject(text) {
  try {
    return JSON.parse(text);
  } catch {
    const firstBrace = text.indexOf("{");
    const lastBrace = text.lastIndexOf("}");
    if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
      throw new Error("model output was not valid JSON");
    }
    return JSON.parse(text.slice(firstBrace, lastBrace + 1));
  }
}

function normalizeSlug(value) {
  return String(value || "")
    .toLowerCase()
    .replace(/&/g, " and ")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

function ensureUniqueSlug(slug, recentSlugs) {
  if (!slug) {
    slug = `post-${Date.now()}`;
  }

  if (!recentSlugs.includes(slug)) {
    return slug;
  }

  return `${slug}-${Date.now().toString().slice(-6)}`;
}

function assertField(value, name) {
  if (!value || typeof value !== "string") {
    throw new Error(`model output is missing "${name}"`);
  }
}

function trimTrailingSlash(value) {
  return value.replace(/\/+$/, "");
}

function csv(value) {
  return String(value || "")
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
}

function toBool(value, fallback) {
  if (value == null || value === "") {
    return fallback;
  }
  return ["1", "true", "yes", "on"].includes(String(value).toLowerCase());
}

function toNumber(value, fallback) {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function formatError(error) {
  if (error instanceof Error) {
    return error.message;
  }
  return String(error);
}

function log(message) {
  console.log(`[autopost] ${new Date().toISOString()} ${message}`);
}

main().catch((error) => {
  log(`fatal error: ${formatError(error)}`);
  process.exit(1);
});
