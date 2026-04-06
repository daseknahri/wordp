import { Buffer } from "node:buffer";
import { setTimeout as sleep } from "node:timers/promises";

const SECOND_MS = 1000;
const IDLE_MS = 60 * 60 * SECOND_MS;
const WORKER_VERSION = "1.7.5";
const PROMPT_VERSION = "recipe-master-v9";
const QUALITY_SCORE_THRESHOLD = 75;
const RECIPE_HOOK_ANGLES = [
  {
    key: "quick_dinner",
    label: "Quick Dinner",
    instruction: "Lead with speed, weeknight relief, and a realistic payoff for busy cooks.",
  },
  {
    key: "comfort_food",
    label: "Comfort Food",
    instruction: "Lean into warmth, coziness, craveability, and repeat-cook appeal.",
  },
  {
    key: "budget_friendly",
    label: "Budget Friendly",
    instruction: "Emphasize value, pantry practicality, and generous payoff without sounding cheap.",
  },
  {
    key: "beginner_friendly",
    label: "Beginner Friendly",
    instruction: "Make the recipe feel approachable, confidence-building, and easy to follow.",
  },
  {
    key: "crowd_pleaser",
    label: "Crowd Pleaser",
    instruction: "Frame the dish as dependable, family-friendly, and easy to serve again.",
  },
  {
    key: "better_than_takeout",
    label: "Better Than Takeout",
    instruction: "Focus on restaurant-style payoff with simpler home-kitchen control.",
  },
];

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
  let preferredAngle = resolvePreferredAngle(job);
  let distribution = seedLegacyFacebookDistribution(
    normalizeFacebookDistribution(generated.facebook_distribution),
    selectedPages,
    job,
    facebookCaption,
  );

  log(`processing ${jobLabel}`);

  try {
    assertRecipeDistributionTargets(job, selectedPages);

    if (claimMode === "generate") {
      ensureOpenAiConfigured(settings);
      generated = await generatePackage(job, settings);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
      socialPack = normalizeSocialPack(generated.social_pack);
      selectedPages = resolveSelectedFacebookPages(job, settings);
      assertRecipeDistributionTargets(job, selectedPages);
      preferredAngle = resolvePreferredAngle(job);
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

      socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, preferredAngle);
      const qualitySummary = buildQualitySummary(job, { ...generated, social_pack: socialPack }, settings, {
        selectedPages,
        featuredImage,
        facebookImage,
      });
      generated = mergeValidatorSummary(
        {
          ...generated,
          social_pack: socialPack,
        },
        {
          ...qualitySummary,
          target_pages: qualitySummary.quality_checks?.target_pages || selectedPages.length,
          social_variants: qualitySummary.quality_checks?.social_variants || socialPack.length,
        },
      );
      facebookCaption = generated.facebook_caption;

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
      if (isFutureUtcTimestamp(publishOn)) {
        log(`scheduled ${jobLabel} for ${publishOn} UTC`);
        return scheduledHeartbeatState(job.id);
      }

      job = scheduled?.job || job;
      generated = normalizeGeneratedPayload(job.generated_payload || generated, job);
      facebookCaption = String(job.facebook_caption || generated.facebook_caption || facebookCaption);
      groupShareKit = String(job.group_share_kit || generated.group_share_kit || groupShareKit);
      featuredImage = firstAttachment(job.featured_image, job.blog_image, featuredImage);
      facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage, facebookImage);
      socialPack = normalizeSocialPack(generated.social_pack || socialPack);
      selectedPages = resolveSelectedFacebookPages(job, settings);
      assertRecipeDistributionTargets(job, selectedPages);
      preferredAngle = resolvePreferredAngle(job);
      distribution = seedLegacyFacebookDistribution(
        normalizeFacebookDistribution(generated.facebook_distribution),
        selectedPages,
        job,
        facebookCaption,
      );
      log(`generated ${jobLabel} and continuing directly to publish`);
    }

    if (!generated.title || !generated.content_html) {
      ensureOpenAiConfigured(settings);
      generated = await generatePackage(job, settings);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
      socialPack = normalizeSocialPack(generated.social_pack);
      selectedPages = resolveSelectedFacebookPages(job, settings);
      assertRecipeDistributionTargets(job, selectedPages);
      preferredAngle = resolvePreferredAngle(job);
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

    socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, preferredAngle);
    const qualitySummary = buildQualitySummary(job, { ...generated, social_pack: socialPack }, settings, {
      selectedPages,
      featuredImage,
      facebookImage,
    });
    generated = mergeValidatorSummary(
      {
        ...generated,
        social_pack: socialPack,
      },
      {
        ...qualitySummary,
        target_pages: qualitySummary.quality_checks?.target_pages || selectedPages.length,
        social_variants: qualitySummary.quality_checks?.social_variants || socialPack.length,
      },
    );
    facebookCaption = generated.facebook_caption;
    assertQualityGate(qualitySummary);

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

    selectedPages = resolveSelectedFacebookPages(job, settings);
    assertRecipeDistributionTargets(job, selectedPages);
    assertFacebookConfigured(selectedPages);
    socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, preferredAngle);
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
    generated = {
      ...generated,
      social_pack: socialPack,
      facebook_distribution: distribution,
      facebook_urls: {
        ...(generated.facebook_urls || {}),
        facebook_comment: firstSuccess?.comment_url || "",
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
  const uploadedFirst = !manualOnly;

  if (manualOnly && (!featuredImage?.id || !facebookImage?.id)) {
    throw new Error("Manual-only image handling requires both a real uploaded blog image and a real uploaded Facebook image.");
  }

  if (uploadedFirst && (!featuredImage?.id || !facebookImage?.id)) {
    ensureOpenAiConfigured(settings);
  }

  if (!featuredImage?.id && uploadedFirst) {
    log(`generating blog hero image for job #${job.id}`);
    featuredImage = await generateAndUploadImage(job.id, settings, generated, {
      slot: "blog",
      filename: `${normalizeSlug(generated.slug || generated.title || job.topic)}-blog.png`,
      size: "1536x1024",
      variantHint: "Landscape hero image for the blog article header.",
    });
  }

  if (!facebookImage?.id && uploadedFirst) {
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
    const preferredAngle = resolvePreferredAngle(job);
    const socialPack = ensureSocialPackCoverage(packageResult.social_pack, selectedPages, packageResult, settings, preferredAngle);
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

function buildQualitySummary(job, generated, settings, options = {}) {
  const contentHtml = String(generated.content_html || "");
  const selectedPages = Array.isArray(options.selectedPages) ? options.selectedPages : resolveSelectedFacebookPages(job, settings);
  const socialPack = Array.isArray(generated.social_pack) ? generated.social_pack : [];
  const fingerprints = socialPack.map((variant) => normalizeSocialFingerprint(variant)).filter(Boolean);
  const uniqueFingerprints = new Set(fingerprints);
  const uniqueHookFingerprints = new Set(socialPack.map((variant) => normalizeHookFingerprint(variant)).filter(Boolean));
  const uniqueCaptionOpenings = new Set(socialPack.map((variant) => normalizeCaptionOpeningFingerprint(variant)).filter(Boolean));
  const uniqueAngleKeys = new Set(socialPack.map((variant) => normalizeAngleKey(variant?.angle_key || "")).filter(Boolean));
  const strongSocialVariants = socialPack.filter((variant) => !socialVariantLooksWeak(variant, generated.title || "")).length;
  const recipe = isPlainObject(generated.recipe) ? generated.recipe : {};
  const wordCount = cleanText(contentHtml.replace(/<[^>]+>/g, " ")).split(/\s+/).filter(Boolean).length;
  const minimumWords = Number(job.content_type === "recipe" ? 1200 : 1100);
  const h2Count = (contentHtml.match(/<h2\b/gi) || []).length;
  const internalLinks = countInternalLinks(contentHtml);
  const excerptWords = cleanText(generated.excerpt || "").split(/\s+/).filter(Boolean).length;
  const seoWords = cleanText(generated.seo_description || "").split(/\s+/).filter(Boolean).length;
  const recipeComplete = job.content_type !== "recipe" || (ensureStringArray(recipe.ingredients).length > 0 && ensureStringArray(recipe.instructions).length > 0);
  const featuredImageReady = Boolean(options.featuredImage?.id || job.featured_image?.id || job.blog_image?.id);
  const facebookImageReady = Boolean(options.facebookImage?.id || job.facebook_image_result?.id || job.facebook_image?.id || options.featuredImage?.id || job.featured_image?.id || job.blog_image?.id);
  const imageReady = settings.imageGenerationMode === "manual_only"
    ? featuredImageReady && facebookImageReady
    : featuredImageReady && facebookImageReady;
  const duplicateRisk = Boolean(options.duplicateRisk);

  const blockingChecks = [];
  const warningChecks = [];
  if (!cleanText(generated.title || "") || !cleanText(generated.slug || "") || !cleanText(contentHtml.replace(/<[^>]+>/g, " "))) {
    blockingChecks.push("missing_core_fields");
  }
  if (job.content_type === "recipe" && !recipeComplete) {
    blockingChecks.push("missing_recipe");
  }
  if (settings.imageGenerationMode === "manual_only" && (!featuredImageReady || !facebookImageReady)) {
    blockingChecks.push("missing_manual_images");
  }
  if (duplicateRisk) {
    blockingChecks.push("duplicate_conflict");
  }
  if (selectedPages.length < 1) {
    blockingChecks.push("missing_target_pages");
  }
  if (wordCount < minimumWords) {
    warningChecks.push("thin_content");
  }
  if (excerptWords < 12) {
    warningChecks.push("weak_excerpt");
  }
  if (seoWords < 12) {
    warningChecks.push("weak_seo");
  }
  if (h2Count < 2) {
    warningChecks.push("weak_structure");
  }
  if (internalLinks < 3) {
    warningChecks.push("missing_internal_links");
  }
  if (socialPack.length < Math.max(1, selectedPages.length)) {
    warningChecks.push("social_pack_incomplete");
  }
  if (socialPack.length > 0 && uniqueFingerprints.size < Math.min(socialPack.length, Math.max(1, selectedPages.length))) {
    warningChecks.push("social_pack_repetitive");
  }
  if (socialPack.length > 1 && uniqueHookFingerprints.size < Math.min(socialPack.length, Math.max(1, selectedPages.length))) {
    warningChecks.push("social_hooks_repetitive");
  }
  if (socialPack.length > 1 && uniqueCaptionOpenings.size < Math.min(socialPack.length, Math.max(1, selectedPages.length))) {
    warningChecks.push("social_openings_repetitive");
  }
  if (selectedPages.length > 1 && uniqueAngleKeys.size < Math.min(selectedPages.length, RECIPE_HOOK_ANGLES.length)) {
    warningChecks.push("social_angles_repetitive");
  }
  if (strongSocialVariants < Math.max(1, selectedPages.length)) {
    warningChecks.push("weak_social_copy");
  }
  if (!imageReady) {
    warningChecks.push("image_not_ready");
  }

  const penalties = {
    missing_core_fields: 35,
    missing_recipe: 25,
    missing_manual_images: 20,
    duplicate_conflict: 30,
    missing_target_pages: 25,
    thin_content: 15,
    weak_excerpt: 8,
    weak_seo: 8,
    weak_structure: 10,
    missing_internal_links: 9,
    social_pack_incomplete: 12,
    social_pack_repetitive: 10,
    social_hooks_repetitive: 8,
    social_openings_repetitive: 8,
    social_angles_repetitive: 8,
    weak_social_copy: 10,
    image_not_ready: 8,
  };
  let qualityScore = 100;
  for (const failedCheck of [...blockingChecks, ...warningChecks]) {
    qualityScore -= Number(penalties[failedCheck] || 0);
  }
  qualityScore = Math.max(0, qualityScore);
  const dedupedBlockingChecks = Array.from(new Set(blockingChecks));
  const dedupedWarningChecks = Array.from(new Set(warningChecks));
  const failedChecks = [...dedupedBlockingChecks, ...dedupedWarningChecks];
  const qualityStatus = dedupedBlockingChecks.length > 0
    ? "block"
    : ((dedupedWarningChecks.length > 0 || qualityScore < QUALITY_SCORE_THRESHOLD) ? "warn" : "pass");

  return {
    quality_score: qualityScore,
    quality_status: qualityStatus,
    blocking_checks: dedupedBlockingChecks,
    warning_checks: dedupedWarningChecks,
    failed_checks: failedChecks,
    quality_checks: {
      word_count: wordCount,
      minimum_words: minimumWords,
      h2_count: h2Count,
      internal_links: internalLinks,
      excerpt_words: excerptWords,
      seo_words: seoWords,
      recipe_complete: recipeComplete,
      image_ready: imageReady,
      target_pages: selectedPages.length,
      social_variants: socialPack.length,
      unique_social_variants: uniqueFingerprints.size,
      unique_social_hooks: uniqueHookFingerprints.size,
      unique_social_openings: uniqueCaptionOpenings.size,
      unique_social_angles: uniqueAngleKeys.size,
      strong_social_variants: strongSocialVariants,
      duplicate_risk: duplicateRisk,
    },
  };
}

function mergeValidatorSummary(generated, updates) {
  const contentMachine = isPlainObject(generated?.content_machine) ? generated.content_machine : {};
  const validatorSummary = isPlainObject(contentMachine.validator_summary) ? contentMachine.validator_summary : {};

  return {
    ...generated,
    content_machine: {
      ...contentMachine,
      validator_summary: {
        ...validatorSummary,
        ...updates,
      },
    },
  };
}

function assertQualityGate(summary) {
  const blockingChecks = Array.isArray(summary?.blocking_checks)
    ? summary.blocking_checks
    : (Array.isArray(summary?.failed_checks) ? summary.failed_checks : []);
  if (!blockingChecks.length && String(summary?.quality_status || "") !== "block") {
    return;
  }

  const messages = {
    missing_core_fields: "The generated package is missing a title, slug, or article body.",
    missing_recipe: "The generated recipe is missing ingredients or instructions.",
    missing_manual_images: "Manual-only mode requires both blog and Facebook images before publish.",
    duplicate_conflict: "A duplicate title or slug conflict blocked this recipe before publish.",
    missing_target_pages: "At least one Facebook page must stay attached before publish.",
    thin_content: "The generated article body was too thin for the quality gate.",
    weak_excerpt: "The generated excerpt was too thin for the quality gate.",
    weak_seo: "The generated SEO description was too thin for the quality gate.",
    weak_structure: "The generated article needs more H2 structure before publish.",
    missing_internal_links: "The generated article did not include enough internal links.",
    social_pack_incomplete: "The generated social pack did not cover all selected Facebook pages.",
    social_pack_repetitive: "The generated social pack was too repetitive across selected Facebook pages.",
    social_hooks_repetitive: "The generated Facebook hooks were too repetitive across selected Facebook pages.",
    social_openings_repetitive: "The generated Facebook caption openings were too repetitive across selected Facebook pages.",
    social_angles_repetitive: "The generated social pack reused too many of the same angle types across selected Facebook pages.",
    weak_social_copy: "The generated Facebook hooks or captions were too weak for publish.",
    image_not_ready: "The required image slots were not ready before publish.",
  };

  const primaryCheck = String(blockingChecks[0] || "quality_gate_failed");
  const primaryMessage = messages[primaryCheck] || "The generated recipe package did not meet the quality gate for publish.";
  throw new Error(`${primaryMessage} Quality score: ${Number(summary?.quality_score || 0)}.`);
}

async function generateRecipeMasterPackage(job, settings) {
  const models = settings.contentMachine.models || {};
  const repairAttemptsAllowed = models.repair_enabled ? Math.max(0, Number(models.repair_attempts || 0)) : 0;
  const selectedPages = resolveSelectedFacebookPages(job, settings);
  const preferredAngle = resolvePreferredAngle(job);
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    try {
      const content = await requestOpenAiChat(settings, [
        {
          role: "system",
          content:
            "You are the recipe publishing engine for a premium food publication. Return strict JSON only with no markdown fences. Put every required field at the top level of the JSON object; do not wrap them inside article, data, post, or content objects. The JSON must contain: title, slug, excerpt, seo_description, content_html, recipe, image_prompt, image_alt, and social_pack. content_html must be clean WordPress-ready HTML using paragraphs, h2 headings, lists, and blockquotes only, and it must never be empty. The recipe object must contain prep_time, cook_time, total_time, yield, ingredients, and instructions. social_pack must be an array of objects with angle_key, hook, caption, and cta_hint. Never include the article URL, hashtags, or markdown inside captions.",
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
      const socialPack = ensureSocialPackCoverage(validated.social_pack, selectedPages, validated, settings, preferredAngle);

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
    articlePrompt: contentMachine.channelPresets.article.guidance || raw.article_prompt || "Open with appetite and payoff, use useful H2 sections, and keep the recipe practical, cookable, and worth the click.",
    defaultCta: raw.default_cta || contentMachine.defaultCta || "Read the full recipe on the blog.",
    imageStyle: contentMachine.channelPresets.image.guidance || raw.image_style || "Realistic, appetizing food photography with natural light, clean plating, believable texture, and no text overlays.",
    imageGenerationMode: raw.image_generation_mode || "uploaded_first_generate_missing",
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
        "You are writing distribution copy for a premium food publication. Return strict JSON only with no markdown fences. The JSON must contain: facebook_caption, image_prompt, and image_alt. Never include links in facebook_caption.",
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
    image_prompt: cleanMultilineText(parsed.image_prompt || parsed.imagePrompt),
    image_alt: cleanText(parsed.image_alt || parsed.imageAlt),
  };
}

async function requestOpenAiChat(settings, messages) {
  let payload;

  try {
    payload = await openAiJsonRequest(settings, "/chat/completions", {
      model: settings.openaiModel,
      messages,
      response_format: { type: "json_object" },
    });
  } catch (error) {
    const message = formatError(error);
    if (!/response_format|json_object|unsupported/i.test(message)) {
      throw error;
    }

    payload = await openAiJsonRequest(settings, "/chat/completions", {
      model: settings.openaiModel,
      messages,
    });
  }

  let content = extractOpenAiMessageText(payload?.choices?.[0]?.message);
  if (!isDegenerateModelJsonText(content)) {
    return content;
  }

  const fallbackMessages = [
    ...messages,
    {
      role: "system",
      content:
        "Your previous reply was invalid for this task. Reply with one JSON object only. Do not return an array, do not return [], do not return markdown, and do not wrap the object in quotes.",
    },
  ];

  const fallbackPayload = await openAiJsonRequest(settings, "/chat/completions", {
    model: settings.openaiModel,
    messages: fallbackMessages,
  });

  content = extractOpenAiMessageText(fallbackPayload?.choices?.[0]?.message);
  return content;
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
      guardrails: cleanMultilineText(
        publicationProfile.guardrails ||
          raw.global_guardrails ||
          [publicationProfile.do_guidance, publicationProfile.dont_guidance, publicationProfile.banned_claims]
            .filter(Boolean)
            .join("\n") ||
          "No fake personal stories. No filler SEO intros. No spammy clickbait. No medical or nutrition claims beyond ordinary kitchen guidance. Keep paragraphs short, specific, and human. Avoid generic openers like 'when it comes to' or 'in today's busy world.'",
      ),
    },
    contentPresets: {
      recipe: normalizePreset(contentPresets.recipe, "Create dependable, craveable, and realistic home-cooking recipes with believable timings, coherent ingredient amounts, repeatable results, and enough practical detail to justify the click.", 1200),
      food_fact: normalizePreset(contentPresets.food_fact, "Write a fact-led article that answers the question directly, corrects confusion, and gives a practical takeaway.", 1100),
      food_story: normalizePreset(contentPresets.food_story, "Write a publication-voice kitchen essay with a clear observation and a reflective close.", 1100),
    },
    channelPresets: {
      recipe_master: {
        guidance: cleanMultilineText(
          channelPresets.recipe_master?.guidance ||
            raw.recipe_master_prompt ||
            "Turn the dish name into one complete premium recipe package with a strong title, excerpt, SEO description, useful article body, structured recipe card, image direction, and one unique Facebook variant per selected page."
        ),
      },
      article: {
        guidance: cleanMultilineText(channelPresets.article?.guidance || raw.article_prompt || "Open with appetite and payoff, use useful H2 sections, and keep the recipe practical, cookable, and worth the click."),
      },
      facebook_caption: {
        guidance: cleanMultilineText(channelPresets.facebook_caption?.guidance || "Generate one distinct Facebook variant per selected page with a short hook, 2 to 5 short caption lines, one distinct angle per page, no repeated hook-as-caption opener, no link, and no hashtags."),
      },
      image: {
        guidance: cleanMultilineText(channelPresets.image?.guidance || raw.image_style || "Realistic, appetizing food photography with natural light, clean plating, believable texture, and no text overlays."),
      },
    },
    cadence: {
      mode: String(cadence.mode || "manual_recipe_publish_at"),
      timezone: String(cadence.timezone || "UTC"),
    },
    models: {
      text_model: String(models.text_model || raw.openai_model || config.fallbackTextModel),
      image_model: String(models.image_model || raw.openai_image_model || "gpt-image-1.5"),
      repair_enabled: Boolean(models.repair_enabled ?? true),
      repair_attempts: Math.max(0, Number(models.repair_attempts ?? 1)),
    },
    defaultCta: cleanText(provided.default_cta || raw.default_cta || "Read the full recipe on the blog."),
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

function normalizeAngleKey(value) {
  const key = cleanText(value || "").replace(/\s+/g, "_").toLowerCase();
  return RECIPE_HOOK_ANGLES.some((angle) => angle.key === key) ? key : "";
}

function resolvePreferredAngle(job) {
  return normalizeAngleKey(job?.request_payload?.preferred_angle || "");
}

function isFutureUtcTimestamp(value) {
  const normalized = cleanText(value || "");
  if (!normalized) {
    return false;
  }

  const timestamp = Date.parse(normalized.replace(" ", "T") + "Z");
  if (Number.isNaN(timestamp)) {
    return false;
  }

  return timestamp > Date.now();
}

function buildAngleSequence(count, preferredAngle = "") {
  const normalizedPreferred = normalizeAngleKey(preferredAngle);
  const keys = RECIPE_HOOK_ANGLES.map((angle) => angle.key);
  const ordered = normalizedPreferred
    ? [normalizedPreferred, ...keys.filter((key) => key !== normalizedPreferred)]
    : [...keys];

  return Array.from({ length: Math.max(1, count) }, (_, index) => ordered[index % ordered.length]);
}

function buildPageAnglePlan(pages, preferredAngle = "") {
  const count = Math.max(1, Array.isArray(pages) ? pages.length : 0);
  const angles = buildAngleSequence(count, preferredAngle);

  return Array.from({ length: count }, (_, index) => {
    const angleKey = angles[index] || RECIPE_HOOK_ANGLES[index % RECIPE_HOOK_ANGLES.length].key;
    const angle = angleDefinition(angleKey);
    const page = Array.isArray(pages) ? pages[index] || null : null;

    return {
      index,
      angle_key: angleKey,
      page_label: cleanText(page?.label || `Page ${index + 1}`),
      instruction: angle?.instruction || "",
    };
  });
}

function angleDefinition(angleKey) {
  const normalized = normalizeAngleKey(angleKey);
  return RECIPE_HOOK_ANGLES.find((angle) => angle.key === normalized) || null;
}

function normalizeSocialFingerprint(variant) {
  return buildFacebookPostMessage(variant, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, " ")
    .trim();
}

function normalizeSocialLineFingerprint(value) {
  return normalizeSlug(cleanText(value || ""));
}

function normalizeHookFingerprint(variant) {
  return normalizeSocialLineFingerprint(variant?.hook || "");
}

function normalizeCaptionOpeningFingerprint(variant) {
  const firstLine = cleanMultilineText(variant?.caption || "")
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find(Boolean);

  return normalizeSocialLineFingerprint(firstLine || "");
}

function countWords(value) {
  return cleanText(value).split(/\s+/).filter(Boolean).length;
}

function countLines(value) {
  return cleanMultilineText(value)
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean).length;
}

function socialVariantLooksWeak(variant, articleTitle = "") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const hookWords = countWords(hook);
  const captionWords = countWords(caption);
  const captionLines = countLines(caption);
  const normalizedHook = normalizeSlug(hook);
  const normalizedTitle = normalizeSlug(articleTitle);

  return (
    !hook ||
    hookWords < 4 ||
    hookWords > 18 ||
    !caption ||
    captionWords < 14 ||
    captionWords > 85 ||
    captionLines < 2 ||
    captionLines > 5 ||
    (normalizedTitle !== "" && normalizedHook === normalizedTitle) ||
    /(https?:\/\/|www\.)/i.test(caption) ||
    /(^|\s)#[a-z0-9_]+/i.test(caption)
  );
}

function stripHookEchoFromCaption(hook, caption) {
  const cleanHook = cleanText(hook || "");
  const lines = cleanMultilineText(caption || "")
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  if (!cleanHook || lines.length < 2) {
    return lines.join("\n");
  }

  const normalizedHook = normalizeSocialLineFingerprint(cleanHook);
  const firstLine = lines[0] || "";
  if (normalizedHook !== "" && normalizeSocialLineFingerprint(firstLine) === normalizedHook) {
    return lines.slice(1).join("\n");
  }

  return lines.join("\n");
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
    `Global guardrails: ${profile.guardrails || "No fake personal stories, no filler SEO intros, no spammy clickbait, no generic opening filler, and no unsupported health or nutrition claims."}`,
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
  const preferredAngle = resolvePreferredAngle(job);
  const angleSequence = buildAngleSequence(socialPackCount, preferredAngle);
  const pageAnglePlan = buildPageAnglePlan(selectedPages, preferredAngle);
  const blogImageProvided = Boolean(job?.blog_image?.id || job?.blog_image_id || job?.request_payload?.blog_image?.id || job?.request_payload?.blog_image_id);
  const facebookImageProvided = Boolean(job?.facebook_image?.id || job?.facebook_image_id || job?.request_payload?.facebook_image?.id || job?.request_payload?.facebook_image_id);
  let imageAssetPlan = "No uploaded images are attached yet. Return one strong reusable image_prompt and one clean image_alt so missing blog or Facebook assets can be generated when needed.";
  if (blogImageProvided && facebookImageProvided) {
    imageAssetPlan = "Both uploaded images are already attached. Still return one concise image_prompt and one strong image_alt, but prioritize article and Facebook-copy quality over long visual explanation.";
  } else if (blogImageProvided) {
    imageAssetPlan = "A real blog hero image is already attached. Return one concise image_prompt and one strong image_alt that can guide only the missing Facebook asset if needed.";
  } else if (facebookImageProvided) {
    imageAssetPlan = "A real Facebook image is already attached. Return one concise image_prompt and one strong image_alt that can guide only the missing blog hero asset if needed.";
  }
  const internalLinkLibrary = internalLinkTargetsForJob({ ...job, content_type: "recipe" })
    .map((item) => `- ${item.label}: [kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
    .join("\n");
  const masterPrompt =
    settings.contentMachine.channelPresets.recipe_master?.guidance ||
    "Turn the dish name into one complete premium recipe package with a strong title, excerpt, SEO description, useful article body, structured recipe card, image direction, and one unique Facebook variant per selected page.";

  return [
    `Publication profile: ${profile.name || settings.siteName}`,
    `Dish name: ${job.topic}`,
    `Voice brief: ${profile.voice_brief || settings.brandVoice}`,
    `Global guardrails: ${profile.guardrails || "No fake personal stories, no filler SEO intros, no spammy clickbait, no generic opening filler, and no unsupported health or nutrition claims."}`,
    `Recipe master direction: ${masterPrompt}`,
    `Recipe preset guidance: ${preset.guidance || ""}`,
    `Recipe article guidance: ${settings.contentMachine.channelPresets.article.guidance}`,
    `Facebook variant guidance: ${settings.contentMachine.channelPresets.facebook_caption.guidance}`,
    `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
    job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate the best article title yourself.",
    `Create exactly ${socialPackCount} social variants in social_pack.`,
    selectedLabels.length ? `Target Facebook pages: ${selectedLabels.join(" | ")}` : "Target Facebook pages: Primary Facebook page",
    preferredAngle ? `Use ${preferredAngle} as the first social angle, then rotate the remaining angles distinctly.` : "Auto-rotate distinct Facebook angles across the selected pages.",
    `Image asset status: ${imageAssetPlan}`,
    "Social angle library:",
    ...RECIPE_HOOK_ANGLES.map((angle) => `- ${angle.key}: ${angle.instruction}`),
    `Use these angle keys in order when possible: ${angleSequence.join(", ")}`,
    "Variant-to-page assignment:",
    ...pageAnglePlan.map((item) => `- social_pack[${item.index}] -> ${item.page_label} -> ${item.angle_key}: ${item.instruction}`),
    "Recipe output rules:",
    "- Write an original, practical, highly clickable recipe article with real kitchen value after the click.",
    "- Never return [] or an empty array. Return one JSON object matching the contract even if the recipe is simple.",
    "- content_html must open with 1 to 2 short appetite-led paragraphs before the first H2.",
    "- Keep the JSON flat: do not wrap article fields in nested article, post, data, or content objects.",
    "- Use H2 sections that help scanning and conversion: why this recipe works, ingredient notes, how to make it, and serving or storage tips.",
    "- Keep paragraphs short, specific, and concrete. Avoid generic filler, fake memoir, or restaurant-style exaggeration.",
    "- Do not paste the full ingredient list and full numbered method into the article body; keep those for the recipe object.",
    "- The recipe object must include prep_time, cook_time, total_time, yield, ingredients[], and instructions[]. Use believable times, exact amounts, and sequential method steps.",
    "- The title must be human, craveable, and high-intent without spam phrases like 'best ever' or 'you won't believe'.",
    "- excerpt should feel distinct, useful, and click-worthy, not generic.",
    "- seo_description should stay under 155 characters while still sounding natural.",
    "- Include at least three internal Kuchnia Twist links inside content_html.",
    "- social_pack captions must stay short, hook-led, and must not include the article URL.",
    "- Every social_pack item must include angle_key, hook, caption, and cta_hint.",
    "- Each hook should be one short line, ideally 4 to 18 words, and should not simply repeat the title.",
    "- Each caption should be 2 to 5 short lines that expand the hook with taste, texture, ease, payoff, or usefulness.",
    "- Do not repeat the hook as the first caption line.",
    "- End captions with a light CTA such as save this, make it tonight, or tell me who would want it.",
    "- The social_pack array order must match the Variant-to-page assignment list exactly.",
    "- Each social_pack item must feel different in angle so multiple pages are not posting clones.",
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
    '  "recipe": {',
    '    "prep_time": "string",',
    '    "cook_time": "string",',
    '    "total_time": "string",',
    '    "yield": "string",',
    '    "ingredients": ["string"],',
    '    "instructions": ["string"]',
    "  },",
    '  "social_pack": [{"angle_key":"string","hook":"string","caption":"string","cta_hint":"string"}]',
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
    `Global guardrails: ${profile.guardrails || "No fake personal stories, no filler SEO intros, no spammy clickbait, no generic opening filler, and no unsupported health or nutrition claims."}`,
    `Preset guidance: ${preset.guidance || ""}`,
    `Facebook caption guidance: ${settings.contentMachine.channelPresets.facebook_caption.guidance}`,
    `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
    `Article title: ${article.title}`,
    `Excerpt: ${article.excerpt}`,
    headings.length ? `H2 sections: ${headings.join(" | ")}` : "",
    `Article summary: ${articleSummary}`,
    "Return only these JSON keys: facebook_caption, image_prompt, image_alt.",
    "facebook_caption must stay short, hook-led, and never include a link.",
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
      const angleKey = normalizeAngleKey(item.angle_key || item.angleKey || "");

      if (!hook && !caption) {
        return null;
      }

      const normalized = {
        id: cleanText(item.id || `variant-${index + 1}`),
        angle_key: angleKey,
        hook,
        caption,
        cta_hint: ctaHint,
        post_message: cleanMultilineText(item.post_message || item.postMessage || ""),
      };

      normalized.post_message = normalized.post_message || buildFacebookPostMessage(normalized, "");

      return normalized;
    })
    .filter(Boolean);
}

function buildFallbackSocialPack(article, pages, settings, preferredAngle = "") {
  const count = Math.max(1, pages.length || 1);
  const angles = buildAngleSequence(count, preferredAngle);
  const closers = {
    quick_dinner: [
      "Would you make this tonight?",
      "Save this for a busy evening.",
      "This one is built for the weeknight rotation.",
    ],
    comfort_food: [
      "Save this for a comfort-food night.",
      "This is the kind of dinner you come back to.",
      "Would this hit the spot for you tonight?",
    ],
    budget_friendly: [
      "Would you try it for a family meal?",
      "This is a strong one to keep in the low-stress rotation.",
      "Save this for a practical dinner that still feels good.",
    ],
    beginner_friendly: [
      "Would you cook this as a starter dinner?",
      "This is a good recipe to build confidence with.",
      "Save this if you want an easy kitchen win.",
    ],
    crowd_pleaser: [
      "Who would you make this for?",
      "This one is built for repeat requests.",
      "Save this for the next easy family dinner.",
    ],
    better_than_takeout: [
      "Would you skip takeout for this?",
      "This is the kind of fakeout takeaway people repeat.",
      "Save this for the night you want the payoff without delivery.",
    ],
  };
  const templates = {
    quick_dinner: (title, index) => ({
      hook: `${title} is the dinner that saves a busy night.`,
      caption: `Fast enough for a real weeknight.\nBig payoff, clear steps, and no unnecessary drag.\n${closers.quick_dinner[index % closers.quick_dinner.length]}`,
    }),
    comfort_food: (title, index) => ({
      hook: `${title} is comfort food with real payoff.`,
      caption: `Cozy, rich, and built for the kind of dinner you actually want.\nIt feels indulgent without making the method harder.\n${closers.comfort_food[index % closers.comfort_food.length]}`,
    }),
    budget_friendly: (title, index) => ({
      hook: `${title} keeps dinner practical without feeling basic.`,
      caption: `Simple ingredients, strong flavor, and no unnecessary extras.\nThis one feels generous without making the grocery list harder.\n${closers.budget_friendly[index % closers.budget_friendly.length]}`,
    }),
    beginner_friendly: (title, index) => ({
      hook: `${title} is an easy win for a home cook.`,
      caption: `Approachable steps, clear detail, and a result that still feels impressive.\nThis is the kind of recipe that builds confidence fast.\n${closers.beginner_friendly[index % closers.beginner_friendly.length]}`,
    }),
    crowd_pleaser: (title, index) => ({
      hook: `${title} is the kind of dinner people ask for again.`,
      caption: `Easy to serve, easy to repeat, and hard to complain about.\nIt works when you want a meal that lands with everyone.\n${closers.crowd_pleaser[index % closers.crowd_pleaser.length]}`,
    }),
    better_than_takeout: (title, index) => ({
      hook: `${title} is how you skip takeout and still win.`,
      caption: `Big payoff, better control, and a home-kitchen method that actually works.\nIt gives you the restaurant-style hit without the delivery wait.\n${closers.better_than_takeout[index % closers.better_than_takeout.length]}`,
    }),
  };

  return Array.from({ length: count }, (_, index) => {
    const page = pages[index] || null;
    const angleKey = angles[index] || RECIPE_HOOK_ANGLES[index % RECIPE_HOOK_ANGLES.length].key;
    const variant = (templates[angleKey] || templates.quick_dinner)(article.title, index);
    const angleLabel = angleDefinition(angleKey)?.label || angleKey.replace(/_/g, " ");
    return {
      id: `variant-${index + 1}`,
      angle_key: angleKey,
      hook: variant.hook,
      caption: variant.caption,
      cta_hint: page?.label ? `${angleLabel} angle on ${page.label}` : "General recipe post",
      post_message: buildFacebookPostMessage(variant, ""),
    };
  });
}

function ensureSocialPackCoverage(value, pages, article, settings, preferredAngle = "") {
  const desiredCount = Math.max(1, Array.isArray(pages) ? pages.length : 0);
  const normalized = normalizeSocialPack(value);
  const fallback = buildFallbackSocialPack(article, pages, settings, preferredAngle);
  const angleSequence = buildAngleSequence(desiredCount, preferredAngle);
  const usedFingerprints = new Set();
  const usedHookFingerprints = new Set();
  const usedCaptionOpenings = new Set();

  return Array.from({ length: desiredCount }, (_, index) => {
    const base = normalized[index] || fallback[index] || fallback[fallback.length - 1];
    let variant = {
      ...base,
      angle_key: angleSequence[index],
      cta_hint: cleanText(base?.cta_hint || (pages[index]?.label ? `Use on ${pages[index]?.label}` : "")),
      post_message: cleanMultilineText(base?.post_message || base?.postMessage || ""),
    };
    variant.post_message = variant.post_message || buildFacebookPostMessage(variant, "");

    const fingerprint = normalizeSocialFingerprint(variant);
    const hookFingerprint = normalizeHookFingerprint(variant);
    const captionOpeningFingerprint = normalizeCaptionOpeningFingerprint(variant);
    if (
      !fingerprint ||
      usedFingerprints.has(fingerprint) ||
      (hookFingerprint && usedHookFingerprints.has(hookFingerprint)) ||
      (captionOpeningFingerprint && usedCaptionOpenings.has(captionOpeningFingerprint)) ||
      socialVariantLooksWeak(variant, article.title || "")
    ) {
      variant = {
        ...(fallback[index] || fallback[fallback.length - 1]),
        angle_key: angleSequence[index],
      };
      variant.post_message = cleanMultilineText(variant.post_message || "") || buildFacebookPostMessage(variant, "");
    }

    usedFingerprints.add(normalizeSocialFingerprint(variant));
    if (normalizeHookFingerprint(variant)) {
      usedHookFingerprints.add(normalizeHookFingerprint(variant));
    }
    if (normalizeCaptionOpeningFingerprint(variant)) {
      usedCaptionOpenings.add(normalizeCaptionOpeningFingerprint(variant));
    }
    return variant;
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
          (() => {
            const normalizedPage = {
              page_id: cleanText(page.page_id || pageId),
              label: cleanText(page.label || ""),
              angle_key: normalizeAngleKey(page.angle_key || page.angleKey || ""),
              hook: cleanText(page.hook || ""),
              caption: cleanMultilineText(page.caption || ""),
              cta_hint: cleanText(page.cta_hint || page.ctaHint || ""),
              post_message: cleanMultilineText(page.post_message || page.postMessage || ""),
              post_id: cleanText(page.post_id || page.postId || ""),
              post_url: cleanText(page.post_url || page.postUrl || ""),
              comment_message: cleanMultilineText(page.comment_message || page.commentMessage || ""),
              comment_id: cleanText(page.comment_id || page.commentId || ""),
              comment_url: cleanText(page.comment_url || page.commentUrl || ""),
              status: cleanText(page.status || ""),
              error: cleanText(page.error || ""),
            };
            normalizedPage.post_message = normalizedPage.post_message || buildFacebookPostMessage(normalizedPage, "");
            return normalizedPage;
          })(),
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
    angle_key: "",
    hook: "",
    caption: cleanMultilineText(facebookCaption || ""),
    cta_hint: "",
    post_message: buildFacebookPostMessage({ hook: "", caption: cleanMultilineText(facebookCaption || "") }, ""),
    post_id: legacyPostId,
    post_url: buildFacebookPostUrl(legacyPostId),
    comment_message: "",
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
  const blockedPhrases = [
    "as an ai",
    "generated by ai",
    "lorem ipsum",
  ];

  for (const phrase of blockedPhrases) {
    if (opening.includes(phrase)) {
      throw new Error(`The generated article used a blocked generic opening phrase: ${phrase}`);
    }
  }

  if (job.content_type === "food_story" && /\b(i|my|me|mine)\b/i.test(text)) {
    throw new Error("Food story output used first-person voice, which is blocked for the publication-voice essay format.");
  }

  return generated;
}

function normalizeGeneratedPayload(raw, job) {
  const source = coerceGeneratedPayload(raw);
  const titleOverride = cleanText(job?.title_override || "");
  const title =
    titleOverride ||
    cleanText(readGeneratedString(source, ["title", "headline", "post_title", "postTitle", "name"])) ||
    cleanText(job?.topic) ||
    "Fresh from Kuchnia Twist";
  const slug = normalizeSlug(readGeneratedString(source, ["slug", "post_slug", "postSlug"]) || title);
  const contentHtml = ensureInternalLinks(resolveGeneratedContentHtml(source, job), job);
  const sourceContentText = cleanText(String(contentHtml || "").replace(/<[^>]+>/g, " "));
  const fallbackExcerpt = trimText(sourceContentText.split(/(?<=[.!?])\s+/)[0] || sourceContentText, 220);
  const excerpt =
    trimText(cleanText(readGeneratedString(source, ["excerpt", "summary", "dek", "standfirst", "description"])), 220) ||
    fallbackExcerpt ||
    `${title} on Kuchnia Twist.`;
  const seoDescription =
    trimText(cleanText(readGeneratedString(source, ["seo_description", "seoDescription", "meta_description", "metaDescription", "search_description", "searchDescription"])), 155) ||
    trimText(excerpt, 155);
  const facebookCaption =
    cleanMultilineText(readGeneratedString(source, ["facebook_caption", "facebookCaption"])) ||
    buildFallbackFacebookCaption({ title, excerpt }, "Read the full recipe on the blog.");
  const groupShareKit = cleanMultilineText(readGeneratedString(source, ["group_share_kit", "groupShareKit"]));
  const imagePrompt =
    cleanMultilineText(readGeneratedString(source, ["image_prompt", "imagePrompt", "hero_image_prompt", "heroImagePrompt"])) ||
    `Editorial food photography of ${title}, premium magazine lighting, appetizing detail, natural styling, no text overlay.`;
  const imageAlt = cleanText(readGeneratedString(source, ["image_alt", "imageAlt", "hero_image_alt", "heroImageAlt", "alt_text", "altText"])) || title;
  const recipe = normalizeRecipe(readGeneratedObject(source, ["recipe", "recipe_card", "recipeCard"]) || {}, job?.content_type || "recipe");

  if (!contentHtml) {
    throw new Error(
      `The generated article body was empty. Parsed type: ${describeGeneratedType(raw)}. Parsed keys: ${describeGeneratedShape(source)}. Raw preview: ${previewGeneratedValue(raw)}.`,
    );
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
    social_pack: normalizeSocialPack(readGeneratedArray(source, ["social_pack", "socialPack", "facebook_variants", "facebookVariants"])),
    facebook_distribution: normalizeFacebookDistribution(readGeneratedObject(source, ["facebook_distribution", "facebookDistribution"]) || {}),
    assets: readGeneratedObject(source, ["assets"]) || {},
    facebook_urls: readGeneratedObject(source, ["facebook_urls", "facebookUrls"]) || {},
    content_machine: readGeneratedObject(source, ["content_machine", "contentMachine"]) || {},
  };
}

function coerceGeneratedPayload(value, depth = 0) {
  if (depth > 4) {
    return {};
  }

  if (isPlainObject(value)) {
    return value;
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if (!trimmed) {
      return {};
    }

    if ((trimmed.startsWith("{") && trimmed.endsWith("}")) || (trimmed.startsWith("[") && trimmed.endsWith("]"))) {
      try {
        return coerceGeneratedPayload(JSON.parse(trimmed), depth + 1);
      } catch {
        return {};
      }
    }

    return {};
  }

  if (Array.isArray(value)) {
    if (value.length === 1) {
      return coerceGeneratedPayload(value[0], depth + 1);
    }

    const firstObject = value.find((item) => isPlainObject(item) || typeof item === "string" || Array.isArray(item));
    return firstObject ? coerceGeneratedPayload(firstObject, depth + 1) : {};
  }

  return {};
}

function generatedPayloadContainers(source) {
  const containers = [];
  const queue = [source];
  const seen = new Set();
  const nestedKeys = ["article", "post", "content", "data", "result", "output", "payload", "article_package", "recipe_package", "blog_post", "blogPost"];

  while (queue.length && containers.length < 16) {
    const current = queue.shift();
    if (!isPlainObject(current) || seen.has(current)) {
      continue;
    }

    seen.add(current);
    containers.push(current);

    for (const key of nestedKeys) {
      if (isPlainObject(current[key])) {
        queue.push(current[key]);
      }
    }
  }

  return containers;
}

function readGeneratedString(source, keys) {
  for (const container of generatedPayloadContainers(source)) {
    for (const key of keys) {
      const value = container[key];
      if (typeof value === "string" && cleanText(value)) {
        return value;
      }

      if (Array.isArray(value)) {
        const joined = cleanMultilineText(
          value
            .map((item) => {
              if (typeof item === "string") {
                return item;
              }

              if (isPlainObject(item)) {
                return cleanMultilineText(item.html || item.text || item.content || item.body || item.value || "");
              }

              return "";
            })
            .filter(Boolean)
            .join("\n\n"),
        );

        if (joined) {
          return joined;
        }
      }
    }
  }

  return "";
}

function readGeneratedObject(source, keys) {
  for (const container of generatedPayloadContainers(source)) {
    for (const key of keys) {
      if (isPlainObject(container[key])) {
        return container[key];
      }
    }
  }

  return null;
}

function readGeneratedArray(source, keys) {
  for (const container of generatedPayloadContainers(source)) {
    for (const key of keys) {
      if (Array.isArray(container[key])) {
        return container[key];
      }
    }
  }

  return [];
}

function buildContentHtmlFromSections(source) {
  const sectionSets = readGeneratedArray(source, ["sections", "article_sections", "articleSections", "content_sections", "contentSections", "body_sections", "bodySections"]);
  if (!sectionSets.length) {
    return "";
  }

  return sectionSets
    .map((section) => {
      if (typeof section === "string") {
        return `<p>${escapeHtml(cleanText(section))}</p>`;
      }

      if (!isPlainObject(section)) {
        return "";
      }

      const heading = cleanText(section.heading || section.title || section.label);
      const body = cleanMultilineText(section.html || section.content_html || section.content || section.body || section.text || "");
      const bodyHtml = normalizeHtml(body);
      if (!heading && !bodyHtml) {
        return "";
      }

      return [heading ? `<h2>${escapeHtml(heading)}</h2>` : "", bodyHtml].filter(Boolean).join("\n");
    })
    .filter(Boolean)
    .join("\n");
}

function resolveGeneratedContentHtml(source, job) {
  const direct = readGeneratedString(source, [
    "content_html",
    "contentHtml",
    "article_html",
    "articleHtml",
    "body_html",
    "bodyHtml",
    "blog_html",
    "blogHtml",
    "article_body",
    "articleBody",
    "html",
    "body",
  ]);

  if (direct) {
    return normalizeHtml(direct);
  }

  const sectionHtml = buildContentHtmlFromSections(source);
  if (sectionHtml) {
    return sectionHtml;
  }

  const plaintext = readGeneratedString(source, ["content", "article", "post"]);
  if (plaintext && !isPlainObject(plaintext)) {
    return normalizeHtml(plaintext);
  }

  return "";
}

function describeGeneratedShape(source) {
  const keys = new Set();

  for (const container of generatedPayloadContainers(source)) {
    Object.keys(container).forEach((key) => keys.add(key));
  }

  return Array.from(keys).sort().join(", ") || "none";
}

function describeGeneratedType(value) {
  if (Array.isArray(value)) {
    return `array(${value.length})`;
  }

  if (value === null) {
    return "null";
  }

  return typeof value;
}

function previewGeneratedValue(value) {
  if (isPlainObject(value)) {
    return trimText(JSON.stringify(value).replace(/\s+/g, " "), 220) || "empty-object";
  }

  if (Array.isArray(value)) {
    return trimText(JSON.stringify(value).replace(/\s+/g, " "), 220) || "empty-array";
  }

  return trimText(String(value || "").replace(/\s+/g, " "), 220) || "empty";
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
    "Keep the image realistic, editorial, craveable, and free of any text or logos.",
    "Show the finished dish clearly with natural light, believable texture, and clean plating.",
  ].join("\n");
}

function buildFacebookComment(defaultCta, trackedUrl) {
  const cta = cleanText(defaultCta) || "Read the full recipe on the blog.";
  return cleanMultilineText(`${cta} ${trackedUrl}`.trim());
}

function buildFacebookPostMessage(variant, fallbackCaption) {
  const hook = cleanText(variant?.hook || "");
  const caption = stripHookEchoFromCaption(hook, variant?.caption || fallbackCaption || "");

  if (hook && caption) {
    return `${hook}\n\n${caption}`.trim();
  }

  return hook || caption;
}

function buildFallbackFacebookCaption(generated, defaultCta) {
  return [
    cleanText(generated.title),
    cleanText(generated.excerpt),
    cleanText(defaultCta) || "Read the full recipe on the blog.",
  ]
    .filter(Boolean)
    .join("\n\n")
    .trim();
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
    const commentMessage = buildFacebookComment(settings.defaultCta, commentUrl);
    const pageState = {
      ...existing,
      page_id: page.page_id,
      label: pageLabel,
      angle_key: normalizeAngleKey(variant.angle_key || existing.angle_key || existing.angleKey || ""),
      hook: cleanText(variant.hook || existing.hook || ""),
      caption: cleanMultilineText(variant.caption || existing.caption || ""),
      cta_hint: cleanText(variant.cta_hint || existing.cta_hint || ""),
      post_message: cleanMultilineText(existing.post_message || existing.postMessage || message) || message,
      post_url: cleanText(existing.post_url || (existing.post_id ? buildFacebookPostUrl(existing.post_id) : "")),
      comment_message: cleanMultilineText(existing.comment_message || existing.commentMessage || commentMessage) || commentMessage,
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
          pageState.comment_message,
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

function assertRecipeDistributionTargets(job, pages) {
  if (Array.isArray(pages) && pages.length > 0) {
    return;
  }

  throw new Error(
    `Recipe job #${toInt(job?.id)} no longer has any active Facebook pages attached. Reopen it in wp-admin, choose at least one page, and try again.`,
  );
}

function parseJsonObject(text) {
  try {
    return coerceParsedJsonValue(JSON.parse(text));
  } catch {
    const firstBrace = text.indexOf("{");
    const lastBrace = text.lastIndexOf("}");
    if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
      throw new Error("The model response was not valid JSON.");
    }
    return coerceParsedJsonValue(JSON.parse(text.slice(firstBrace, lastBrace + 1)));
  }
}

function coerceParsedJsonValue(value, depth = 0) {
  if (depth > 4) {
    return value;
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if ((trimmed.startsWith("{") && trimmed.endsWith("}")) || (trimmed.startsWith("[") && trimmed.endsWith("]"))) {
      try {
        return coerceParsedJsonValue(JSON.parse(trimmed), depth + 1);
      } catch {
        return value;
      }
    }
  }

  if (Array.isArray(value) && value.length === 1) {
    return coerceParsedJsonValue(value[0], depth + 1);
  }

  return value;
}

function isDegenerateModelJsonText(value) {
  const normalized = cleanMultilineText(value);
  if (!normalized) {
    return true;
  }

  return ["[]", "[ ]", "null", "\"[]\"", "'[]'"].includes(normalized);
}

function extractOpenAiMessageText(message) {
  if (typeof message?.content === "string") {
    return message.content;
  }

  if (Array.isArray(message?.content)) {
    const joined = cleanMultilineText(
      message.content
        .map((part) => {
          if (typeof part === "string") {
            return part;
          }

          if (!isPlainObject(part)) {
            return "";
          }

          if (typeof part.text === "string") {
            return part.text;
          }

          if (isPlainObject(part.text) && typeof part.text.value === "string") {
            return part.text.value;
          }

          return cleanMultilineText(part.content || part.value || part.output_text || "");
        })
        .filter(Boolean)
        .join("\n"),
    );

    if (joined) {
      return joined;
    }
  }

  return cleanMultilineText(message?.text || message?.output_text || "");
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
