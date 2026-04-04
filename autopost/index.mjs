import { Buffer } from "node:buffer";
import { setTimeout as sleep } from "node:timers/promises";

const SECOND_MS = 1000;
const IDLE_MS = 60 * 60 * SECOND_MS;

const config = {
  enabled: toBool(process.env.AUTOPOST_ENABLED, false),
  runOnce: toBool(process.env.AUTOPOST_RUN_ONCE, false),
  startupDelaySeconds: toNumber(process.env.AUTOPOST_STARTUP_DELAY_SECONDS, 30),
  pollSeconds: toNumber(process.env.AUTOPOST_POLL_SECONDS, 15),
  internalWordPressUrl: trimTrailingSlash(process.env.AUTOPOST_WORDPRESS_INTERNAL_URL || "http://wordpress"),
  publicWordPressUrl: trimTrailingSlash(process.env.WORDPRESS_URL || process.env.AUTOPOST_WORDPRESS_URL || ""),
  sharedSecret: process.env.CONTENT_PIPELINE_SHARED_SECRET || "",
  fallbackSiteName: "Kuchnia Twist",
  fallbackBrandVoice: "Warm, useful, story-aware, and editorial without sounding stiff.",
  minWords: toNumber(process.env.AUTOPOST_MIN_WORDS, 1000),
  maxWords: toNumber(process.env.AUTOPOST_MAX_WORDS, 1500),
  fallbackOpenAiKey: process.env.OPENAI_API_KEY || "",
  fallbackOpenAiBaseUrl: trimTrailingSlash(process.env.OPENAI_BASE_URL || "https://api.openai.com/v1"),
  fallbackTextModel: process.env.OPENAI_MODEL || "gpt-5-mini",
};

async function main() {
  log("worker booting");

  if (config.startupDelaySeconds > 0) {
    log(`waiting ${config.startupDelaySeconds}s before first check`);
    await sleep(config.startupDelaySeconds * SECOND_MS);
  }

  if (!config.enabled) {
    log("AUTOPOST_ENABLED is off; worker will stay idle");
    await idleLoop();
    return;
  }

  if (!config.internalWordPressUrl || !config.sharedSecret) {
    log("missing worker configuration; set AUTOPOST_WORDPRESS_INTERNAL_URL and CONTENT_PIPELINE_SHARED_SECRET");
    await idleLoop();
    return;
  }

  do {
    let foundJob = false;

    try {
      foundJob = await processNextJob();
    } catch (error) {
      log(`run failed: ${formatError(error)}`);
    }

    if (config.runOnce) {
      if (foundJob) {
        log("AUTOPOST_RUN_ONCE finished; worker will stay idle until the next deploy");
      } else {
        log("AUTOPOST_RUN_ONCE found no queued jobs; worker will stay idle until the next deploy");
      }
      await idleLoop();
      return;
    }

    await sleep(config.pollSeconds * SECOND_MS);
  } while (true);
}

async function processNextJob() {
  const claimed = await wpRequest("/wp-json/kuchnia-twist/v1/jobs/claim", {
    method: "POST",
  });

  if (!claimed?.job) {
    return false;
  }

  await processJob(claimed.job, mergeSettings(claimed.settings || {}));
  return true;
}

