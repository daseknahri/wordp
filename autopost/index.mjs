import { Buffer } from "node:buffer";
import { setTimeout as sleep } from "node:timers/promises";

const SECOND_MS = 1000;
const IDLE_MS = 60 * 60 * SECOND_MS;
const WORKER_VERSION = "1.5.0";
const PROMPT_VERSION = "recipe-master-v1";

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
  await sendHeartbeatBestEffort({ last_loop_result: "booting" });

  if (config.startupDelaySeconds > 0) {
    log(`waiting ${config.startupDelaySeconds}s before first check`);
    await sleep(config.startupDelaySeconds * SECOND_MS);
    await sendHeartbeatBestEffort({ last_loop_result: "startup_delay_complete" });
  }

  if (!config.enabled) {
    log("AUTOPOST_ENABLED is off; worker will stay idle");
    await sendHeartbeatBestEffort({ last_loop_result: "disabled" });
    await idleLoop();
    return;
  }

  if (!hasWorkerConfig()) {
    const message = "missing worker configuration; set AUTOPOST_WORDPRESS_INTERNAL_URL and CONTENT_PIPELINE_SHARED_SECRET";
    log(message);
    await sendHeartbeatBestEffort({
      config_ok: false,
      last_loop_result: "invalid_config",
      last_error: message,
    });
    await idleLoop();
    return;
  }

  do {
    let loopState = idleHeartbeatState();

    try {
      loopState = await processNextJob();
    } catch (error) {
      const message = formatError(error);
      log(`run failed: ${message}`);
      loopState = {
        foundJob: false,
        last_loop_result: "loop_error",
        last_job_id: 0,
        last_job_status: "",
        last_error: message,
      };
    }

    await sendHeartbeatBestEffort(loopState);

    if (config.runOnce) {
      if (loopState.foundJob) {
        log("AUTOPOST_RUN_ONCE finished; worker will stay idle until the next deploy");
      } else {
        log("AUTOPOST_RUN_ONCE found no due jobs; worker will stay idle until the next deploy");
      }
      await sendHeartbeatBestEffort({
        last_loop_result: loopState.foundJob ? "run_once_complete" : "run_once_no_job",
        last_job_id: loopState.last_job_id || 0,
        last_job_status: loopState.last_job_status || "",
        last_error: loopState.last_error || "",
      });
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
    return idleHeartbeatState();
  }

  return processJob(
    claimed.job,
    mergeSettings(claimed.settings || {}),
    String(claimed.claim_mode || inferClaimMode(claimed.job)),
  );
}

async function processJob(job, settings, claimMode = "generate") {
  const jobLabel = `job #${job.id} [${job.content_type}] ${job.topic}`;
  const retryTarget = String(job.retry_target || (claimMode === "publish" ? "publish" : "full"));
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
  let socialPack = normalizeSocialPack(generated.social_pack);
  let selectedPages = resolveSelectedFacebookPages(job, settings);
  let distribution = seedLegacyFacebookDistribution(
    normalizeFacebookDistribution(generated.facebook_distribution),
    selectedPages,
    job,
    facebookCaption,
  );

  log(`processing ${jobLabel}`);

  try {
    if (claimMode === "generate") {
      ensureOpenAiConfigured(settings);
      generated = await generatePackage(job, settings);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
      socialPack = normalizeSocialPack(generated.social_pack);
      selectedPages = resolveSelectedFacebookPages(job, settings);
      distribution = seedLegacyFacebookDistribution(
        normalizeFacebookDistribution(generated.facebook_distribution),
        selectedPages,
        job,
        facebookCaption,
      );

      ({ featuredImage, facebookImage, generated } = await ensureJobImages(job, settings, generated, {
        featuredImage,
        facebookImage,
      }));

      const scheduled = await updateJobProgress(job.id, {
        status: "scheduled",
        stage: "scheduled",
        generated_payload: generated,
        facebook_caption: facebookCaption,
        group_share_kit: groupShareKit,
        featured_image_id: featuredImage?.id || 0,
        facebook_image_result_id: facebookImage?.id || featuredImage?.id || 0,
      });

      const publishOn = String(scheduled?.job?.publish_on || "");
      log(
        publishOn
          ? `scheduled ${jobLabel} for ${publishOn} UTC`
          : `scheduled ${jobLabel} for the next daily publish slot`,
      );
      return scheduledHeartbeatState(job.id);
    }

    if (!generated.title || !generated.content_html) {
      ensureOpenAiConfigured(settings);
      generated = await generatePackage(job, settings);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
      socialPack = normalizeSocialPack(generated.social_pack);
      selectedPages = resolveSelectedFacebookPages(job, settings);
      distribution = seedLegacyFacebookDistribution(
        normalizeFacebookDistribution(generated.facebook_distribution),
        selectedPages,
        job,
        facebookCaption,
      );
    }

    ({ featuredImage, facebookImage, generated } = await ensureJobImages(job, settings, generated, {
      featuredImage,
      facebookImage,
    }));

    if (!postId || retryTarget === "publish" || retryTarget === "full") {
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

    const groupShareUrl = buildTrackedUrl(permalink, settings, generated, "facebook_group_manual");
    selectedPages = resolveSelectedFacebookPages(job, settings);
    assertFacebookConfigured(selectedPages);
    socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings);
    distribution = seedLegacyFacebookDistribution(
      normalizeFacebookDistribution(generated.facebook_distribution),
      selectedPages,
      job,
      facebookCaption,
    );

    const facebookResult = await publishFacebookDistribution({
      settings,
      generated,
      permalink,
      pages: selectedPages,
      socialPack,
      distribution,
      imageUrl: facebookImage?.url || featuredImage?.url || "",
      retryTarget,
    });

    distribution = facebookResult.distribution;
    const firstSuccess = firstSuccessfulDistributionResult(distribution);
    facebookPostId = firstSuccess?.post_id || "";
    facebookCommentId = firstSuccess?.comment_id || "";
    facebookCaption = firstSuccess?.caption || socialPack[0]?.caption || buildFallbackFacebookCaption(generated, settings.defaultCta);

    groupShareKit = finalizeGroupShareKit(groupShareKit, groupShareUrl);
    generated = {
      ...generated,
      social_pack: socialPack,
      facebook_distribution: distribution,
      facebook_urls: {
        ...(generated.facebook_urls || {}),
        facebook_comment: firstSuccess?.comment_url || "",
        facebook_group_manual: groupShareUrl,
        facebook_post: firstSuccess?.post_url || "",
      },
    };

    if (facebookResult.failedPages.length > 0) {
      const message = summarizeFacebookFailures(facebookResult.failedPages);
      await safeFailJob(job.id, {
        status: "partial_failure",
        stage,
        facebook_post_id: facebookPostId,
        facebook_comment_id: facebookCommentId,
        facebook_caption: facebookCaption,
        group_share_kit: groupShareKit,
        generated_payload: generated,
        error_message: message,
      });

      log(`${jobLabel} partially failed across Facebook pages: ${message}`);
      return {
        foundJob: true,
        last_loop_result: "job_failed",
        last_job_id: toInt(job.id),
        last_job_status: "partial_failure",
        last_error: message,
      };
    }

    await completeJob(job.id, {
      status: "completed",
      facebook_post_id: facebookPostId,
      facebook_comment_id: facebookCommentId,
      facebook_caption: facebookCaption,
      group_share_kit: groupShareKit,
      generated_payload: generated,
    });

    log(`completed ${jobLabel}; distributed to ${selectedPages.length} Facebook page(s)`);
    return completedHeartbeatState(job.id, "completed");
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
    return {
      foundJob: true,
      last_loop_result: "job_failed",
      last_job_id: toInt(job.id),
      last_job_status: status,
      last_error: message,
    };
  }
}

function inferClaimMode(job) {
  return String(job?.status || "") === "scheduled" ? "publish" : "generate";
}

async function ensureJobImages(job, settings, generated, current) {
  let featuredImage = current.featuredImage;
  let facebookImage = current.facebookImage;
  const manualOnly = (settings.imageGenerationMode || "manual_only") === "manual_only";

  if (manualOnly && (!featuredImage?.id || !facebookImage?.id)) {
    throw new Error("Manual-only launch mode requires both a real uploaded blog image and a real uploaded Facebook image.");
  }

  if (featuredImage?.id && !facebookImage?.id) {
    facebookImage = featuredImage;
  }

  if (facebookImage?.id && !featuredImage?.id) {
    featuredImage = facebookImage;
  }

  if (!featuredImage?.id && !facebookImage?.id) {
    if (manualOnly) {
      throw new Error("Manual-only launch mode blocked AI image generation because no uploaded images were provided.");
    }

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
  return wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/progress`, {
    method: "POST",
    body: payload,
  });
}

async function sendHeartbeatBestEffort(overrides = {}) {
  if (!hasWorkerConfig()) {
    return;
  }

  try {
    await wpRequest("/wp-json/kuchnia-twist/v1/worker/heartbeat", {
      method: "POST",
      body: {
        worker_version: WORKER_VERSION,
        enabled: config.enabled,
        run_once: config.runOnce,
        poll_seconds: config.pollSeconds,
        startup_delay_seconds: config.startupDelaySeconds,
        config_ok: hasWorkerConfig(),
        last_seen_at: new Date().toISOString(),
        last_loop_result: "",
        last_job_id: 0,
        last_job_status: "",
        last_error: "",
        ...overrides,
      },
    });
  } catch (error) {
    log(`heartbeat failed: ${formatError(error)}`);
  }
}

function hasWorkerConfig() {
  return Boolean(config.internalWordPressUrl && config.sharedSecret);
}

function idleHeartbeatState() {
  return {
    foundJob: false,
    last_loop_result: "no_due_jobs",
    last_job_id: 0,
    last_job_status: "",
    last_error: "",
  };
}

function completedHeartbeatState(jobId, status) {
  return {
    foundJob: true,
    last_loop_result: "completed",
    last_job_id: toInt(jobId),
    last_job_status: status,
    last_error: "",
  };
}

function scheduledHeartbeatState(jobId) {
  return {
    foundJob: true,
    last_loop_result: "scheduled",
    last_job_id: toInt(jobId),
    last_job_status: "scheduled",
    last_error: "",
  };
}

async function generatePackage(job, settings) {
  if ((job.content_type || "recipe") === "recipe") {
    const packageResult = await generateRecipeMasterPackage(job, settings);
    const selectedPages = resolveSelectedFacebookPages(job, settings);
    const masterPromptSocialPack = normalizeSocialPack(packageResult.social_pack);
    const socialPack = ensureSocialPackCoverage(packageResult.social_pack, selectedPages, packageResult, settings);
    const distributionSource =
      masterPromptSocialPack.length === 0
        ? "local_fallback"
        : masterPromptSocialPack.length < Math.max(1, selectedPages.length)
          ? "partial_fallback"
          : "master_prompt";

    return normalizeGeneratedPayload(
      {
        ...packageResult,
        facebook_caption: socialPack[0]?.caption || packageResult.facebook_caption,
        social_pack: socialPack,
        content_machine: {
          prompt_version: PROMPT_VERSION,
          publication_profile: resolvePublicationProfile(settings).name || settings.siteName,
          content_preset: "recipe",
          validator_summary: {
            repair_attempts: packageResult.validatorSummary.repair_attempts,
            repaired: packageResult.validatorSummary.repaired,
            last_validation_error: packageResult.validatorSummary.last_validation_error,
            distribution_source: distributionSource,
            target_pages: Math.max(1, selectedPages.length),
            social_variants: socialPack.length,
          },
        },
      },
      job,
    );
  }

  const { article, validatorSummary } = await generateCoreArticlePackage(job, settings);
  let distribution = {};
  let distributionSource = "model";

  try {
    distribution = await generateDistributionPack(job, settings, article);
  } catch (error) {
    distributionSource = "local_fallback";
    log(`distribution fallback for job #${job.id}: ${formatError(error)}`);
    distribution = buildLocalDistributionPack(article, settings);
  }

  return normalizeGeneratedPayload(
    {
      ...article,
      ...distribution,
      content_machine: {
        prompt_version: PROMPT_VERSION,
        publication_profile: resolvePublicationProfile(settings).name || settings.siteName,
        content_preset: job.content_type,
        validator_summary: {
          repair_attempts: validatorSummary.repair_attempts,
          repaired: validatorSummary.repaired,
          last_validation_error: validatorSummary.last_validation_error,
          distribution_source: distributionSource,
        },
      },
    },
    job,
  );
}