async function processJob(job, settings) {
  const jobLabel = `job #${job.id} [${job.content_type}] ${job.topic}`;
  const retryTarget = String(job.retry_target || "full");
  let stage = String(job.stage || "generating");
  let postId = toInt(job.post_id);
  let permalink = String(job.permalink || "");
  let facebookPostId = String(job.facebook_post_id || "");
  let facebookCommentId = String(job.facebook_comment_id || "");
  let generated = normalizeGeneratedPayload(job.generated_payload || {}, job);
  let facebookCaption = String(job.facebook_caption || generated.facebook_caption || "");
  let groupShareKit = String(job.group_share_kit || generated.group_share_kit || "");
  let featuredImage = firstAttachment(job.featured_image, job.blog_image);
  let facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage);

  log(`processing ${jobLabel}`);

  try {
    if (retryTarget === "comment") {
      if (!facebookPostId || !permalink) {
        throw new Error("Cannot retry the first comment because the Facebook post or permalink is missing.");
      }

      const commentUrl = buildTrackedUrl(permalink, settings, generated, "facebook_comment");
      facebookCommentId = await publishFacebookComment(
        settings,
        facebookPostId,
        buildFacebookComment(settings.defaultCta, commentUrl),
      );

      groupShareKit = finalizeGroupShareKit(groupShareKit, buildTrackedUrl(permalink, settings, generated, "facebook_group_manual"));
      generated = {
        ...generated,
        facebook_urls: {
          ...(generated.facebook_urls || {}),
          facebook_comment: commentUrl,
          facebook_group_manual: buildTrackedUrl(permalink, settings, generated, "facebook_group_manual"),
        },
      };

      await completeJob(job.id, {
        status: "completed",
        facebook_post_id: facebookPostId,
        facebook_comment_id: facebookCommentId,
        facebook_caption: facebookCaption,
        group_share_kit: groupShareKit,
        generated_payload: generated,
      });

      log(`completed ${jobLabel} by adding the missing first comment`);
      return;
    }

    if (retryTarget === "full" || !generated.title || !generated.content_html) {
      ensureOpenAiConfigured(settings);
      generated = await generatePackage(job, settings);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
    }

    ({ featuredImage, facebookImage, generated } = await ensureJobImages(job, settings, generated, {
      featuredImage,
      facebookImage,
    }));

    if (!postId || retryTarget === "full") {
      stage = "publishing_blog";
      await updateJobProgress(job.id, {
        status: "publishing_blog",
        stage,
      });

      const blogPost = await publishBlogPost(job.id, job, generated, featuredImage?.id || 0, facebookImage?.id || featuredImage?.id || 0);
      postId = blogPost.post_id;
      permalink = blogPost.permalink;

      log(`published WordPress article #${postId} for ${jobLabel}`);
    }

    stage = "publishing_facebook";
    await updateJobProgress(job.id, {
      status: "publishing_facebook",
      stage,
    });

    assertFacebookConfigured(settings);

    const commentUrl = buildTrackedUrl(permalink, settings, generated, "facebook_comment");
    const groupShareUrl = buildTrackedUrl(permalink, settings, generated, "facebook_group_manual");

    const facebookPost = await publishFacebookPost(settings, {
      message: facebookCaption || buildFallbackFacebookCaption(generated, settings.defaultCta),
      imageUrl: facebookImage?.url || featuredImage?.url || "",
    });

    facebookPostId = facebookPost.postId;
    facebookCommentId = await publishFacebookComment(
      settings,
      facebookPostId,
      buildFacebookComment(settings.defaultCta, commentUrl),
    );

    groupShareKit = finalizeGroupShareKit(groupShareKit, groupShareUrl);
    generated = {
      ...generated,
      facebook_urls: {
        facebook_comment: commentUrl,
        facebook_group_manual: groupShareUrl,
        facebook_post: facebookPost.url || "",
      },
    };

    await completeJob(job.id, {
      status: "completed",
      facebook_post_id: facebookPostId,
      facebook_comment_id: facebookCommentId,
      facebook_caption: facebookCaption || buildFallbackFacebookCaption(generated, settings.defaultCta),
      group_share_kit: groupShareKit,
      generated_payload: generated,
    });

    log(`completed ${jobLabel}; Facebook post ${facebookPostId}`);
  } catch (error) {
    const message = formatError(error);
    const status = postId ? "partial_failure" : "failed";

    await safeFailJob(job.id, {
      status,
      stage,
      facebook_post_id: facebookPostId,
      facebook_comment_id: facebookCommentId,
      facebook_caption: facebookCaption,
      group_share_kit: groupShareKit,
      generated_payload: generated,
      error_message: message,
    });

    log(`${jobLabel} failed: ${message}`);
  }
}

async function ensureJobImages(job, settings, generated, current) {
  let featuredImage = current.featuredImage;
  let facebookImage = current.facebookImage;

  if (featuredImage?.id && !facebookImage?.id) {
    facebookImage = featuredImage;
  }

  if (facebookImage?.id && !featuredImage?.id) {
    featuredImage = facebookImage;
  }

  if (!featuredImage?.id && !facebookImage?.id) {
    ensureOpenAiConfigured(settings);

    log(`generating blog hero image for job #${job.id}`);
    featuredImage = await generateAndUploadImage(job.id, settings, generated, {
      slot: "blog",
      filename: `${normalizeSlug(generated.slug || generated.title || job.topic)}-blog.png`,
      size: "1536x1024",
      variantHint: "Landscape hero image for the blog article header.",
    });

    log(`generating Facebook image for job #${job.id}`);
    facebookImage = await generateAndUploadImage(job.id, settings, generated, {
      slot: "facebook",
      filename: `${normalizeSlug(generated.slug || generated.title || job.topic)}-facebook.png`,
      size: "1024x1024",
      variantHint: "Square social image for a Facebook Page post.",
    });
  }

  generated = {
    ...generated,
    assets: {
      featured_image_id: featuredImage?.id || 0,
      facebook_image_id: facebookImage?.id || featuredImage?.id || 0,
    },
  };

  return { featuredImage, facebookImage: facebookImage || featuredImage, generated };
}

async function generateAndUploadImage(jobId, settings, generated, options) {
  const base64Data = await generateImageBase64(settings, buildImagePrompt(generated, options.variantHint), options.size);

  return wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/media`, {
    method: "POST",
    body: {
      slot: options.slot,
      filename: options.filename,
      title: `${generated.title} ${options.slot === "blog" ? "Hero" : "Facebook"} Image`,
      alt: generated.image_alt || generated.title,
      base64_data: base64Data,
    },
  });
}

async function publishBlogPost(jobId, job, generated, featuredImageId, facebookImageId) {
  return wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/publish-blog`, {
    method: "POST",
    body: {
      content_type: job.content_type,
      title: generated.title,
      slug: generated.slug,
      excerpt: generated.excerpt,
      seo_description: generated.seo_description,
      content_html: generated.content_html,
      featured_image_id: featuredImageId,
      facebook_image_id: facebookImageId,
      facebook_caption: generated.facebook_caption,
      group_share_kit: generated.group_share_kit,
      generated_payload: generated,
    },
  });
}

async function completeJob(jobId, payload) {
  await wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/complete`, {
    method: "POST",
    body: payload,
  });
}

async function safeFailJob(jobId, payload) {
  try {
    await wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/fail`, {
      method: "POST",
      body: payload,
    });
  } catch (error) {
    log(`unable to report failure for job #${jobId}: ${formatError(error)}`);
  }
}

async function updateJobProgress(jobId, payload) {
  await wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/progress`, {
    method: "POST",
    body: payload,
  });
}

async function generatePackage(job, settings) {
  const body = {
    model: settings.openaiModel,
    messages: [
      {
        role: "system",
        content:
          "You are the editorial engine for a premium food publication. Return strict JSON only, with no markdown fences. The JSON must contain: title, slug, excerpt, seo_description, content_html, facebook_caption, group_share_kit, image_prompt, image_alt, and recipe. content_html must be clean HTML for WordPress using paragraphs, headings, lists, and blockquotes only. Never include links in facebook_caption or group_share_kit.",
      },
      {
        role: "user",
        content: buildEditorialPrompt(job, settings),
      },
    ],
  };

  const payload = await openAiJsonRequest(settings, "/chat/completions", body);
  const content = payload?.choices?.[0]?.message?.content;

  if (!content || typeof content !== "string") {
    throw new Error("OpenAI did not return article content.");
  }

  return normalizeGeneratedPayload(parseJsonObject(content), job);
}

async function generateImageBase64(settings, prompt, size) {
  const payload = await openAiJsonRequest(settings, "/images/generations", {
    model: settings.openaiImageModel,
    prompt,
    size,
  });

  const imageItem = payload?.data?.[0] || payload?.output?.[0] || null;
  if (imageItem?.b64_json) {
    return imageItem.b64_json;
  }

  if (imageItem?.url) {
    const response = await fetch(imageItem.url);
    if (!response.ok) {
      throw new Error(`Image download failed with status ${response.status}.`);
    }
    const binary = Buffer.from(await response.arrayBuffer());
    return binary.toString("base64");
  }

  throw new Error("OpenAI image generation did not return an image.");
}

async function publishFacebookPost(settings, options) {
  const endpoint = options.imageUrl
    ? `https://graph.facebook.com/${settings.facebookGraphVersion}/${settings.facebookPageId}/photos`
    : `https://graph.facebook.com/${settings.facebookGraphVersion}/${settings.facebookPageId}/feed`;

  const params = new URLSearchParams();
  params.set("access_token", settings.facebookPageAccessToken);
  params.set("published", "true");

  if (options.imageUrl) {
    params.set("url", options.imageUrl);
    params.set("caption", options.message);
  } else {
    params.set("message", options.message);
  }

  const payload = await formPostJson(endpoint, params);
  const postId = String(payload.post_id || payload.id || "");

  if (!postId) {
    throw new Error("Facebook did not return a post identifier.");
  }

  return {
    postId,
    url: buildFacebookPostUrl(postId),
  };
}