async function generateRecipeMasterPackage(job, settings) {
  const models = settings.contentMachine.models || {};
  const repairAttemptsAllowed = models.repair_enabled ? Math.max(0, Number(models.repair_attempts || 0)) : 0;
  const selectedPages = resolveSelectedFacebookPages(job, settings);
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    try {
      const content = await requestOpenAiChat(settings, [
        {
          role: "system",
          content:
            "You are the recipe publishing engine for a premium food publication. Return strict JSON only with no markdown fences. The JSON must contain: title, slug, excerpt, seo_description, content_html, recipe, image_prompt, image_alt, group_share_kit, and social_pack. social_pack must be an array of objects with hook, caption, and cta_hint. Never include the article URL inside captions because the tracked link will be posted in the first comment.",
        },
        {
          role: "user",
          content: buildRecipeMasterPrompt(job, settings, selectedPages, lastValidationError),
        },
      ]);

      if (!content || typeof content !== "string") {
        throw new Error("OpenAI did not return recipe master content.");
      }

      const normalized = normalizeGeneratedPayload(parseJsonObject(content), job);
      const validated = validateGeneratedPayload(normalized, job);
      const socialPack = ensureSocialPackCoverage(validated.social_pack, selectedPages, validated, settings);

      return {
        ...validated,
        social_pack: socialPack,
        validatorSummary: {
          repair_attempts: attempt,
          repaired: attempt > 0,
          last_validation_error: lastValidationError,
        },
      };
    } catch (error) {
      lastValidationError = formatError(error);
      if (attempt >= repairAttemptsAllowed) {
        throw error;
      }
    }
  }

  throw new Error("Recipe master generation failed unexpectedly.");
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

async function publishFacebookPost(settings, page, options) {
  const endpoint = options.imageUrl
    ? `https://graph.facebook.com/${settings.facebookGraphVersion}/${page.page_id}/photos`
    : `https://graph.facebook.com/${settings.facebookGraphVersion}/${page.page_id}/feed`;

  const params = new URLSearchParams();
  params.set("access_token", page.access_token);
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

async function publishFacebookComment(settings, page, postId, message) {
  const params = new URLSearchParams();
  params.set("access_token", page.access_token);
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
  const contentMachine = resolveContentMachine(raw);

  return {
    siteName: raw.site_name || config.fallbackSiteName,
    siteUrl: trimTrailingSlash(raw.site_url || config.publicWordPressUrl || config.internalWordPressUrl),
    brandVoice: contentMachine.publicationProfile.voice_brief || raw.brand_voice || config.fallbackBrandVoice,
    articlePrompt: contentMachine.channelPresets.article.guidance || raw.article_prompt || "Write substantial, original food articles with a premium editorial tone.",
    defaultCta: raw.default_cta || contentMachine.defaultCta || "Read the full article on the blog.",
    imageStyle: contentMachine.channelPresets.image.guidance || raw.image_style || "Natural food photography, premium editorial light, no text overlays.",
    imageGenerationMode: raw.image_generation_mode || "manual_only",
    facebookGraphVersion: raw.facebook_graph_version || "v22.0",
    facebookPageId: raw.facebook_page_id || "",
    facebookPageAccessToken: raw.facebook_page_access_token || "",
    facebookPages: normalizeFacebookPages(raw.facebook_pages, raw.facebook_page_id || "", raw.facebook_page_access_token || ""),
    openaiModel: contentMachine.models.text_model || raw.openai_model || config.fallbackTextModel,
    openaiImageModel: contentMachine.models.image_model || raw.openai_image_model || "gpt-image-1.5",
    openaiApiKey: raw.openai_api_key || config.fallbackOpenAiKey,
    openaiBaseUrl: trimTrailingSlash(raw.openai_base_url || config.fallbackOpenAiBaseUrl),
    utmSource: raw.utm_source || "facebook",
    utmCampaignPrefix: raw.utm_campaign_prefix || "kuchnia-twist",
    contentMachine,
  };
}

async function generateCoreArticlePackage(job, settings) {
  const models = settings.contentMachine.models || {};
  const repairAttemptsAllowed = models.repair_enabled ? Math.max(0, Number(models.repair_attempts || 0)) : 0;
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    try {
      const content = await requestOpenAiChat(settings, [
        {
          role: "system",
          content:
            "You are the editorial engine for a premium food publication. Return strict JSON only with no markdown fences. The JSON must contain: title, slug, excerpt, seo_description, content_html, and recipe. content_html must be clean HTML for WordPress using paragraphs, headings, lists, and blockquotes only.",
        },
        {
          role: "user",
          content: buildCoreArticlePrompt(job, settings, lastValidationError),
        },
      ]);

      if (!content || typeof content !== "string") {
        throw new Error("OpenAI did not return article content.");
      }

      const normalized = normalizeGeneratedPayload(parseJsonObject(content), job);
      const validated = validateGeneratedPayload(normalized, job);

      return {
        article: {
          title: validated.title,
          slug: validated.slug,
          excerpt: validated.excerpt,
          seo_description: validated.seo_description,
          content_html: validated.content_html,
          recipe: validated.recipe,
        },
        validatorSummary: {
          repair_attempts: attempt,
          repaired: attempt > 0,
          last_validation_error: lastValidationError,
        },
      };
    } catch (error) {
      lastValidationError = formatError(error);
      if (attempt >= repairAttemptsAllowed) {
        throw error;
      }
    }
  }

  throw new Error("Core article generation failed unexpectedly.");
}