async function publishFacebookComment(settings, postId, message) {
  const params = new URLSearchParams();
  params.set("access_token", settings.facebookPageAccessToken);
  params.set("message", message);

  const payload = await formPostJson(
    `https://graph.facebook.com/${settings.facebookGraphVersion}/${postId}/comments`,
    params,
  );

  if (!payload?.id) {
    throw new Error("Facebook did not return a comment identifier.");
  }

  return String(payload.id);
}

async function wpRequest(path, options = {}) {
  const url = `${config.internalWordPressUrl}${path}`;
  const headers = {
    "x-kuchnia-worker-secret": config.sharedSecret,
    ...(options.body ? { "Content-Type": "application/json" } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(url, {
    method: options.method || "GET",
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  return parseJsonResponse(response, "WordPress");
}

async function openAiJsonRequest(settings, path, body) {
  const response = await fetch(`${settings.openaiBaseUrl}${path}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${settings.openaiApiKey}`,
    },
    body: JSON.stringify(body),
  });

  return parseJsonResponse(response, "OpenAI");
}

async function formPostJson(url, params) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: params.toString(),
  });

  return parseJsonResponse(response, "Facebook");
}

async function parseJsonResponse(response, sourceName) {
  const raw = await response.text();
  let payload = null;

  try {
    payload = raw ? JSON.parse(raw) : null;
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const message =
      payload?.message ||
      payload?.error?.message ||
      raw ||
      `${response.status} ${response.statusText}`;
    throw new Error(`${sourceName} request failed with status ${response.status}: ${message}`);
  }

  if (payload?.error?.message) {
    throw new Error(`${sourceName} error: ${payload.error.message}`);
  }

  return payload;
}

function mergeSettings(raw) {
  return {
    siteName: raw.site_name || config.fallbackSiteName,
    siteUrl: trimTrailingSlash(raw.site_url || config.publicWordPressUrl || config.internalWordPressUrl),
    brandVoice: raw.brand_voice || config.fallbackBrandVoice,
    articlePrompt: raw.article_prompt || "Write substantial, original food articles with a premium editorial tone.",
    defaultCta: raw.default_cta || "Read the full article on the blog.",
    imageStyle: raw.image_style || "Natural food photography, premium editorial light, no text overlays.",
    facebookGraphVersion: raw.facebook_graph_version || "v22.0",
    facebookPageId: raw.facebook_page_id || "",
    facebookPageAccessToken: raw.facebook_page_access_token || "",
    openaiModel: raw.openai_model || config.fallbackTextModel,
    openaiImageModel: raw.openai_image_model || "gpt-image-1.5",
    openaiApiKey: raw.openai_api_key || config.fallbackOpenAiKey,
    openaiBaseUrl: trimTrailingSlash(raw.openai_base_url || config.fallbackOpenAiBaseUrl),
    utmSource: raw.utm_source || "facebook",
    utmCampaignPrefix: raw.utm_campaign_prefix || "kuchnia-twist",
  };
}