async function generateDistributionPack(job, settings, article) {
  const content = await requestOpenAiChat(settings, [
    {
      role: "system",
      content:
        "You are writing distribution copy for a premium food publication. Return strict JSON only with no markdown fences. The JSON must contain: facebook_caption, group_share_kit, image_prompt, and image_alt. Never include links in facebook_caption or group_share_kit.",
    },
    {
      role: "user",
      content: buildDerivativePrompt(job, settings, article),
    },
  ]);

  if (!content || typeof content !== "string") {
    throw new Error("OpenAI did not return distribution content.");
  }

  const parsed = parseJsonObject(content);
  return {
    facebook_caption: cleanMultilineText(parsed.facebook_caption || parsed.facebookCaption),
    group_share_kit: cleanMultilineText(parsed.group_share_kit || parsed.groupShareKit),
    image_prompt: cleanMultilineText(parsed.image_prompt || parsed.imagePrompt),
    image_alt: cleanText(parsed.image_alt || parsed.imageAlt),
  };
}

async function requestOpenAiChat(settings, messages) {
  const payload = await openAiJsonRequest(settings, "/chat/completions", {
    model: settings.openaiModel,
    messages,
  });

  return payload?.choices?.[0]?.message?.content;
}

function resolveContentMachine(raw) {
  const provided = isPlainObject(raw.content_machine) ? raw.content_machine : {};
  const publicationProfile = isPlainObject(provided.publication_profile) ? provided.publication_profile : {};
  const contentPresets = isPlainObject(provided.content_presets) ? provided.content_presets : {};
  const channelPresets = isPlainObject(provided.channel_presets) ? provided.channel_presets : {};
  const cadence = isPlainObject(provided.cadence) ? provided.cadence : {};
  const models = isPlainObject(provided.models) ? provided.models : {};

  return {
    promptVersion: String(provided.prompt_version || PROMPT_VERSION),
    publicationProfile: {
      id: String(publicationProfile.id || "default"),
      name: String(publicationProfile.name || raw.site_name || config.fallbackSiteName),
      voice_brief: cleanText(publicationProfile.voice_brief || raw.brand_voice || config.fallbackBrandVoice),
      do_guidance: cleanMultilineText(publicationProfile.do_guidance || ""),
      dont_guidance: cleanMultilineText(publicationProfile.dont_guidance || raw.article_prompt || ""),
      banned_claims: cleanMultilineText(publicationProfile.banned_claims || ""),
      shared_link_policy: cleanMultilineText(publicationProfile.shared_link_policy || "Include at least three internal Kuchnia Twist links inside the article body."),
    },
    contentPresets: {
      recipe: normalizePreset(contentPresets.recipe, "Write a recipe-led article with strong sensory writing and practical kitchen guidance.", 1200),
      food_fact: normalizePreset(contentPresets.food_fact, "Write a fact-led article that answers the question directly, corrects confusion, and gives a practical takeaway.", 1100),
      food_story: normalizePreset(contentPresets.food_story, "Write a publication-voice kitchen essay with a clear observation and a reflective close.", 1100),
    },
    channelPresets: {
      recipe_master: {
        guidance: cleanMultilineText(
          channelPresets.recipe_master?.guidance ||
            raw.recipe_master_prompt ||
            "Generate a complete recipe content pack with blog copy, recipe card data, image direction, and one unique Facebook hook/caption variant per selected page."
        ),
      },
      article: {
        guidance: cleanMultilineText(channelPresets.article?.guidance || raw.article_prompt || "Write substantial, original food articles with a premium editorial tone."),
      },
      facebook_caption: {
        guidance: cleanMultilineText(channelPresets.facebook_caption?.guidance || "Short, hook-led Facebook caption with no link."),
      },
      group_share_kit: {
        guidance: cleanMultilineText(channelPresets.group_share_kit?.guidance || "Useful manual-share copy for food groups with no link."),
      },
      image: {
        guidance: cleanMultilineText(channelPresets.image?.guidance || raw.image_style || "Natural food photography, premium editorial light, no text overlays."),
      },
    },
    cadence: {
      mode: String(cadence.mode || "generate_now_publish_daily"),
      daily_publish_time: String(cadence.daily_publish_time || "09:00"),
      timezone: String(cadence.timezone || "UTC"),
      posts_per_day: Math.max(1, Number(cadence.posts_per_day || 1)),
    },
    models: {
      text_model: String(models.text_model || raw.openai_model || config.fallbackTextModel),
      image_model: String(models.image_model || raw.openai_image_model || "gpt-image-1.5"),
      repair_enabled: Boolean(models.repair_enabled ?? true),
      repair_attempts: Math.max(0, Number(models.repair_attempts ?? 1)),
    },
    defaultCta: cleanText(provided.default_cta || raw.default_cta || "Read the full article on the blog."),
  };
}

function normalizeFacebookPages(rawPages, legacyPageId = "", legacyAccessToken = "") {
  const pages = [];
  const pageMap = new Map();

  if (Array.isArray(rawPages)) {
    for (const raw of rawPages) {
      if (!isPlainObject(raw)) {
        continue;
      }

      const pageId = cleanText(raw.page_id || raw.pageId);
      const label = cleanText(raw.label || raw.name || "");
      const accessToken = cleanMultilineText(raw.access_token || raw.accessToken || "");
      const active = Boolean(raw.active);

      if (!pageId || !label) {
        continue;
      }

      pageMap.set(pageId, {
        page_id: pageId,
        label,
        access_token: accessToken,
        active,
      });
    }
  }

  const legacyId = cleanText(legacyPageId);
  const legacyToken = cleanMultilineText(legacyAccessToken);
  if (legacyId && !pageMap.has(legacyId)) {
    pageMap.set(legacyId, {
      page_id: legacyId,
      label: "Primary Page",
      access_token: legacyToken,
      active: Boolean(legacyToken),
    });
  }

  for (const page of pageMap.values()) {
    if (!page.page_id) {
      continue;
    }
    pages.push(page);
  }

  return pages;
}

function normalizePreset(value, fallbackGuidance, fallbackMinWords) {
  const preset = isPlainObject(value) ? value : {};
  return {
    label: cleanText(preset.label),
    guidance: cleanMultilineText(preset.guidance || fallbackGuidance),
    min_words: Math.max(800, Number(preset.min_words || fallbackMinWords)),
  };
}

function resolvePublicationProfile(settings) {
  return settings.contentMachine.publicationProfile || {};
}

function resolveContentPreset(settings, contentType) {
  const presets = settings.contentMachine.contentPresets || {};
  return presets[contentType] || presets.recipe || {};
}

function buildCoreArticlePrompt(job, settings, repairNote = "") {
  const profile = resolvePublicationProfile(settings);
  const preset = resolveContentPreset(settings, job.content_type);
  const internalLinkLibrary = internalLinkTargetsForJob(job)
    .map((item) => `- ${item.label}: [kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
    .join("\n");

  const contentTypeNotes = {
    recipe: [
      "Write a recipe-led article with strong sensory writing and practical kitchen guidance.",
      "Do not place the full ingredients and method inside the article body; the structured recipe box will render them separately.",
      "Use H2 sections for: why this works, ingredient notes, practical cooking method, and serving or storage guidance.",
      "The recipe object must include prep_time, cook_time, total_time, yield, ingredients[], and instructions[].",
    ],
    food_fact: [
      "Write a fact-led article that answers the kitchen question directly, corrects confusion, explains why it matters, and gives a practical takeaway.",
      "Use H2 sections for: the direct answer, what is happening, a common mistake, and a practical takeaway.",
      "The recipe object must contain empty strings and empty arrays.",
    ],
    food_story: [
      "Write a publication-voice kitchen essay with a clear narrative arc, practical food insight, and a reflective close.",
      "Do not write fabricated first-person memories, invented reporting, or autobiography.",
      "Use H2 sections for: the central observation, practical meaning in home cooking, and a reflective close.",
      "The recipe object must contain empty strings and empty arrays.",
    ],
  };

  return [
    `Publication profile: ${profile.name || settings.siteName}`,
    `Topic: ${job.topic}`,
    `Content type: ${job.content_type}`,
    `Voice brief: ${profile.voice_brief || settings.brandVoice}`,
    `Do guidance: ${profile.do_guidance || "Be specific, useful, and calm."}`,
    `Do not guidance: ${profile.dont_guidance || "Avoid filler and generic SEO phrasing."}`,
    `Banned claims: ${profile.banned_claims || "Avoid unsupported health and expert claims."}`,
    `Shared link policy: ${profile.shared_link_policy || "Include at least three internal links."}`,
    `Article body guidance: ${settings.contentMachine.channelPresets.article.guidance}`,
    `Content preset guidance: ${preset.guidance || ""}`,
    `Length target: ${preset.min_words || config.minWords}-${config.maxWords} words for the main article body.`,
    job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate a strong, editorial article title.",
    "Global rules:",
    "- Write original, useful, human-sounding content.",
    "- Use a polished magazine tone, not generic SEO filler or padded introductions.",
    "- Do not mention AI or say the article was generated.",
    "- content_html must begin with an introduction paragraph and use clean H2 sections.",
    "- The opening paragraph must be concrete and specific, not generic throat-clearing.",
    "- excerpt should feel distinct, specific, and useful, not like a restatement of the title.",
    "- seo_description should stay under 155 characters.",
    "- Include at least three internal Kuchnia Twist links inside content_html.",
    "- Avoid copy like 'when it comes to', 'in today's world', 'this article explores', or other generic filler openings.",
    ...(contentTypeNotes[job.content_type] || contentTypeNotes.recipe),
    "Preferred internal link library:",
    internalLinkLibrary,
    repairNote ? `Previous attempt failed validation: ${repairNote}` : "",
    "JSON contract:",
    "{",
    '  "title": "string",',
    '  "slug": "kebab-case-string",',
    '  "excerpt": "string",',
    '  "seo_description": "string",',
    '  "content_html": "string",',
    '  "recipe": {',
    '    "prep_time": "string",',
    '    "cook_time": "string",',
    '    "total_time": "string",',
    '    "yield": "string",',
    '    "ingredients": ["string"],',
    '    "instructions": ["string"]',
    "  }",
    "}",
  ]
    .filter(Boolean)
    .join("\n");
}

function buildRecipeMasterPrompt(job, settings, selectedPages, repairNote = "") {
  const profile = resolvePublicationProfile(settings);
  const preset = resolveContentPreset(settings, "recipe");
  const selectedLabels = selectedPages.map((page) => page.label).filter(Boolean);
  const socialPackCount = Math.max(1, selectedLabels.length || 1);
  const internalLinkLibrary = internalLinkTargetsForJob({ ...job, content_type: "recipe" })
    .map((item) => `- ${item.label}: [kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
    .join("\n");
  const masterPrompt =
    settings.contentMachine.channelPresets.recipe_master?.guidance ||
    "Generate a complete recipe content pack with a blog article, structured recipe card, image direction, and one unique Facebook variant per selected page.";

  return [
    `Publication profile: ${profile.name || settings.siteName}`,
    `Dish name: ${job.topic}`,
    `Voice brief: ${profile.voice_brief || settings.brandVoice}`,
    `Recipe master prompt: ${masterPrompt}`,
    `Recipe preset guidance: ${preset.guidance || ""}`,
    `Recipe article guidance: ${settings.contentMachine.channelPresets.article.guidance}`,
    `Facebook caption guidance: ${settings.contentMachine.channelPresets.facebook_caption.guidance}`,
    `Group share guidance: ${settings.contentMachine.channelPresets.group_share_kit.guidance}`,
    `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
    `Shared link policy: ${profile.shared_link_policy || "Include at least three internal links."}`,
    job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate the best article title yourself.",
    `Create exactly ${socialPackCount} social variants in social_pack.`,
    selectedLabels.length ? `Target Facebook pages: ${selectedLabels.join(" | ")}` : "Target Facebook pages: Primary Facebook page",
    "Recipe output rules:",
    "- Write an original, practical recipe article with a vivid opening and clear kitchen value.",
    "- content_html must begin with an introduction paragraph and use H2 sections.",
    "- Do not paste the full ingredient list and full numbered method into the article body; keep those for the recipe object.",
    "- The recipe object must include prep_time, cook_time, total_time, yield, ingredients[], and instructions[].",
    "- excerpt should feel distinct and useful, not generic.",
    "- seo_description should stay under 155 characters.",
    "- Include at least three internal Kuchnia Twist links inside content_html.",
    "- social_pack captions must stay short, hook-led, and must not include the article URL.",
    "- Each social_pack item must feel different in angle so multiple pages are not posting clones.",
    "- group_share_kit should be a concise manual share text with [LINK] placeholder support.",
    "Preferred internal link library:",
    internalLinkLibrary,
    repairNote ? `Previous attempt failed validation: ${repairNote}` : "",
    "JSON contract:",
    "{",
    '  "title": "string",',
    '  "slug": "kebab-case-string",',
    '  "excerpt": "string",',
    '  "seo_description": "string",',
    '  "content_html": "string",',
    '  "image_prompt": "string",',
    '  "image_alt": "string",',
    '  "group_share_kit": "string",',
    '  "recipe": {',
    '    "prep_time": "string",',
    '    "cook_time": "string",',
    '    "total_time": "string",',
    '    "yield": "string",',
    '    "ingredients": ["string"],',
    '    "instructions": ["string"]',
    "  },",
    '  "social_pack": [{"hook":"string","caption":"string","cta_hint":"string"}]',
    "}",
  ]
    .filter(Boolean)
    .join("\n");
}

function buildDerivativePrompt(job, settings, article) {
  const profile = resolvePublicationProfile(settings);
  const preset = resolveContentPreset(settings, job.content_type);
  const headings = Array.from(String(article.content_html || "").matchAll(/<h2\b[^>]*>(.*?)<\/h2>/gi))
    .map((match) => cleanText(String(match[1] || "").replace(/<[^>]+>/g, " ")))
    .filter(Boolean)
    .slice(0, 5);
  const articleSummary = trimText(cleanText(String(article.content_html || "").replace(/<[^>]+>/g, " ")), 900);

  return [
    `Publication profile: ${profile.name || settings.siteName}`,
    `Content type: ${job.content_type}`,
    `Voice brief: ${profile.voice_brief || settings.brandVoice}`,
    `Preset guidance: ${preset.guidance || ""}`,
    `Facebook caption guidance: ${settings.contentMachine.channelPresets.facebook_caption.guidance}`,
    `Group share guidance: ${settings.contentMachine.channelPresets.group_share_kit.guidance}`,
    `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
    `Article title: ${article.title}`,
    `Excerpt: ${article.excerpt}`,
    headings.length ? `H2 sections: ${headings.join(" | ")}` : "",
    `Article summary: ${articleSummary}`,
    "Return only these JSON keys: facebook_caption, group_share_kit, image_prompt, image_alt.",
    "facebook_caption must stay short, hook-led, and never include a link.",
    "group_share_kit must feel natural for manual posting into food groups and never include a link.",
    "image_prompt must describe a realistic premium food photo with no text overlays.",
  ]
    .filter(Boolean)
    .join("\n");
}

function resolveSelectedFacebookPages(job, settings) {
  const availablePages = Array.isArray(settings.facebookPages)
    ? settings.facebookPages.filter((page) => page?.page_id && page?.access_token && page?.active !== false)
    : [];
  const pageMap = new Map(availablePages.map((page) => [String(page.page_id), page]));
  const requested = Array.isArray(job?.request_payload?.selected_facebook_pages)
    ? job.request_payload.selected_facebook_pages
        .filter((page) => isPlainObject(page))
        .map((page) => ({
          page_id: cleanText(page.page_id || page.pageId),
          label: cleanText(page.label || ""),
        }))
        .filter((page) => page.page_id)
    : [];

  if (!requested.length) {
    return availablePages.length ? [availablePages[0]] : [];
  }

  return requested
    .map((page) => {
      const found = pageMap.get(page.page_id);
      if (!found) {
        return null;
      }

      return {
        ...found,
        label: page.label || found.label,
      };
    })
    .filter(Boolean);
}

function normalizeSocialPack(value) {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item, index) => {
      if (!isPlainObject(item)) {
        return null;
      }

      const hook = cleanText(item.hook || item.headline || "");
      const caption = cleanMultilineText(item.caption || item.body || "");
      const ctaHint = cleanText(item.cta_hint || item.ctaHint || "");

      if (!hook && !caption) {
        return null;
      }

      return {
        id: cleanText(item.id || `variant-${index + 1}`),
        hook,
        caption,
        cta_hint: ctaHint,
      };
    })
    .filter(Boolean);
}