function buildEditorialPrompt(job, settings) {
  const contentTypeNotes = {
    recipe: [
      "Write a recipe-led article with strong sensory writing and practical kitchen guidance.",
      "Do not place the full ingredients and method inside the article body; the structured recipe box will render them separately.",
      "The recipe object must include prep_time, cook_time, total_time, yield, ingredients[], and instructions[].",
    ],
    food_fact: [
      "Write a fact-led article that corrects confusion, explains why it matters, and gives a practical takeaway.",
      "The recipe object must contain empty strings and empty arrays.",
    ],
    food_story: [
      "Write a story-led article with a clear narrative arc, practical food insight, and a reflective close.",
      "The recipe object must contain empty strings and empty arrays.",
    ],
  };

  return [
    `Site name: ${settings.siteName}`,
    `Topic: ${job.topic}`,
    `Content type: ${job.content_type}`,
    `Brand voice: ${settings.brandVoice}`,
    `Article guidance: ${settings.articlePrompt}`,
    `Length target: ${config.minWords}-${config.maxWords} words for the main article body.`,
    `Default CTA: ${settings.defaultCta}`,
    job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate a strong, editorial article title.",
    "Global rules:",
    "- Write original, useful, human-sounding content.",
    "- Use a polished magazine tone, not generic SEO filler.",
    "- Do not mention AI or say the article was generated.",
    "- content_html must begin with an introduction paragraph and use clean H2 sections.",
    "- facebook_caption must be short, hook-led, and must not include the link.",
    "- group_share_kit must feel natural for manual posting into food groups and must not include the link.",
    "- seo_description should stay under 155 characters.",
    "- image_prompt must describe a premium food photo with no text overlays.",
    ...contentTypeNotes[job.content_type] || contentTypeNotes.recipe,
    "JSON contract:",
    "{",
    '  "title": "string",',
    '  "slug": "kebab-case-string",',
    '  "excerpt": "string",',
    '  "seo_description": "string",',
    '  "content_html": "string",',
    '  "facebook_caption": "string",',
    '  "group_share_kit": "string",',
    '  "image_prompt": "string",',
    '  "image_alt": "string",',
    '  "recipe": {',
    '    "prep_time": "string",',
    '    "cook_time": "string",',
    '    "total_time": "string",',
    '    "yield": "string",',
    '    "ingredients": ["string"],',
    '    "instructions": ["string"]',
    "  }",
    "}",
  ].join("\n");
}

function normalizeGeneratedPayload(raw, job) {
  const source = isPlainObject(raw) ? raw : {};
  const titleOverride = cleanText(job?.title_override || "");
  const title = titleOverride || cleanText(source.title) || cleanText(job?.topic) || "Fresh from Kuchnia Twist";
  const slug = normalizeSlug(source.slug || title);
  const excerpt = trimText(cleanText(source.excerpt), 220) || `${title} on Kuchnia Twist.`;
  const seoDescription =
    trimText(cleanText(source.seo_description || source.seoDescription), 155) ||
    trimText(excerpt, 155);
  const contentHtml = normalizeHtml(source.content_html || source.contentHtml || "");
  const facebookCaption =
    cleanMultilineText(source.facebook_caption || source.facebookCaption) ||
    buildFallbackFacebookCaption({ title, excerpt }, "Read the full article on the blog.");
  const groupShareKit =
    cleanMultilineText(source.group_share_kit || source.groupShareKit) ||
    `${title}\n\n${excerpt}\n\nCurious what you think about this one.`;
  const imagePrompt =
    cleanMultilineText(source.image_prompt || source.imagePrompt) ||
    `Editorial food photography of ${title}, premium magazine lighting, appetizing detail, natural styling, no text overlay.`;
  const imageAlt = cleanText(source.image_alt || source.imageAlt) || title;
  const recipe = normalizeRecipe(source.recipe || {}, job?.content_type || "recipe");

  if (!contentHtml) {
    throw new Error("The generated article body was empty.");
  }

  if ((job?.content_type || "") === "recipe" && (!recipe.ingredients.length || !recipe.instructions.length)) {
    throw new Error("The generated recipe is missing ingredients or instructions.");
  }

  return {
    title,
    slug,
    excerpt,
    seo_description: seoDescription,
    content_html: contentHtml,
    facebook_caption: facebookCaption,
    group_share_kit: groupShareKit,
    image_prompt: imagePrompt,
    image_alt: imageAlt,
    recipe,
    assets: isPlainObject(source.assets) ? source.assets : {},
    facebook_urls: isPlainObject(source.facebook_urls) ? source.facebook_urls : {},
  };
}

function normalizeRecipe(value, contentType) {
  if (contentType !== "recipe") {
    return {
      prep_time: "",
      cook_time: "",
      total_time: "",
      yield: "",
      ingredients: [],
      instructions: [],
    };
  }

  const recipe = isPlainObject(value) ? value : {};
  return {
    prep_time: cleanText(recipe.prep_time),
    cook_time: cleanText(recipe.cook_time),
    total_time: cleanText(recipe.total_time),
    yield: cleanText(recipe.yield),
    ingredients: ensureStringArray(recipe.ingredients),
    instructions: ensureStringArray(recipe.instructions),
  };
}