function buildFallbackSocialPack(article, pages, settings) {
  const count = Math.max(1, pages.length || 1);
  const angles = [
    (title) => ({ hook: `This ${title} is weeknight comfort without the fuss.`, caption: `${title} with a creamy, practical finish you can actually pull off on a busy evening.` }),
    (title) => ({ hook: `The best part of ${title}? The texture.`, caption: `${title} that balances crisp edges, soft centers, and a finish worth saving for later.` }),
    (title) => ({ hook: `If dinner needs to be easier, start with ${title}.`, caption: `${title} built for home cooks who want flavor, clear steps, and no wasted ingredients.` }),
    (title) => ({ hook: `${title} is the kind of recipe people ask for twice.`, caption: `${title} with enough detail to make it reliable, not just pretty on the plate.` }),
    (title) => ({ hook: `Save this ${title} for the next time you need a dependable win.`, caption: `${title} with practical notes, a strong method, and the kind of payoff that makes repeat cooking easy.` }),
  ];

  return Array.from({ length: count }, (_, index) => {
    const page = pages[index] || null;
    const variant = angles[index % angles.length](article.title);
    return {
      id: `variant-${index + 1}`,
      hook: variant.hook,
      caption: variant.caption,
      cta_hint: page?.label ? `Use on ${page.label}` : "General recipe post",
    };
  });
}

function ensureSocialPackCoverage(value, pages, article, settings) {
  const desiredCount = Math.max(1, Array.isArray(pages) ? pages.length : 0);
  const normalized = normalizeSocialPack(value);
  const fallback = buildFallbackSocialPack(article, pages, settings);

  return Array.from({ length: desiredCount }, (_, index) => {
    return normalized[index] || fallback[index] || fallback[fallback.length - 1];
  }).filter(Boolean);
}

function normalizeFacebookDistribution(value) {
  if (!isPlainObject(value) || !isPlainObject(value.pages)) {
    return { pages: {} };
  }

  return {
    pages: Object.fromEntries(
      Object.entries(value.pages).map(([pageId, raw]) => {
        const page = isPlainObject(raw) ? raw : {};
        return [
          pageId,
          {
            page_id: cleanText(page.page_id || pageId),
            label: cleanText(page.label || ""),
            hook: cleanText(page.hook || ""),
            caption: cleanMultilineText(page.caption || ""),
            cta_hint: cleanText(page.cta_hint || page.ctaHint || ""),
            post_id: cleanText(page.post_id || page.postId || ""),
            post_url: cleanText(page.post_url || page.postUrl || ""),
            comment_id: cleanText(page.comment_id || page.commentId || ""),
            comment_url: cleanText(page.comment_url || page.commentUrl || ""),
            status: cleanText(page.status || ""),
            error: cleanText(page.error || ""),
          },
        ];
      }),
    ),
  };
}

function seedLegacyFacebookDistribution(distribution, pages, job, facebookCaption) {
  const normalized = normalizeFacebookDistribution(distribution);
  if (Object.keys(normalized.pages).length > 0) {
    return normalized;
  }

  const legacyPostId = cleanText(job?.facebook_post_id || "");
  const legacyCommentId = cleanText(job?.facebook_comment_id || "");
  if (!legacyPostId && !legacyCommentId) {
    return normalized;
  }

  const page = pages[0];
  if (!page?.page_id) {
    return normalized;
  }

  normalized.pages[page.page_id] = {
    page_id: page.page_id,
    label: cleanText(page.label || ""),
    hook: "",
    caption: cleanMultilineText(facebookCaption || ""),
    cta_hint: "",
    post_id: legacyPostId,
    post_url: buildFacebookPostUrl(legacyPostId),
    comment_id: legacyCommentId,
    comment_url: buildFacebookCommentUrl(legacyPostId, legacyCommentId),
    status: legacyPostId && legacyCommentId ? "completed" : (legacyPostId ? "comment_failed" : "post_failed"),
    error: "",
  };

  return normalized;
}

function buildLocalDistributionPack(article, settings) {
  return {
    facebook_caption: buildFallbackFacebookCaption(article, settings.defaultCta),
    group_share_kit: `${article.title}\n\n${article.excerpt}\n\nShare the full article if it feels useful for your kitchen.`,
    image_prompt: [
      settings.contentMachine.channelPresets.image.guidance || settings.imageStyle,
      `Feature the dish or idea from "${article.title}" in an appetizing, realistic editorial composition.`,
      "No text overlays, no logos, natural kitchen detail.",
    ]
      .filter(Boolean)
      .join("\n"),
    image_alt: article.title,
  };
}

function validateGeneratedPayload(generated, job) {
  const text = cleanText(generated.content_html.replace(/<[^>]+>/g, " "));
  const opening = cleanText((generated.content_html.match(/<p>(.*?)<\/p>/i)?.[1] || generated.content_html).replace(/<[^>]+>/g, " ")).toLowerCase();
  const h2Count = (generated.content_html.match(/<h2\b/gi) || []).length;
  const wordCount = text.split(/\s+/).filter(Boolean).length;
  const minimumWords = {
    recipe: 1200,
    food_fact: 1100,
    food_story: 1100,
  }[job.content_type] || 1100;
  const blockedPhrases = [
    "when it comes to",
    "in today",
    "this article explores",
    "whether you are",
    "few things are as",
    "as an ai",
    "generated by ai",
    "lorem ipsum",
  ];

  if (wordCount < minimumWords) {
    throw new Error(`The generated article body was too short for launch publishing standards. Minimum words for ${job.content_type} is ${minimumWords}.`);
  }

  if (h2Count < 2) {
    throw new Error("The generated article did not include enough H2 structure.");
  }

  if (generated.excerpt.length < 90) {
    throw new Error("The generated excerpt was too short for a launch-quality archive card.");
  }

  if (generated.seo_description.length < 90) {
    throw new Error("The generated SEO description was too short for launch quality standards.");
  }

  for (const phrase of blockedPhrases) {
    if (opening.includes(phrase)) {
      throw new Error(`The generated article used a blocked generic opening phrase: ${phrase}`);
    }
  }

  if (countInternalLinks(generated.content_html) < 3) {
    throw new Error("The generated article did not include enough internal Kuchnia Twist links.");
  }

  if (job.content_type === "food_story" && /\b(i|my|me|mine)\b/i.test(text)) {
    throw new Error("Food story output used first-person voice, which is blocked for the publication-voice essay format.");
  }

  return generated;
}