function buildImagePrompt(generated, variantHint) {
  return [
    generated.image_prompt,
    variantHint,
    "Keep the image realistic, editorial, appetizing, and free of any text or logos.",
  ].join("\n");
}

function buildFacebookComment(defaultCta, trackedUrl) {
  const cta = cleanText(defaultCta) || "Read the full article on the blog.";
  return `${cta} ${trackedUrl}`.trim();
}

function finalizeGroupShareKit(text, trackedUrl) {
  const cleaned = cleanMultilineText(text).replace(/\[LINK\]/gi, trackedUrl).trim();
  if (!cleaned) {
    return `Thought this article might be useful here.\n\nRead more: ${trackedUrl}`;
  }

  return cleaned.includes(trackedUrl) ? cleaned : `${cleaned}\n\nRead more: ${trackedUrl}`;
}

function buildFallbackFacebookCaption(generated, defaultCta) {
  return `${generated.title}\n\n${generated.excerpt}\n\n${cleanText(defaultCta) || "Read the full article on the blog."}`.trim();
}

function buildTrackedUrl(permalink, settings, generated, contentLabel) {
  const url = new URL(permalink);
  url.searchParams.set("utm_source", settings.utmSource || "facebook");
  url.searchParams.set("utm_medium", "social");
  url.searchParams.set(
    "utm_campaign",
    trimText(`${settings.utmCampaignPrefix || "kuchnia-twist"}-${normalizeSlug(generated.slug || generated.title || "article")}`, 80),
  );
  url.searchParams.set("utm_content", contentLabel);
  return url.toString();
}

function buildFacebookPostUrl(postId) {
  if (!postId.includes("_")) {
    return "";
  }

  const [pageId, entryId] = postId.split("_");
  return pageId && entryId ? `https://www.facebook.com/${pageId}/posts/${entryId}` : "";
}

function firstAttachment(...items) {
  return items.find((item) => item && item.id && item.url) || null;
}

function ensureOpenAiConfigured(settings) {
  if (!settings.openaiApiKey) {
    throw new Error("OpenAI API key is missing. Add it in the plugin settings or container environment.");
  }
}

function assertFacebookConfigured(settings) {
  if (!settings.facebookPageId || !settings.facebookPageAccessToken) {
    throw new Error("Facebook Page ID or access token is missing in the plugin settings.");
  }
}

function parseJsonObject(text) {
  try {
    return JSON.parse(text);
  } catch {
    const firstBrace = text.indexOf("{");
    const lastBrace = text.lastIndexOf("}");
    if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
      throw new Error("The model response was not valid JSON.");
    }
    return JSON.parse(text.slice(firstBrace, lastBrace + 1));
  }
}

function normalizeHtml(value) {
  const html = String(value || "").trim();
  if (!html) {
    return "";
  }

  if (html.includes("<")) {
    return html;
  }

  return html
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean)
    .map((paragraph) => `<p>${escapeHtml(paragraph)}</p>`)
    .join("\n");
}

function ensureStringArray(value) {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => cleanText(item))
    .filter(Boolean);
}

function cleanText(value) {
  return String(value || "")
    .replace(/\s+/g, " ")
    .trim();
}

function cleanMultilineText(value) {
  return String(value || "")
    .replace(/\r/g, "")
    .split("\n")
    .map((line) => line.trim())
    .join("\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function trimText(value, maxLength) {
  const text = cleanText(value);
  if (!text || text.length <= maxLength) {
    return text;
  }

  return `${text.slice(0, maxLength - 1).trim()}…`;
}

function normalizeSlug(value) {
  return String(value || "")
    .toLowerCase()
    .replace(/&/g, " and ")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80) || `post-${Date.now()}`;
}

function escapeHtml(value) {
  return String(value || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function isPlainObject(value) {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
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

function toInt(value) {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : 0;
}

function trimTrailingSlash(value) {
  return String(value || "").replace(/\/+$/, "");
}

function formatError(error) {
  if (error instanceof Error) {
    return error.message;
  }

  return String(error);
}

async function idleLoop() {
  do {
    await sleep(IDLE_MS);
  } while (true);
}

function log(message) {
  console.log(`[autopost] ${new Date().toISOString()} ${message}`);
}

main().catch((error) => {
  log(`fatal error: ${formatError(error)}`);
  process.exit(1);
});