function normalizeGeneratedPayload(raw, job) {
  const source = isPlainObject(raw) ? raw : {};
  const titleOverride = cleanText(job?.title_override || "");
  const title = titleOverride || cleanText(source.title) || cleanText(job?.topic) || "Fresh from Kuchnia Twist";
  const slug = normalizeSlug(source.slug || title);
  const sourceContentText = cleanText(String(source.content_html || source.contentHtml || "").replace(/<[^>]+>/g, " "));
  const fallbackExcerpt = trimText(sourceContentText.split(/(?<=[.!?])\s+/)[0] || sourceContentText, 220);
  const excerpt = trimText(cleanText(source.excerpt), 220) || fallbackExcerpt || `${title} on Kuchnia Twist.`;
  const seoDescription =
    trimText(cleanText(source.seo_description || source.seoDescription), 155) ||
    trimText(excerpt, 155);
  const contentHtml = ensureInternalLinks(normalizeHtml(source.content_html || source.contentHtml || ""), job);
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
    social_pack: normalizeSocialPack(source.social_pack || source.socialPack),
    facebook_distribution: normalizeFacebookDistribution(source.facebook_distribution || source.facebookDistribution),
    assets: isPlainObject(source.assets) ? source.assets : {},
    facebook_urls: isPlainObject(source.facebook_urls) ? source.facebook_urls : {},
    content_machine: isPlainObject(source.content_machine) ? source.content_machine : {},
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

function countInternalLinks(contentHtml) {
  const shortcodeCount = (String(contentHtml || "").match(/\[kuchnia_twist_link\s+slug=/gi) || []).length;
  const anchorCount = (String(contentHtml || "").match(/<a\s+[^>]*href=/gi) || []).length;
  return shortcodeCount + anchorCount;
}

function internalLinkTargetsForJob(job) {
  const shared = [
    { slug: "editorial-policy", label: "Editorial Policy" },
    { slug: "about", label: "About" },
    { slug: "contact", label: "Contact" },
  ];

  const byType = {
    recipe: [
      { slug: "recipes", label: "Recipes" },
      { slug: "why-onions-need-more-time-than-most-recipes-admit", label: "Why Onions Need More Time Than Most Recipes Admit" },
      { slug: "what-tomato-paste-actually-does-in-a-pan", label: "What Tomato Paste Actually Does in a Pan" },
      { slug: "food-stories", label: "Food Stories" },
    ],
    food_fact: [
      { slug: "food-facts", label: "Food Facts" },
      { slug: "recipes", label: "Recipes" },
      { slug: "crispy-sheet-pan-chicken-with-caramelized-onions-and-potatoes", label: "Crispy Sheet-Pan Chicken with Caramelized Onions and Potatoes" },
      { slug: "tomato-butter-beans-on-toast-with-garlic-and-lemon", label: "Tomato Butter Beans on Toast with Garlic and Lemon" },
    ],
    food_story: [
      { slug: "food-stories", label: "Food Stories" },
      { slug: "recipes", label: "Recipes" },
      { slug: "the-quiet-value-of-a-soup-pot-on-a-busy-weeknight", label: "The Quiet Value of a Soup Pot on a Busy Weeknight" },
      { slug: "creamy-mushroom-barley-soup-for-busy-evenings", label: "Creamy Mushroom Barley Soup for Busy Evenings" },
    ],
  };

  return [...(byType[job.content_type] || byType.recipe), ...shared];
}

function ensureInternalLinks(contentHtml, job) {
  if (!contentHtml || countInternalLinks(contentHtml) >= 3) {
    return contentHtml;
  }

  const selections = internalLinkTargetsForJob(job).slice(0, 3);
  if (!selections.length) {
    return contentHtml;
  }

  const links = selections
    .map((item) => `[kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
    .join(", ");

  return `${contentHtml}\n<p>Keep reading across the journal: ${links}.</p>`;
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

function buildFacebookPostMessage(variant, fallbackCaption) {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || fallbackCaption || "");

  if (hook && caption) {
    return `${hook}\n\n${caption}`.trim();
  }

  return hook || caption;
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

function buildFacebookCommentUrl(postId, commentId) {
  const postUrl = buildFacebookPostUrl(postId);
  if (!postUrl || !commentId) {
    return "";
  }

  const fragment = String(commentId).includes("_") ? String(commentId).split("_").pop() : String(commentId);
  return fragment ? `${postUrl}?comment_id=${fragment}` : postUrl;
}

async function publishFacebookDistribution({ settings, generated, permalink, pages, socialPack, distribution, imageUrl }) {
  const nextDistribution = normalizeFacebookDistribution(distribution);
  const failedPages = [];

  for (let index = 0; index < pages.length; index += 1) {
    const page = pages[index];
    const pageLabel = page.label || page.page_id;
    const existing = nextDistribution.pages[page.page_id] || {};
    const variant = socialPack[index] || socialPack[index % socialPack.length] || {};
    const message = buildFacebookPostMessage(variant, buildFallbackFacebookCaption(generated, settings.defaultCta));
    const commentUrl = buildTrackedUrl(permalink, settings, generated, `facebook_comment_${normalizeSlug(pageLabel)}`);
    const pageState = {
      ...existing,
      page_id: page.page_id,
      label: pageLabel,
      hook: cleanText(variant.hook || existing.hook || ""),
      caption: cleanMultilineText(variant.caption || existing.caption || ""),
      cta_hint: cleanText(variant.cta_hint || existing.cta_hint || ""),
      post_url: cleanText(existing.post_url || (existing.post_id ? buildFacebookPostUrl(existing.post_id) : "")),
      comment_url: cleanText(existing.comment_url || (existing.post_id && existing.comment_id ? buildFacebookCommentUrl(existing.post_id, existing.comment_id) : "")),
      status: cleanText(existing.status || ""),
      error: "",
    };

    try {
      if (!pageState.post_id) {
        const post = await publishFacebookPost(settings, page, {
          message,
          imageUrl,
        });

        pageState.post_id = post.postId;
        pageState.post_url = post.url || "";
      }

      if (!pageState.comment_id) {
        pageState.comment_id = await publishFacebookComment(
          settings,
          page,
          pageState.post_id,
          buildFacebookComment(settings.defaultCta, commentUrl),
        );
        pageState.comment_url = buildFacebookCommentUrl(pageState.post_id, pageState.comment_id);
      }

      pageState.status = "completed";
      nextDistribution.pages[page.page_id] = pageState;
    } catch (error) {
      pageState.status = pageState.post_id ? "comment_failed" : "post_failed";
      pageState.error = formatError(error);
      nextDistribution.pages[page.page_id] = pageState;
      failedPages.push({
        page_id: page.page_id,
        label: pageLabel,
        error: pageState.error,
        stage: pageState.status,
      });
    }
  }

  return {
    distribution: nextDistribution,
    failedPages,
  };
}

function firstSuccessfulDistributionResult(distribution) {
  const normalized = normalizeFacebookDistribution(distribution);
  for (const page of Object.values(normalized.pages)) {
    if (page?.post_id || page?.comment_id) {
      return page;
    }
  }

  return null;
}

function summarizeFacebookFailures(failedPages) {
  if (!Array.isArray(failedPages) || failedPages.length === 0) {
    return "";
  }

  return failedPages
    .map((page) => `${page.label || page.page_id}: ${page.error}`)
    .join(" | ");
}

function firstAttachment(...items) {
  return items.find((item) => item && item.id && item.url) || null;
}

function ensureOpenAiConfigured(settings) {
  if (!settings.openaiApiKey) {
    throw new Error("OpenAI API key is missing. Add it in the plugin settings or container environment.");
  }
}

function assertFacebookConfigured(pages) {
  if (!Array.isArray(pages) || pages.length === 0) {
    throw new Error("No active Facebook pages are configured for recipe distribution.");
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

  return `${text.slice(0, maxLength - 3).trim()}...`;
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

main().catch(async (error) => {
  const message = formatError(error);
  await sendHeartbeatBestEffort({
    last_loop_result: "fatal_error",
    last_error: message,
  });
  log(`fatal error: ${message}`);
  process.exit(1);
});
