import { Buffer } from "node:buffer";
import { setTimeout as sleep } from "node:timers/promises";

const SECOND_MS = 1000;
const IDLE_MS = 60 * 60 * SECOND_MS;
const WORKER_VERSION = "1.8.9";
const PROMPT_VERSION = "typed-content-v10";
const CONTENT_PACKAGE_CONTRACT_VERSION = "content-package-v1";
const CHANNEL_ADAPTER_CONTRACT_VERSION = "channel-adapters-v1";
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
const FOOD_FACT_HOOK_ANGLES = [
  {
    key: "myth_busting",
    label: "Myth Busting",
    instruction: "Lead with a correction to something many cooks casually believe.",
  },
  {
    key: "surprising_truth",
    label: "Surprising Truth",
    instruction: "Frame the post around a specific surprise that changes how the reader sees the topic.",
  },
  {
    key: "kitchen_mistake",
    label: "Kitchen Mistake",
    instruction: "Focus on a common mistake, why it happens, and what to do instead.",
  },
  {
    key: "smarter_shortcut",
    label: "Smarter Shortcut",
    instruction: "Offer a clearer, simpler, or smarter way to handle the topic in a home kitchen.",
  },
  {
    key: "what_most_people_get_wrong",
    label: "What Most People Get Wrong",
    instruction: "Make the angle about the exact misunderstanding most readers carry into the kitchen.",
  },
  {
    key: "ingredient_truth",
    label: "Ingredient Truth",
    instruction: "Explain what an ingredient really does and why that matters in practice.",
  },
  {
    key: "changes_how_you_cook_it",
    label: "Changes How You Cook It",
    instruction: "Make the payoff feel like a concrete shift in how the reader will cook after learning this.",
  },
  {
    key: "restaurant_vs_home",
    label: "Restaurant vs Home",
    instruction: "Contrast restaurant assumptions with what really works in a normal home kitchen.",
  },
];
const SOCIAL_ANGLE_LIBRARY = {
  recipe: RECIPE_HOOK_ANGLES,
  food_fact: FOOD_FACT_HOOK_ANGLES,
  food_story: FOOD_FACT_HOOK_ANGLES,
};

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
  let generated = hydrateStoredGeneratedPayload(job.generated_payload, job);
  let contentPackage = resolveCanonicalContentPackage(generated, job);
  let facebookCaption = String(job.facebook_caption || generated.facebook_caption || "");
  let groupShareKit = String(job.group_share_kit || generated.group_share_kit || "");
  let featuredImage = firstAttachment(job.featured_image, job.blog_image);
  let facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage);
  let socialPack = resolveFacebookChannelAdapter(generated, job).selected;
  let selectedPages = resolveSelectedFacebookPages(job, settings);
  let preferredAngle = resolvePreferredAngle(job);
  let distribution = seedLegacyFacebookDistribution(
    resolveFacebookChannelAdapter(generated, job).distribution,
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
      contentPackage = resolveCanonicalContentPackage(generated, job);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
      socialPack = resolveFacebookChannelAdapter(generated, job).selected;
      selectedPages = resolveSelectedFacebookPages(job, settings);
      assertRecipeDistributionTargets(job, selectedPages);
      preferredAngle = resolvePreferredAngle(job);
      distribution = seedLegacyFacebookDistribution(
        resolveFacebookChannelAdapter(generated, job).distribution,
        selectedPages,
        job,
        facebookCaption,
      );

      ({ featuredImage, facebookImage, generated } = await ensureJobImages(job, settings, generated, {
        featuredImage,
        facebookImage,
      }));

      socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, job.content_type || "recipe", preferredAngle);
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
      contentPackage = resolveCanonicalContentPackage(generated, job);
      facebookCaption = String(job.facebook_caption || generated.facebook_caption || facebookCaption);
      groupShareKit = String(job.group_share_kit || generated.group_share_kit || groupShareKit);
      featuredImage = firstAttachment(job.featured_image, job.blog_image, featuredImage);
      facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage, facebookImage);
      socialPack = resolveFacebookChannelAdapter(generated, job).selected;
      selectedPages = resolveSelectedFacebookPages(job, settings);
      assertRecipeDistributionTargets(job, selectedPages);
      preferredAngle = resolvePreferredAngle(job);
      distribution = seedLegacyFacebookDistribution(
        resolveFacebookChannelAdapter(generated, job).distribution,
        selectedPages,
        job,
        facebookCaption,
      );
      log(`generated ${jobLabel} and continuing directly to publish`);
    }

    if (!contentPackage.title || !contentPackage.content_html) {
      ensureOpenAiConfigured(settings);
      generated = await generatePackage(job, settings);
      contentPackage = resolveCanonicalContentPackage(generated, job);
      facebookCaption = generated.facebook_caption;
      groupShareKit = generated.group_share_kit;
      socialPack = resolveFacebookChannelAdapter(generated, job).selected;
      selectedPages = resolveSelectedFacebookPages(job, settings);
      assertRecipeDistributionTargets(job, selectedPages);
      preferredAngle = resolvePreferredAngle(job);
      distribution = seedLegacyFacebookDistribution(
        resolveFacebookChannelAdapter(generated, job).distribution,
        selectedPages,
        job,
        facebookCaption,
      );
    }

    ({ featuredImage, facebookImage, generated } = await ensureJobImages(job, settings, generated, {
      featuredImage,
      facebookImage,
    }));

    socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, job.content_type || "recipe", preferredAngle);
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
    contentPackage = resolveCanonicalContentPackage(generated, job);
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
    socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, job.content_type || "recipe", preferredAngle);
    distribution = seedLegacyFacebookDistribution(
      resolveFacebookChannelAdapter(generated, job).distribution,
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
      contentType: job.content_type || "recipe",
      retryTarget,
    });

    distribution = facebookResult.distribution;
    const firstSuccess = firstSuccessfulDistributionResult(distribution, job.content_type || "recipe");
    facebookPostId = firstSuccess?.post_id || "";
    facebookCommentId = firstSuccess?.comment_id || "";
    facebookCaption = firstSuccess?.caption || socialPack[0]?.caption || buildFallbackFacebookCaption(generated, settings.defaultCta);
    generated = syncGeneratedContractContainers({
      ...generated,
      social_pack: socialPack,
      facebook_distribution: distribution,
      facebook_urls: {
        ...(generated.facebook_urls || {}),
        facebook_comment: firstSuccess?.comment_url || "",
        facebook_post: firstSuccess?.post_url || "",
      },
    }, job);

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
  const contentPackage = resolveCanonicalContentPackage(generated, job);
  return wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/publish-blog`, {
    method: "POST",
    body: {
      content_type: contentPackage.content_type || job.content_type,
      title: contentPackage.title,
      slug: contentPackage.slug,
      excerpt: contentPackage.excerpt,
      seo_description: contentPackage.seo_description,
      content_html: contentPackage.content_html,
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
  const { article, validatorSummary } = await generateCoreArticlePackage(job, settings);
  const contentPackage = resolveCanonicalContentPackage(
    syncGeneratedContractContainers(
      {
        content_type: job.content_type || "recipe",
        topic_seed: cleanText(job.topic || ""),
        title: article.title,
        slug: article.slug,
        excerpt: article.excerpt,
        seo_description: article.seo_description,
        content_pages: Array.isArray(article.content_pages) ? article.content_pages : [],
        page_flow: Array.isArray(article.page_flow) ? article.page_flow : [],
        content_html: article.content_html,
        image_prompt: article.image_prompt,
        image_alt: article.image_alt,
        recipe: article.recipe,
        content_machine: {
          validator_summary: {
            article_stage_quality_status: validatorSummary.article_stage_quality_status || "pass",
            article_stage_checks: Array.isArray(validatorSummary.article_stage_checks) ? validatorSummary.article_stage_checks : [],
            article_title_score: validatorSummary.article_title_score,
            article_title_strong: validatorSummary.article_title_strong,
            article_title_front_load_score: validatorSummary.article_title_front_load_score,
            article_opening_alignment_score: validatorSummary.article_opening_alignment_score,
            article_excerpt_signal_score: validatorSummary.article_excerpt_signal_score,
            article_excerpt_front_load_score: validatorSummary.article_excerpt_front_load_score,
            article_seo_signal_score: validatorSummary.article_seo_signal_score,
            article_seo_front_load_score: validatorSummary.article_seo_front_load_score,
            article_excerpt_adds_value: validatorSummary.article_excerpt_adds_value,
            article_opening_adds_value: validatorSummary.article_opening_adds_value,
            article_opening_front_load_score: validatorSummary.article_opening_front_load_score,
          },
        },
      },
      job,
    ),
    job,
  );
  const selectedPages = resolveSelectedFacebookPages(job, settings);
  const preferredAngle = resolvePreferredAngle(job);
  let socialCandidates = [];
  let socialValidatorSummary = {
    social_repair_attempts: 0,
    social_repaired: false,
    last_social_validation_error: "",
    social_pool_quality_status: "fallback",
    social_pool_size: 0,
    strong_social_candidates: 0,
    specific_social_candidates: 0,
    conversation_social_candidates: 0,
    recognition_social_candidates: 0,
    savvy_social_candidates: 0,
    identity_shift_social_candidates: 0,
    immediacy_social_candidates: 0,
    habit_shift_social_candidates: 0,
    promise_sync_candidates: 0,
    scannable_social_candidates: 0,
    two_step_social_candidates: 0,
    front_loaded_social_candidates: 0,
    curiosity_social_candidates: 0,
    contrast_social_candidates: 0,
    pain_point_social_candidates: 0,
    payoff_social_candidates: 0,
    high_scoring_social_candidates: 0,
  };
  let distributionSource = "model_pool";

  try {
    const socialResult = await generateSocialCandidatePool(job, settings, contentPackage, selectedPages, preferredAngle);
    socialCandidates = Array.isArray(socialResult?.candidates) ? socialResult.candidates : [];
    socialValidatorSummary = isPlainObject(socialResult?.validatorSummary)
      ? { ...socialValidatorSummary, ...socialResult.validatorSummary }
      : socialValidatorSummary;
  } catch (error) {
    distributionSource = "local_fallback";
    log(`social fallback for job #${job.id}: ${formatError(error)}`);
    socialCandidates = [];
  }

  const socialPack = ensureSocialPackCoverage(
    socialCandidates,
    selectedPages,
    contentPackage,
    settings,
    job.content_type || "recipe",
    preferredAngle,
  );
  const selectedSocialSummary = summarizeSelectedSocialPack(socialPack, contentPackage, job.content_type || "recipe");
  if (distributionSource !== "local_fallback" && socialCandidates.length < Math.max(1, selectedPages.length)) {
    distributionSource = "partial_fallback";
  }

  return normalizeGeneratedPayload(
    {
      content_type: contentPackage.content_type,
      topic_seed: contentPackage.topic_seed,
      title: contentPackage.title,
      slug: contentPackage.slug,
      excerpt: contentPackage.excerpt,
      seo_description: contentPackage.seo_description,
      content_html: contentPackage.content_html,
      content_pages: contentPackage.content_pages,
      page_flow: contentPackage.page_flow,
      image_prompt: contentPackage.image_prompt,
      image_alt: contentPackage.image_alt,
      recipe: contentPackage.recipe,
      content_package: contentPackage,
      facebook_caption: socialPack[0]?.caption || "",
      social_candidates: socialCandidates,
      social_pack: socialPack,
      channels: {
        facebook: {
          channel: "facebook",
          live: true,
          profile: buildChannelProfile("facebook"),
          candidates: socialCandidates,
          selected: socialPack,
          distribution: normalizeFacebookDistribution({}, job.content_type || "recipe"),
        },
        pinterest: {
          channel: "pinterest",
          live: false,
          profile: buildChannelProfile("pinterest"),
          draft: buildPinterestDraft(contentPackage),
        },
      },
      content_machine: {
        prompt_version: PROMPT_VERSION,
        publication_profile: resolvePublicationProfile(settings).name || settings.siteName,
        content_preset: job.content_type,
        validator_summary: {
          repair_attempts: validatorSummary.repair_attempts,
          repaired: validatorSummary.repaired,
          last_validation_error: validatorSummary.last_validation_error,
          article_stage_quality_status: validatorSummary.article_stage_quality_status || "pass",
          article_stage_checks: Array.isArray(validatorSummary.article_stage_checks) ? validatorSummary.article_stage_checks : [],
          article_title_score: validatorSummary.article_title_score,
          article_title_strong: validatorSummary.article_title_strong,
          article_title_front_load_score: validatorSummary.article_title_front_load_score,
          article_opening_alignment_score: validatorSummary.article_opening_alignment_score,
          article_excerpt_signal_score: validatorSummary.article_excerpt_signal_score,
          article_excerpt_front_load_score: validatorSummary.article_excerpt_front_load_score,
          article_seo_signal_score: validatorSummary.article_seo_signal_score,
          article_seo_front_load_score: validatorSummary.article_seo_front_load_score,
          article_excerpt_adds_value: validatorSummary.article_excerpt_adds_value,
          article_opening_adds_value: validatorSummary.article_opening_adds_value,
          article_opening_front_load_score: validatorSummary.article_opening_front_load_score,
          social_repair_attempts: socialValidatorSummary.social_repair_attempts,
          social_repaired: socialValidatorSummary.social_repaired,
          last_social_validation_error: socialValidatorSummary.last_social_validation_error,
          social_pool_quality_status: socialValidatorSummary.social_pool_quality_status,
          social_pool_size: socialValidatorSummary.social_pool_size,
          strong_social_candidates: socialValidatorSummary.strong_social_candidates,
          specific_social_candidates: socialValidatorSummary.specific_social_candidates,
          conversation_social_candidates: socialValidatorSummary.conversation_social_candidates,
          recognition_social_candidates: socialValidatorSummary.recognition_social_candidates,
          savvy_social_candidates: socialValidatorSummary.savvy_social_candidates,
          identity_shift_social_candidates: socialValidatorSummary.identity_shift_social_candidates,
          scannable_social_candidates: socialValidatorSummary.scannable_social_candidates,
          anchored_social_candidates: socialValidatorSummary.anchored_social_candidates,
          relatable_social_candidates: socialValidatorSummary.relatable_social_candidates,
          proof_social_candidates: socialValidatorSummary.proof_social_candidates,
          actionable_social_candidates: socialValidatorSummary.actionable_social_candidates,
          immediacy_social_candidates: socialValidatorSummary.immediacy_social_candidates,
          habit_shift_social_candidates: socialValidatorSummary.habit_shift_social_candidates,
          focused_social_candidates: socialValidatorSummary.focused_social_candidates,
          promise_sync_candidates: socialValidatorSummary.promise_sync_candidates,
          two_step_social_candidates: socialValidatorSummary.two_step_social_candidates,
          novelty_social_candidates: socialValidatorSummary.novelty_social_candidates,
          front_loaded_social_candidates: socialValidatorSummary.front_loaded_social_candidates,
          curiosity_social_candidates: socialValidatorSummary.curiosity_social_candidates,
          resolution_social_candidates: socialValidatorSummary.resolution_social_candidates,
          contrast_social_candidates: socialValidatorSummary.contrast_social_candidates,
          pain_point_social_candidates: socialValidatorSummary.pain_point_social_candidates,
          payoff_social_candidates: socialValidatorSummary.payoff_social_candidates,
          high_scoring_social_candidates: socialValidatorSummary.high_scoring_social_candidates,
          selected_social_average_score: selectedSocialSummary.selected_social_average_score,
          specific_social_variants: selectedSocialSummary.specific_social_variants,
          anchored_variants: selectedSocialSummary.anchored_variants,
          relatable_variants: selectedSocialSummary.relatable_variants,
          recognition_variants: selectedSocialSummary.recognition_variants,
          conversation_variants: selectedSocialSummary.conversation_variants,
          savvy_variants: selectedSocialSummary.savvy_variants,
          identity_shift_variants: selectedSocialSummary.identity_shift_variants,
          proof_variants: selectedSocialSummary.proof_variants,
          actionable_variants: selectedSocialSummary.actionable_variants,
          immediacy_variants: selectedSocialSummary.immediacy_variants,
          consequence_variants: selectedSocialSummary.consequence_variants,
          habit_shift_variants: selectedSocialSummary.habit_shift_variants,
          focused_variants: selectedSocialSummary.focused_variants,
          promise_sync_variants: selectedSocialSummary.promise_sync_variants,
          scannable_variants: selectedSocialSummary.scannable_variants,
          two_step_variants: selectedSocialSummary.two_step_variants,
          novelty_variants: selectedSocialSummary.novelty_variants,
          curiosity_variants: selectedSocialSummary.curiosity_variants,
          resolution_variants: selectedSocialSummary.resolution_variants,
          contrast_variants: selectedSocialSummary.contrast_variants,
          front_loaded_social_variants: selectedSocialSummary.front_loaded_social_variants,
          unique_social_hook_forms: selectedSocialSummary.unique_hook_forms,
          pain_point_variants: selectedSocialSummary.pain_point_variants,
          payoff_variants: selectedSocialSummary.payoff_variants,
          lead_social_score: selectedSocialSummary.lead_social_score,
          lead_social_hook_form: selectedSocialSummary.lead_social_hook_form,
          lead_social_specific: selectedSocialSummary.lead_social_specific,
          lead_social_anchored: selectedSocialSummary.lead_social_anchored,
          lead_social_relatable: selectedSocialSummary.lead_social_relatable,
          lead_social_recognition: selectedSocialSummary.lead_social_recognition,
          lead_social_conversation: selectedSocialSummary.lead_social_conversation,
          lead_social_savvy: selectedSocialSummary.lead_social_savvy,
          lead_social_identity_shift: selectedSocialSummary.lead_social_identity_shift,
          lead_social_proof: selectedSocialSummary.lead_social_proof,
          lead_social_actionable: selectedSocialSummary.lead_social_actionable,
          lead_social_immediacy: selectedSocialSummary.lead_social_immediacy,
          lead_social_consequence: selectedSocialSummary.lead_social_consequence,
          lead_social_habit_shift: selectedSocialSummary.lead_social_habit_shift,
          lead_social_focused: selectedSocialSummary.lead_social_focused,
          lead_social_promise_sync: selectedSocialSummary.lead_social_promise_sync,
          lead_social_scannable: selectedSocialSummary.lead_social_scannable,
          lead_social_two_step: selectedSocialSummary.lead_social_two_step,
          lead_social_novelty: selectedSocialSummary.lead_social_novelty,
          lead_social_curiosity: selectedSocialSummary.lead_social_curiosity,
          lead_social_resolved: selectedSocialSummary.lead_social_resolved,
          lead_social_contrast: selectedSocialSummary.lead_social_contrast,
          lead_social_front_loaded: selectedSocialSummary.lead_social_front_loaded,
          lead_social_pain_point: selectedSocialSummary.lead_social_pain_point,
          lead_social_payoff: selectedSocialSummary.lead_social_payoff,
          distribution_source: distributionSource,
          target_pages: Math.max(1, selectedPages.length),
          social_variants: socialPack.length,
        },
      },
    },
    job,
  );
}

function buildQualitySummary(job, generated, settings, options = {}) {
  const contentPackage = resolveCanonicalContentPackage(generated, job);
  const facebookChannel = resolveFacebookChannelAdapter(generated, job);
  const contentType = contentPackage.content_type || job.content_type || "recipe";
  const articleSignals = isPlainObject(contentPackage.article_signals)
    ? contentPackage.article_signals
    : buildArticleSocialSignals(contentPackage, contentType);
  const contentHtml = String(contentPackage.content_html || "");
  const contentPages = Array.isArray(contentPackage.content_pages) && contentPackage.content_pages.length
    ? contentPackage.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "")
    : splitHtmlIntoPages(contentHtml, contentType).slice(0, 3);
  const pageFlow = normalizeGeneratedPageFlow(Array.isArray(contentPackage.page_flow) ? contentPackage.page_flow : [], contentPages);
  const pageWordCounts = contentPages.map((page) => cleanText(page.replace(/<[^>]+>/g, " ")).split(/\s+/).filter(Boolean).length);
  const pageCount = contentPages.length || 1;
  const shortestPageWords = pageWordCounts.length ? Math.min(...pageWordCounts) : 0;
  const strongPageOpenings = contentPages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;
  const uniquePageLabels = new Set(pageFlow.map((page) => normalizePageFlowLabelFingerprint(page?.label || "")).filter(Boolean));
  const strongPageLabels = pageFlow.filter((page, index) => pageFlowLabelLooksStrong(page?.label || "", index)).length;
  const strongPageSummaries = pageFlow.filter((page) => pageFlowSummaryLooksStrong(page?.summary || "", page?.label || "")).length;
  const selectedPages = Array.isArray(options.selectedPages) ? options.selectedPages : resolveSelectedFacebookPages(job, settings);
  const socialPack = Array.isArray(facebookChannel.selected) ? facebookChannel.selected : [];
  const fingerprints = socialPack.map((variant) => normalizeSocialFingerprint(variant)).filter(Boolean);
  const uniqueFingerprints = new Set(fingerprints);
  const uniqueHookFingerprints = new Set(socialPack.map((variant) => normalizeHookFingerprint(variant)).filter(Boolean));
  const uniqueCaptionOpenings = new Set(socialPack.map((variant) => normalizeCaptionOpeningFingerprint(variant)).filter(Boolean));
  const uniqueAngleKeys = new Set(socialPack.map((variant) => normalizeAngleKey(variant?.angle_key || "", contentType)).filter(Boolean));
  const uniqueHookForms = new Set(socialPack.map((variant) => classifySocialHookForm(variant)).filter(Boolean));
  const strongSocialVariants = socialPack.filter((variant) => !socialVariantLooksWeak(variant, contentPackage.title || "", contentType, articleSignals)).length;
  const selectedSocialSummary = summarizeSelectedSocialPack(socialPack, contentPackage, contentType);
  const signalTargets = desiredSocialSignalTargets(selectedPages.length);
  const recipe = isPlainObject(contentPackage.recipe) ? contentPackage.recipe : {};
  const wordCount = cleanText(contentHtml.replace(/<[^>]+>/g, " ")).split(/\s+/).filter(Boolean).length;
  const minimumWords = Number(contentType === "recipe" ? 1200 : 1100);
  const h2Count = (contentHtml.match(/<h2\b/gi) || []).length;
  const internalLinks = countInternalLinks(contentHtml);
  const excerptWords = cleanText(contentPackage.excerpt || "").split(/\s+/).filter(Boolean).length;
  const seoWords = cleanText(contentPackage.seo_description || "").split(/\s+/).filter(Boolean).length;
  const openingParagraph = extractOpeningParagraphText(contentPackage);
  const titleScore = headlineSpecificityScore(contentPackage.title || "", contentType, job?.topic || "");
  const titleStrong = titleLooksStrong(contentPackage.title || "", job?.topic || "", contentType);
  const titleFrontLoadScore = frontLoadedClickSignalScore(contentPackage.title || "", contentType);
  const excerptFrontLoadScore = frontLoadedClickSignalScore(contentPackage.excerpt || "", contentType);
  const seoFrontLoadScore = frontLoadedClickSignalScore(contentPackage.seo_description || "", contentType);
  const openingFrontLoadScore = frontLoadedClickSignalScore(openingParagraph || "", contentType);
  const openingAlignmentScore = openingPromiseAlignmentScore(contentPackage.title || "", openingParagraph);
  const excerptAddsValue = excerptAddsNewValue(contentPackage.title || "", contentPackage.excerpt || "");
  const openingAddsValue = openingParagraphAddsNewValue(contentHtml, contentPackage.title || "", contentPackage.excerpt || "");
  const excerptSignalScore = excerptClickSignalScore(contentPackage.excerpt || "", contentPackage.title || "", openingParagraph);
  const seoSignalScore = seoDescriptionSignalScore(contentPackage.seo_description || "", contentPackage.title || "", contentPackage.excerpt || "");
  const recipeComplete = contentType !== "recipe" || (ensureStringArray(recipe.ingredients).length > 0 && ensureStringArray(recipe.instructions).length > 0);
  const featuredImageReady = Boolean(options.featuredImage?.id || job.featured_image?.id || job.blog_image?.id);
  const facebookImageReady = Boolean(options.facebookImage?.id || job.facebook_image_result?.id || job.facebook_image?.id || options.featuredImage?.id || job.featured_image?.id || job.blog_image?.id);
  const imageReady = settings.imageGenerationMode === "manual_only"
    ? featuredImageReady && facebookImageReady
    : featuredImageReady && facebookImageReady;
  const duplicateRisk = Boolean(options.duplicateRisk);

  const blockingChecks = [];
  const warningChecks = [];
  if (!cleanText(contentPackage.title || "") || !cleanText(contentPackage.slug || "") || !cleanText(contentHtml.replace(/<[^>]+>/g, " "))) {
    blockingChecks.push("missing_core_fields");
  }
  if (contentType === "recipe" && !recipeComplete) {
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
  if (!titleStrong || titleScore < 3) {
    warningChecks.push("weak_title");
  }
  if (excerptWords < 12 || !excerptAddsValue || excerptSignalScore < 3) {
    warningChecks.push("weak_excerpt");
  }
  if (seoWords < 12 || seoSignalScore < 3) {
    warningChecks.push("weak_seo");
  }
  if (openingAlignmentScore < 2 || !openingAddsValue) {
    warningChecks.push("weak_title_alignment");
  }
  if (pageCount < 2 || pageCount > 3) {
    warningChecks.push("weak_pagination");
  }
  if (pageCount > 1 && shortestPageWords > 0 && shortestPageWords < 140) {
    warningChecks.push("weak_page_balance");
  }
  if (pageCount > 1 && strongPageOpenings < pageCount) {
    warningChecks.push("weak_page_openings");
  }
  if (pageCount > 1 && pageFlow.length < pageCount) {
    warningChecks.push("weak_page_flow");
  }
  if (pageCount > 1 && strongPageLabels < pageCount) {
    warningChecks.push("weak_page_labels");
  }
  if (pageCount > 1 && uniquePageLabels.size < pageCount) {
    warningChecks.push("repetitive_page_labels");
  }
  if (pageCount > 1 && strongPageSummaries < pageCount) {
    warningChecks.push("weak_page_summaries");
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
  if (selectedPages.length > 1 && uniqueAngleKeys.size < Math.min(selectedPages.length, angleDefinitionsForType(contentType).length)) {
    warningChecks.push("social_angles_repetitive");
  }
  if (selectedPages.length > 1 && uniqueHookForms.size < Math.max(2, Math.min(3, selectedPages.length))) {
    warningChecks.push("social_hook_forms_thin");
  }
  if (strongSocialVariants < Math.max(1, selectedPages.length)) {
    warningChecks.push("weak_social_copy");
  }
  if (selectedPages.length > 0 && (selectedSocialSummary.lead_social_score < 16 || !selectedSocialSummary.lead_social_specific || !selectedSocialSummary.lead_social_novelty || !selectedSocialSummary.lead_social_anchored || !selectedSocialSummary.lead_social_relatable || !selectedSocialSummary.lead_social_recognition || !selectedSocialSummary.lead_social_front_loaded || !selectedSocialSummary.lead_social_focused || !selectedSocialSummary.lead_social_promise_sync || !selectedSocialSummary.lead_social_scannable || !selectedSocialSummary.lead_social_two_step || ((selectedSocialSummary.lead_social_curiosity || selectedSocialSummary.lead_social_contrast) && !selectedSocialSummary.lead_social_resolved) || (!selectedSocialSummary.lead_social_pain_point && !selectedSocialSummary.lead_social_payoff && !selectedSocialSummary.lead_social_consequence && !selectedSocialSummary.lead_social_habit_shift && !selectedSocialSummary.lead_social_savvy && !selectedSocialSummary.lead_social_identity_shift))) {
    warningChecks.push("weak_social_lead");
  }
  if (selectedSocialSummary.specific_social_variants < Math.max(1, Math.min(selectedPages.length || 1, 2))) {
    warningChecks.push("social_specificity_thin");
  }
  if (selectedPages.length > 0 && selectedSocialSummary.anchored_variants < Math.max(1, Math.min(selectedPages.length || 1, 2))) {
    warningChecks.push("social_anchor_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.relatable_variants < 1) {
    warningChecks.push("social_relatability_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.recognition_variants < 1) {
    warningChecks.push("social_recognition_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.conversation_variants < 1) {
    warningChecks.push("social_conversation_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.savvy_variants < 1) {
    warningChecks.push("social_savvy_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.identity_shift_variants < 1) {
    warningChecks.push("social_identity_shift_thin");
  }
  if (selectedPages.length > 0 && selectedSocialSummary.novelty_variants < Math.max(1, Math.min(selectedPages.length || 1, 2))) {
    warningChecks.push("social_novelty_thin");
  }
  if (selectedPages.length > 0 && selectedSocialSummary.front_loaded_social_variants < Math.max(1, Math.min(selectedPages.length || 1, 2))) {
    warningChecks.push("social_front_load_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.curiosity_variants < 1) {
    warningChecks.push("social_curiosity_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.resolution_variants < 1) {
    warningChecks.push("social_resolution_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.contrast_variants < 1) {
    warningChecks.push("social_contrast_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.pain_point_variants < signalTargets.painPointMin) {
    warningChecks.push("social_pain_points_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.payoff_variants < signalTargets.payoffMin) {
    warningChecks.push("social_payoffs_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.proof_variants < 1) {
    warningChecks.push("social_proof_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.actionable_variants < 1) {
    warningChecks.push("social_actionability_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.immediacy_variants < 1) {
    warningChecks.push("social_immediacy_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.consequence_variants < 1) {
    warningChecks.push("social_consequence_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.habit_shift_variants < 1) {
    warningChecks.push("social_habit_shift_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.focused_variants < 1) {
    warningChecks.push("social_focus_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.promise_sync_variants < 1) {
    warningChecks.push("social_promise_sync_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.scannable_variants < 1) {
    warningChecks.push("social_scannability_thin");
  }
  if (selectedPages.length > 1 && selectedSocialSummary.two_step_variants < 1) {
    warningChecks.push("social_two_step_thin");
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
    weak_title: 8,
    weak_excerpt: 8,
    weak_seo: 8,
    weak_title_alignment: 7,
    weak_pagination: 8,
    weak_page_balance: 7,
    weak_page_openings: 6,
    weak_page_flow: 6,
    weak_page_labels: 5,
    repetitive_page_labels: 5,
    weak_page_summaries: 5,
    weak_structure: 10,
    missing_internal_links: 9,
    social_pack_incomplete: 12,
    social_pack_repetitive: 10,
    social_hooks_repetitive: 8,
    social_openings_repetitive: 8,
    social_angles_repetitive: 8,
    social_hook_forms_thin: 5,
    weak_social_copy: 10,
    weak_social_lead: 8,
    social_specificity_thin: 8,
    social_anchor_thin: 7,
    social_relatability_thin: 6,
    social_recognition_thin: 6,
    social_conversation_thin: 6,
    social_savvy_thin: 6,
    social_identity_shift_thin: 6,
    social_novelty_thin: 7,
    social_front_load_thin: 7,
    social_curiosity_thin: 6,
    social_resolution_thin: 6,
    social_contrast_thin: 6,
    social_pain_points_thin: 6,
    social_payoffs_thin: 6,
    social_proof_thin: 6,
    social_actionability_thin: 6,
    social_immediacy_thin: 6,
    social_consequence_thin: 6,
    social_habit_shift_thin: 6,
    social_focus_thin: 6,
    social_promise_sync_thin: 6,
    social_scannability_thin: 6,
    social_two_step_thin: 6,
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
  const editorialSummary = buildEditorialReadinessSummary({
    qualityStatus,
    qualityScore,
    titleStrong,
    openingAlignmentScore,
    pageCount,
    strongPageOpenings,
    strongPageSummaries,
    targetPages: selectedPages.length,
    strongSocialVariants,
    leadSocialScore: selectedSocialSummary.lead_social_score,
    leadSocialSpecific: selectedSocialSummary.lead_social_specific,
    leadSocialFrontLoaded: selectedSocialSummary.lead_social_front_loaded,
    leadSocialPromiseSync: selectedSocialSummary.lead_social_promise_sync,
    blockingChecks: dedupedBlockingChecks,
    warningChecks: dedupedWarningChecks,
  });

  return {
    quality_score: qualityScore,
    quality_status: qualityStatus,
    blocking_checks: dedupedBlockingChecks,
    warning_checks: dedupedWarningChecks,
    failed_checks: failedChecks,
    package_quality: {
      ...buildContentPackageQualitySummary({
        ...generated,
        content_type: contentType,
        content_package: contentPackage,
      }),
      warning_checks: dedupedWarningChecks.filter((check) => !/^social_/.test(check)),
    },
    channel_quality: {
      facebook: {
        ...buildFacebookChannelQualitySummary({
          ...generated,
          content_type: contentType,
          social_pack: socialPack,
        }),
        warning_checks: filterChannelWarningChecks(dedupedWarningChecks, "facebook"),
      },
    },
    editorial_readiness: editorialSummary.editorial_readiness,
    editorial_highlights: editorialSummary.editorial_highlights,
    editorial_watchouts: editorialSummary.editorial_watchouts,
    quality_checks: {
      word_count: wordCount,
      minimum_words: minimumWords,
      title_score: titleScore,
      title_strong: titleStrong,
      title_front_load_score: titleFrontLoadScore,
      opening_alignment_score: openingAlignmentScore,
      excerpt_adds_value: excerptAddsValue,
      opening_adds_value: openingAddsValue,
      opening_front_load_score: openingFrontLoadScore,
      h2_count: h2Count,
      internal_links: internalLinks,
      excerpt_words: excerptWords,
      excerpt_signal_score: excerptSignalScore,
      excerpt_front_load_score: excerptFrontLoadScore,
      seo_words: seoWords,
      seo_signal_score: seoSignalScore,
      seo_front_load_score: seoFrontLoadScore,
      page_count: pageCount,
      shortest_page_words: shortestPageWords,
      strong_page_openings: strongPageOpenings,
      unique_page_labels: uniquePageLabels.size,
      strong_page_labels: strongPageLabels,
      strong_page_summaries: strongPageSummaries,
      recipe_complete: recipeComplete,
      image_ready: imageReady,
      target_pages: selectedPages.length,
      social_variants: socialPack.length,
      unique_social_variants: uniqueFingerprints.size,
      unique_social_hooks: uniqueHookFingerprints.size,
      unique_social_openings: uniqueCaptionOpenings.size,
      unique_social_angles: uniqueAngleKeys.size,
      unique_social_hook_forms: uniqueHookForms.size,
      strong_social_variants: strongSocialVariants,
      specific_social_variants: selectedSocialSummary.specific_social_variants,
      anchored_variants: selectedSocialSummary.anchored_variants,
      relatable_variants: selectedSocialSummary.relatable_variants,
      recognition_variants: selectedSocialSummary.recognition_variants,
      conversation_variants: selectedSocialSummary.conversation_variants,
      savvy_variants: selectedSocialSummary.savvy_variants,
      identity_shift_variants: selectedSocialSummary.identity_shift_variants,
      novelty_variants: selectedSocialSummary.novelty_variants,
      curiosity_variants: selectedSocialSummary.curiosity_variants,
      resolution_variants: selectedSocialSummary.resolution_variants,
      contrast_variants: selectedSocialSummary.contrast_variants,
      front_loaded_social_variants: selectedSocialSummary.front_loaded_social_variants,
      pain_point_variants: selectedSocialSummary.pain_point_variants,
      payoff_variants: selectedSocialSummary.payoff_variants,
      proof_variants: selectedSocialSummary.proof_variants,
      actionable_variants: selectedSocialSummary.actionable_variants,
      immediacy_variants: selectedSocialSummary.immediacy_variants,
      consequence_variants: selectedSocialSummary.consequence_variants,
      habit_shift_variants: selectedSocialSummary.habit_shift_variants,
      focused_variants: selectedSocialSummary.focused_variants,
      promise_sync_variants: selectedSocialSummary.promise_sync_variants,
      scannable_variants: selectedSocialSummary.scannable_variants,
      two_step_variants: selectedSocialSummary.two_step_variants,
      selected_social_average_score: selectedSocialSummary.selected_social_average_score,
      lead_social_score: selectedSocialSummary.lead_social_score,
      lead_social_hook_form: selectedSocialSummary.lead_social_hook_form,
      lead_social_specific: selectedSocialSummary.lead_social_specific,
      lead_social_anchored: selectedSocialSummary.lead_social_anchored,
      lead_social_relatable: selectedSocialSummary.lead_social_relatable,
      lead_social_recognition: selectedSocialSummary.lead_social_recognition,
      lead_social_conversation: selectedSocialSummary.lead_social_conversation,
      lead_social_savvy: selectedSocialSummary.lead_social_savvy,
      lead_social_identity_shift: selectedSocialSummary.lead_social_identity_shift,
      lead_social_novelty: selectedSocialSummary.lead_social_novelty,
      lead_social_curiosity: selectedSocialSummary.lead_social_curiosity,
      lead_social_resolved: selectedSocialSummary.lead_social_resolved,
      lead_social_contrast: selectedSocialSummary.lead_social_contrast,
      lead_social_front_loaded: selectedSocialSummary.lead_social_front_loaded,
      lead_social_pain_point: selectedSocialSummary.lead_social_pain_point,
      lead_social_payoff: selectedSocialSummary.lead_social_payoff,
      lead_social_proof: selectedSocialSummary.lead_social_proof,
      lead_social_actionable: selectedSocialSummary.lead_social_actionable,
      lead_social_immediacy: selectedSocialSummary.lead_social_immediacy,
      lead_social_consequence: selectedSocialSummary.lead_social_consequence,
      lead_social_habit_shift: selectedSocialSummary.lead_social_habit_shift,
      lead_social_focused: selectedSocialSummary.lead_social_focused,
      lead_social_promise_sync: selectedSocialSummary.lead_social_promise_sync,
      lead_social_scannable: selectedSocialSummary.lead_social_scannable,
      lead_social_two_step: selectedSocialSummary.lead_social_two_step,
      duplicate_risk: duplicateRisk,
    },
  };
}

function qualityCheckReasonMessage(check) {
  const messages = {
    missing_core_fields: "Missing title, slug, or article body.",
    missing_recipe: "Recipe card is incomplete.",
    missing_manual_images: "Manual-only image slots are still missing.",
    duplicate_conflict: "Title or slug conflicts with an existing post.",
    missing_target_pages: "No Facebook pages are attached.",
    thin_content: "Article body is still too thin.",
    weak_title: "Headline promise is still soft.",
    weak_excerpt: "Excerpt is too generic or slow to earn the click.",
    weak_seo: "SEO line is too weak or buried.",
    weak_title_alignment: "Page-one opening does not cash the headline fast enough.",
    weak_pagination: "Article split is not a strong 2-3 page flow yet.",
    weak_page_balance: "One article page is too thin.",
    weak_page_openings: "One page opens weakly.",
    weak_page_flow: "Page labels or summaries still need work.",
    weak_page_labels: "Page labels are too generic.",
    repetitive_page_labels: "Page labels feel repetitive.",
    weak_page_summaries: "Page summaries are too thin.",
    weak_structure: "Article needs stronger H2 structure.",
    missing_internal_links: "Article needs more internal links.",
    social_pack_incomplete: "Social pack does not cover every selected page.",
    social_pack_repetitive: "Selected variants still feel repetitive.",
    social_hooks_repetitive: "Hooks still feel repetitive.",
    social_openings_repetitive: "Caption openings still feel repetitive.",
    social_angles_repetitive: "Angles are not varied enough.",
    social_hook_forms_thin: "Hook sentence patterns are too narrow.",
    weak_social_copy: "Some selected social copy is still weak.",
    weak_social_lead: "Lead Facebook variant is not strong enough yet.",
    social_specificity_thin: "Too few variants feel concrete and article-specific.",
    social_anchor_thin: "Too few variants are anchored in a real detail.",
    social_relatability_thin: "Too few variants feel recognizably real.",
    social_recognition_thin: "Too few variants create an immediate self-recognition moment.",
    social_conversation_thin: "Too few variants feel naturally discussable.",
    social_savvy_thin: "Too few variants feel like a smarter move.",
    social_identity_shift_thin: "Too few variants create a real old-default vs better-move snap.",
    social_novelty_thin: "Too few variants add a fresh detail beyond the title.",
    social_front_load_thin: "Too few variants front-load the useful thing fast enough.",
    social_curiosity_thin: "Too few variants create honest curiosity.",
    social_resolution_thin: "Too few variants resolve the hook early.",
    social_contrast_thin: "Too few variants use a clean contrast.",
    social_pain_points_thin: "Too few variants frame a clear problem.",
    social_payoffs_thin: "Too few variants frame a clear payoff.",
    social_proof_thin: "Too few variants carry a believable clue or proof.",
    social_actionability_thin: "Too few variants feel immediately usable.",
    social_immediacy_thin: "Too few variants feel relevant right now.",
    social_consequence_thin: "Too few variants make the cost of ignoring it feel real.",
    social_habit_shift_thin: "Too few variants break the old habit cleanly.",
    social_focus_thin: "Too few variants stay centered on one promise.",
    social_promise_sync_thin: "Too few variants line up cleanly with the article promise.",
    social_scannability_thin: "Too few variants are easy to scan fast.",
    social_two_step_thin: "Too few variants make line 1 and line 2 do different jobs.",
    image_not_ready: "Required images are not ready yet.",
  };
  return messages[check] || cleanText(String(check || "").replace(/_/g, " "));
}

function buildEditorialReadinessSummary({
  qualityStatus = "warn",
  qualityScore = 0,
  titleStrong = false,
  openingAlignmentScore = 0,
  pageCount = 1,
  strongPageOpenings = 0,
  strongPageSummaries = 0,
  targetPages = 0,
  strongSocialVariants = 0,
  leadSocialScore = 0,
  leadSocialSpecific = false,
  leadSocialFrontLoaded = false,
  leadSocialPromiseSync = false,
  blockingChecks = [],
  warningChecks = [],
} = {}) {
  const normalizedStatus = String(qualityStatus || "").toLowerCase();
  const readiness = normalizedStatus === "block"
    ? "blocked"
    : (
      qualityScore >= 88
      && Boolean(titleStrong)
      && Number(openingAlignmentScore) >= 2
      && Number(pageCount) >= 2
      && Number(pageCount) <= 3
      && Number(strongPageOpenings) >= Number(pageCount)
      && Number(strongPageSummaries) >= Number(pageCount)
      && Number(strongSocialVariants) >= Math.max(1, Number(targetPages) || 1)
      && Number(leadSocialScore) >= 18
      && Boolean(leadSocialSpecific)
      && Boolean(leadSocialFrontLoaded)
      && Boolean(leadSocialPromiseSync)
    )
      ? "ready"
      : "review";

  const highlights = [];
  if (titleStrong && Number(openingAlignmentScore) >= 2) {
    highlights.push("Headline and page-one opening land the same promise.");
  }
  if (Number(pageCount) >= 2 && Number(pageCount) <= 3 && Number(strongPageSummaries) >= Number(pageCount)) {
    highlights.push(`Article flow feels intentional across ${pageCount} pages.`);
  }
  if (Number(strongSocialVariants) >= Math.max(1, Number(targetPages) || 1) && Number(leadSocialScore) >= 18) {
    highlights.push("Social pack has a strong lead and enough usable variants.");
  }
  if (!highlights.length && normalizedStatus !== "block" && Number(qualityScore) >= 75) {
    highlights.push("Core package is usable for live testing.");
  }

  const watchouts = [...blockingChecks, ...warningChecks]
    .slice(0, 3)
    .map((check) => qualityCheckReasonMessage(check));

  if (!watchouts.length && readiness === "ready") {
    watchouts.push("No major editorial warnings.");
  }

  return {
    editorial_readiness: readiness,
    editorial_highlights: highlights.slice(0, 3),
    editorial_watchouts: watchouts,
  };
}

function buildContentTypeProfile(contentType = "recipe") {
  const key = String(contentType || "recipe") === "food_fact"
    ? "food_fact"
    : (String(contentType || "recipe") === "food_story" ? "food_story" : "recipe");

  return {
    key,
    contract_version: CONTENT_PACKAGE_CONTRACT_VERSION,
    package_shape: "canonical_content_package",
    input_mode: key === "recipe" ? "dish_name" : "working_title",
    article_stage: `${key}_article`,
    validation_mode: key === "recipe" ? "recipe_article" : "editorial_article",
    rendering_mode: key === "recipe" ? "recipe_multipage" : "editorial_multipage",
    recipe_required: key === "recipe",
  };
}

function buildChannelProfile(channel = "facebook") {
  if (channel === "pinterest") {
    return {
      key: "pinterest",
      contract_version: CHANNEL_ADAPTER_CONTRACT_VERSION,
      live: false,
      adapter: "draft_pin",
      output_shape: "pin_draft",
      input_package: "content_package",
    };
  }

  return {
    key: "facebook",
    contract_version: CHANNEL_ADAPTER_CONTRACT_VERSION,
    live: true,
    adapter: "page_distribution",
    output_shape: "social_pack",
    input_package: "content_package",
  };
}

function extractValidatorSummary(generated) {
  const contentMachine = isPlainObject(generated?.content_machine) ? generated.content_machine : {};
  return isPlainObject(contentMachine.validator_summary) ? contentMachine.validator_summary : {};
}

function filterChannelWarningChecks(checks, channel = "facebook") {
  if (!Array.isArray(checks)) {
    return [];
  }

  if (channel === "facebook") {
    return checks.filter((check) => /^social_/.test(String(check || "")) || check === "missing_target_pages");
  }

  return [];
}

function buildContentPackageQualitySummary(generated) {
  const validatorSummary = extractValidatorSummary(generated);
  const stageChecks = Array.isArray(validatorSummary.article_stage_checks) ? validatorSummary.article_stage_checks : [];

  return {
    layer: "article",
    stage_status: cleanText(validatorSummary.article_stage_quality_status || ""),
    stage_checks: stageChecks,
    title_score: toInt(validatorSummary.article_title_score),
    title_front_load_score: toInt(validatorSummary.article_title_front_load_score),
    opening_alignment_score: toInt(validatorSummary.article_opening_alignment_score),
    opening_front_load_score: toInt(validatorSummary.article_opening_front_load_score),
    excerpt_signal_score: toInt(validatorSummary.article_excerpt_signal_score),
    excerpt_front_load_score: toInt(validatorSummary.article_excerpt_front_load_score),
    seo_signal_score: toInt(validatorSummary.article_seo_signal_score),
    seo_front_load_score: toInt(validatorSummary.article_seo_front_load_score),
    excerpt_adds_value: Boolean(validatorSummary.article_excerpt_adds_value),
    opening_adds_value: Boolean(validatorSummary.article_opening_adds_value),
    editorial_readiness: cleanText(validatorSummary.editorial_readiness || ""),
  };
}

function buildFacebookChannelQualitySummary(generated) {
  const validatorSummary = extractValidatorSummary(generated);

  return {
    layer: "facebook",
    pool_quality_status: cleanText(validatorSummary.social_pool_quality_status || ""),
    distribution_source: cleanText(validatorSummary.distribution_source || ""),
    quality_status: cleanText(validatorSummary.quality_status || ""),
    quality_score: Number(validatorSummary.quality_score || 0),
    target_pages: toInt(validatorSummary.target_pages),
    social_variants: toInt(validatorSummary.social_variants),
    social_pool_size: toInt(validatorSummary.social_pool_size),
    strong_candidates: toInt(validatorSummary.strong_social_candidates),
    specific_candidates: toInt(validatorSummary.specific_social_candidates),
    unique_hooks: toInt(validatorSummary.unique_social_hooks),
    unique_openings: toInt(validatorSummary.unique_social_openings),
    unique_angles: toInt(validatorSummary.unique_social_angles),
    strong_variants: toInt(validatorSummary.strong_social_variants),
    selected_average_score: Number(validatorSummary.selected_social_average_score || 0),
    lead_score: toInt(validatorSummary.lead_social_score),
    lead_specific: Boolean(validatorSummary.lead_social_specific),
    lead_front_loaded: Boolean(validatorSummary.lead_social_front_loaded),
    lead_pain_point: Boolean(validatorSummary.lead_social_pain_point),
    lead_payoff: Boolean(validatorSummary.lead_social_payoff),
    warning_checks: filterChannelWarningChecks(validatorSummary.warning_checks, "facebook"),
  };
}

function buildPinterestDraft(contentPackage, existing = {}) {
  const articleSignals = isPlainObject(contentPackage?.article_signals) ? contentPackage.article_signals : {};
  const contentType = cleanText(contentPackage?.content_type || "recipe");
  const rawKeywords = [
    contentType === "recipe" ? "recipe" : "food facts",
    cleanText(articleSignals.heading_topic || ""),
    cleanText(articleSignals.ingredient_focus || ""),
    cleanText(articleSignals.meta_line || ""),
  ]
    .join(" ")
    .toLowerCase()
    .replace(/[^a-z0-9\s,]/g, " ")
    .split(/[\s,]+/)
    .map((token) => token.trim())
    .filter((token) => token && token.length > 2);
  const pinKeywords = Array.from(new Set(rawKeywords)).slice(0, 8);
  const fallbackTitle = trimText(cleanText(contentPackage?.title || ""), 90);
  const fallbackDescription = trimText(
    cleanText(contentPackage?.excerpt || articleSignals?.summary_line || articleSignals?.payoff_line || ""),
    180,
  );

  return {
    pin_title: trimText(cleanText(existing?.pin_title || fallbackTitle), 100),
    pin_description: trimText(cleanText(existing?.pin_description || fallbackDescription), 300),
    pin_keywords: Array.isArray(existing?.pin_keywords)
      ? existing.pin_keywords.map((keyword) => cleanText(keyword)).filter(Boolean).slice(0, 12)
      : pinKeywords,
    image_prompt_override: cleanMultilineText(
      existing?.image_prompt_override
      || `${contentPackage?.image_prompt || ""}\nVertical Pinterest pin composition, 2:3 aspect ratio, clean focal hierarchy.`,
    ),
    image_format_hint: cleanText(existing?.image_format_hint || "1000x1500 vertical pin"),
    overlay_text: trimText(cleanText(existing?.overlay_text || fallbackTitle), 70),
  };
}

function resolveCanonicalContentPackage(generated, job = null) {
  const existingPackage = isPlainObject(generated?.content_package) ? generated.content_package : {};
  const contentType = cleanText(existingPackage.content_type || generated?.content_type || job?.content_type || "recipe") || "recipe";
  const topicSeed = cleanText(existingPackage.topic_seed || generated?.topic_seed || job?.topic || "");
  const title = cleanText(existingPackage.title || generated?.title || job?.title_override || job?.topic || "");
  const slug = normalizeSlug(existingPackage.slug || generated?.slug || title);
  const excerpt = trimText(cleanText(existingPackage.excerpt || generated?.excerpt || ""), 220);
  const seoDescription = trimText(cleanText(existingPackage.seo_description || generated?.seo_description || ""), 155);
  const contentPages = Array.isArray(existingPackage.content_pages) && existingPackage.content_pages.length
    ? existingPackage.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "")
    : (Array.isArray(generated?.content_pages) ? generated.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "") : []);
  const contentHtml = cleanMultilineText(existingPackage.content_html || generated?.content_html || "") || mergeContentPagesIntoHtml(contentPages);
  const stabilizedPages = contentPages.length
    ? stabilizeGeneratedContentPages(contentPages, contentHtml, contentType)
    : splitHtmlIntoPages(contentHtml, contentType).slice(0, 3);
  const pageFlow = normalizeGeneratedPageFlow(
    Array.isArray(existingPackage.page_flow) ? existingPackage.page_flow : (Array.isArray(generated?.page_flow) ? generated.page_flow : []),
    stabilizedPages,
  );
  const imagePrompt = cleanMultilineText(existingPackage.image_prompt || generated?.image_prompt || "");
  const imageAlt = cleanText(existingPackage.image_alt || generated?.image_alt || title);
  const recipe = contentType === "recipe"
    ? normalizeRecipe(isPlainObject(existingPackage.recipe) ? existingPackage.recipe : (isPlainObject(generated?.recipe) ? generated.recipe : {}), contentType)
    : undefined;
  const articleBase = {
    contract_version: cleanText(existingPackage.contract_version || "") || CONTENT_PACKAGE_CONTRACT_VERSION,
    package_shape: cleanText(existingPackage.package_shape || "") || "canonical_content_package",
    source_layer: cleanText(existingPackage.source_layer || "") || "article_engine",
    content_type: contentType,
    topic_seed: topicSeed,
    title,
    slug,
    excerpt,
    seo_description: seoDescription,
    content_html: contentHtml,
    content_pages: stabilizedPages,
    page_flow: pageFlow,
    image_prompt: imagePrompt,
    image_alt: imageAlt,
    ...(recipe ? { recipe } : {}),
  };

  return {
    ...articleBase,
    profile: isPlainObject(existingPackage.profile)
      ? { ...buildContentTypeProfile(contentType), ...existingPackage.profile }
      : buildContentTypeProfile(contentType),
    article_signals: isPlainObject(existingPackage.article_signals) && Object.keys(existingPackage.article_signals).length
      ? existingPackage.article_signals
      : buildArticleSocialSignals(articleBase, contentType),
    quality_summary: {
      ...(isPlainObject(existingPackage.quality_summary) ? existingPackage.quality_summary : {}),
      ...buildContentPackageQualitySummary(generated),
    },
  };
}

function resolveFacebookChannelAdapter(generated, job = null) {
  const existingChannels = isPlainObject(generated?.channels) ? generated.channels : {};
  const existingChannel = isPlainObject(existingChannels.facebook) ? existingChannels.facebook : {};
  const contentType = cleanText(generated?.content_type || job?.content_type || "recipe") || "recipe";
  const candidates = normalizeSocialPack(
    Array.isArray(existingChannel.candidates) ? existingChannel.candidates
      : (Array.isArray(existingChannel.social_candidates) ? existingChannel.social_candidates : generated?.social_candidates),
    contentType,
  );
  const selected = normalizeSocialPack(
    Array.isArray(existingChannel.selected) ? existingChannel.selected
      : (Array.isArray(existingChannel.social_pack) ? existingChannel.social_pack : generated?.social_pack),
    contentType,
  );
  const distribution = normalizeFacebookDistribution(
    isPlainObject(existingChannel.distribution) ? existingChannel.distribution
      : (isPlainObject(existingChannel.facebook_distribution) ? existingChannel.facebook_distribution : generated?.facebook_distribution),
    contentType,
  );

  return {
    channel: "facebook",
    contract_version: cleanText(existingChannel.contract_version || "") || CHANNEL_ADAPTER_CONTRACT_VERSION,
    live: true,
    profile: isPlainObject(existingChannel.profile)
      ? { ...buildChannelProfile("facebook"), ...existingChannel.profile }
      : buildChannelProfile("facebook"),
    input_package: cleanText(existingChannel.input_package || "") || "content_package",
    candidates,
    selected,
    distribution,
    quality_summary: {
      ...(isPlainObject(existingChannel.quality_summary) ? existingChannel.quality_summary : {}),
      ...buildFacebookChannelQualitySummary({
        ...generated,
        content_type: contentType,
        social_candidates: candidates,
        social_pack: selected,
        facebook_distribution: distribution,
      }),
    },
  };
}

function resolvePinterestChannelAdapter(generated, job = null) {
  const existingChannels = isPlainObject(generated?.channels) ? generated.channels : {};
  const existingChannel = isPlainObject(existingChannels.pinterest) ? existingChannels.pinterest : {};
  const contentPackage = resolveCanonicalContentPackage(generated, job);

  return {
    channel: "pinterest",
    contract_version: cleanText(existingChannel.contract_version || "") || CHANNEL_ADAPTER_CONTRACT_VERSION,
    live: false,
    profile: isPlainObject(existingChannel.profile)
      ? { ...buildChannelProfile("pinterest"), ...existingChannel.profile }
      : buildChannelProfile("pinterest"),
    input_package: cleanText(existingChannel.input_package || "") || "content_package",
    draft: buildPinterestDraft(contentPackage, isPlainObject(existingChannel.draft) ? existingChannel.draft : {}),
    quality_summary: isPlainObject(existingChannel.quality_summary) ? existingChannel.quality_summary : {},
  };
}

function syncGeneratedContractContainers(generated, job = null) {
  const contentPackage = resolveCanonicalContentPackage(generated, job);
  const facebookChannel = resolveFacebookChannelAdapter({ ...generated, content_package: contentPackage }, job);
  const pinterestChannel = resolvePinterestChannelAdapter({ ...generated, content_package: contentPackage }, job);
  const topLevelRecipe = contentPackage.content_type === "recipe"
    ? normalizeRecipe(contentPackage.recipe || generated?.recipe || {}, contentPackage.content_type)
    : normalizeRecipe({}, contentPackage.content_type);

  return {
    ...generated,
    content_type: contentPackage.content_type,
    topic_seed: contentPackage.topic_seed,
    title: contentPackage.title,
    slug: contentPackage.slug,
    excerpt: contentPackage.excerpt,
    seo_description: contentPackage.seo_description,
    content_html: contentPackage.content_html,
    content_pages: contentPackage.content_pages,
    page_flow: contentPackage.page_flow,
    image_prompt: contentPackage.image_prompt,
    image_alt: contentPackage.image_alt,
    recipe: topLevelRecipe,
    social_candidates: facebookChannel.candidates,
    social_pack: facebookChannel.selected,
    facebook_distribution: facebookChannel.distribution,
    content_package: contentPackage,
    channels: {
      facebook: facebookChannel,
      pinterest: pinterestChannel,
    },
  };
}

function mergeValidatorSummary(generated, updates) {
  const contentMachine = isPlainObject(generated?.content_machine) ? generated.content_machine : {};
  const validatorSummary = isPlainObject(contentMachine.validator_summary) ? contentMachine.validator_summary : {};

  return syncGeneratedContractContainers({
    ...generated,
    content_machine: {
      ...contentMachine,
      validator_summary: {
        ...validatorSummary,
        ...updates,
      },
    },
  });
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
    duplicate_conflict: "A duplicate title or slug conflict blocked this article before publish.",
    missing_target_pages: "At least one Facebook page must stay attached before publish.",
    thin_content: "The generated article body was too thin for the quality gate.",
    weak_title: "The generated title was too generic to carry a strong click promise.",
    weak_excerpt: "The generated excerpt was too weak, repetitive, or slow to surface a concrete reason to click.",
    weak_seo: "The generated SEO description was too weak, repetitive, or buried the concrete click reason too late.",
    weak_title_alignment: "The opening paragraph did not cash the headline promise quickly enough with a concrete answer, problem, or payoff.",
    weak_pagination: "The generated article should be split into 2 or 3 strong pages.",
    weak_page_balance: "One generated article page was too thin to feel intentional.",
    weak_page_openings: "One generated article page opens weakly instead of feeling like a deliberate page start.",
    weak_page_flow: "The generated page map is missing a clear label or summary for one of the article pages.",
    weak_page_labels: "The generated page labels are too generic to feel like real chapter navigation.",
    repetitive_page_labels: "The generated page labels feel repetitive instead of distinct.",
    weak_page_summaries: "The generated page summaries are too thin to make the next click feel worthwhile.",
    weak_structure: "The generated article needs more H2 structure before publish.",
    missing_internal_links: "The generated article did not include enough internal links.",
    social_pack_incomplete: "The generated social pack did not cover all selected Facebook pages.",
    social_pack_repetitive: "The generated social pack was too repetitive across selected Facebook pages.",
    social_hooks_repetitive: "The generated Facebook hooks were too repetitive across selected Facebook pages.",
    social_openings_repetitive: "The generated Facebook caption openings were too repetitive across selected Facebook pages.",
    social_angles_repetitive: "The generated social pack reused too many of the same angle types across selected Facebook pages.",
    social_hook_forms_thin: "The selected Facebook pack reused too many of the same hook shapes instead of varying the sentence pattern.",
    weak_social_copy: "The generated Facebook hooks or captions were too weak for publish.",
    weak_social_lead: "The lead Facebook variant was not strong, specific, concrete, or front-loaded enough to carry the first click opportunity.",
    social_specificity_thin: "Too few selected Facebook variants felt concrete and article-specific.",
    social_anchor_thin: "Too few selected Facebook variants named a concrete dish, ingredient, mistake, method, or topic.",
    social_relatability_thin: "Too few selected Facebook variants framed a recognizable real-life kitchen moment.",
    social_recognition_thin: "Too few selected Facebook variants created a direct self-recognition moment around a repeated kitchen result or mistake.",
    social_conversation_thin: "Too few selected Facebook variants felt naturally discussable through a real household habit, shopping split, or recognizable choice.",
    social_savvy_thin: "Too few selected Facebook variants made the reader feel they were about to make a smarter kitchen or shopping move.",
    social_identity_shift_thin: "Too few selected Facebook variants made the reader feel they were leaving behind the old default move for a better one.",
    social_novelty_thin: "Too few selected Facebook variants added a concrete new detail beyond the article title.",
    social_front_load_thin: "Too few selected Facebook variants surfaced the concrete problem or payoff in the first words.",
    social_curiosity_thin: "Too few selected Facebook variants created honest curiosity with a concrete clue.",
    social_resolution_thin: "Too few selected Facebook variants resolved the hook with a concrete clue in the first caption lines.",
    social_contrast_thin: "Too few selected Facebook variants used a clean expectation-vs-reality or mistake-vs-fix contrast.",
    social_pain_points_thin: "Too few selected Facebook variants framed a clear problem, mistake, or shortcut.",
    social_payoffs_thin: "Too few selected Facebook variants framed a clear payoff or result.",
    social_proof_thin: "Too few selected Facebook variants carried a believable concrete clue or proof early.",
    social_actionability_thin: "Too few selected Facebook variants made the next move or practical use feel obvious.",
    social_immediacy_thin: "Too few selected Facebook variants made the article feel relevant to the reader's next cook, shop, order, or weeknight decision.",
    social_consequence_thin: "Too few selected Facebook variants made the cost, waste, or repeated mistake feel concrete.",
    social_habit_shift_thin: "Too few selected Facebook variants created a clear old-habit-versus-better-result shift.",
    social_focus_thin: "Too few selected Facebook variants stayed centered on one clean dominant promise.",
    social_promise_sync_thin: "Too few selected Facebook variants lined up cleanly with the article title and page-one promise without echoing the headline.",
    social_scannability_thin: "Too few selected Facebook variants stayed easy to scan in short distinct caption lines.",
    social_two_step_thin: "Too few selected Facebook variants made caption line 1 and line 2 do distinct useful jobs instead of repeating the same idea.",
    image_not_ready: "The required image slots were not ready before publish.",
  };

  const primaryCheck = String(blockingChecks[0] || "quality_gate_failed");
  const primaryMessage = messages[primaryCheck] || "The generated article package did not meet the quality gate for publish.";
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
      const socialPack = ensureSocialPackCoverage(validated.social_pack, selectedPages, validated, settings, "recipe", preferredAngle);

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
  log(
    `OpenAI request ${path} key_present=${settings.openaiApiKey ? "yes" : "no"} key_suffix=${maskSecretSuffix(settings.openaiApiKey)} base_url=${settings.openaiBaseUrl || "missing"}`,
  );

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
    articlePrompt: resolveTypedGuidance({ contentMachine }, "article", "recipe", raw.article_prompt || "Open with appetite and payoff, use useful H2 sections, and keep the recipe practical, cookable, and worth the click."),
    defaultCta: raw.default_cta || contentMachine.defaultCta || "Read the full article on the blog.",
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
  const contentType = job.content_type || "recipe";
  const articleContract = contentType === "recipe"
    ? "The JSON must contain: title, slug, excerpt, seo_description, content_pages, page_flow, image_prompt, image_alt, and recipe. content_pages must be an array of 2 or 3 clean HTML strings for WordPress using paragraphs, headings, lists, and blockquotes only. page_flow must be an array with one item per page containing label and summary. Labels must be short, specific, and non-generic. Summaries must preview the payoff of the page instead of repeating the label."
    : "The JSON must contain: title, slug, excerpt, seo_description, content_pages, page_flow, image_prompt, and image_alt. Do not include recipe-only metadata for non-recipe content. content_pages must be an array of 2 or 3 clean HTML strings for WordPress using paragraphs, headings, lists, and blockquotes only. page_flow must be an array with one item per page containing label and summary. Labels must be short, specific, and non-generic. Summaries must preview the payoff of the page instead of repeating the label.";
  let lastErrorMessage = "";
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    try {
      const content = await requestOpenAiChat(settings, [
        {
          role: "system",
          content:
            `You are the article engine for a premium food publication. Return strict JSON only with no markdown fences. Be publish-ready, specific, and useful. No filler openings, no fake personal stories, no generic SEO padding, and no schema drift. ${articleContract}`,
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
      const articleStageSummary = summarizeArticleStage(validated, job);
      if (articleStageSummary.checks.length) {
        lastErrorMessage = `Article stage quality needs repair: ${articleStageSummary.checks.join(", ")}`;
        if (attempt >= repairAttemptsAllowed) {
          return {
            article: {
              title: validated.title,
              slug: validated.slug,
              excerpt: validated.excerpt,
              seo_description: validated.seo_description,
              content_pages: Array.isArray(validated.content_pages) ? validated.content_pages : [],
              page_flow: Array.isArray(validated.page_flow) ? validated.page_flow : [],
              content_html: validated.content_html,
              image_prompt: validated.image_prompt,
              image_alt: validated.image_alt,
              recipe: validated.recipe,
            },
            validatorSummary: {
              repair_attempts: attempt,
              repaired: attempt > 0,
              last_validation_error: lastErrorMessage,
              article_stage_quality_status: "warn",
              article_stage_checks: articleStageSummary.checks,
              article_title_score: articleStageSummary.metrics.title_score,
              article_title_strong: articleStageSummary.metrics.title_strong,
              article_title_front_load_score: articleStageSummary.metrics.title_front_load_score,
              article_opening_alignment_score: articleStageSummary.metrics.opening_alignment_score,
              article_excerpt_signal_score: articleStageSummary.metrics.excerpt_signal_score,
              article_excerpt_front_load_score: articleStageSummary.metrics.excerpt_front_load_score,
              article_seo_signal_score: articleStageSummary.metrics.seo_signal_score,
              article_seo_front_load_score: articleStageSummary.metrics.seo_front_load_score,
              article_excerpt_adds_value: articleStageSummary.metrics.excerpt_adds_value,
              article_opening_adds_value: articleStageSummary.metrics.opening_adds_value,
              article_opening_front_load_score: articleStageSummary.metrics.opening_front_load_score,
            },
          };
        }

        lastValidationError = buildArticleStageRepairNote(articleStageSummary, job);
        continue;
      }

      return {
        article: {
          title: validated.title,
          slug: validated.slug,
          excerpt: validated.excerpt,
          seo_description: validated.seo_description,
          content_pages: Array.isArray(validated.content_pages) ? validated.content_pages : [],
          page_flow: Array.isArray(validated.page_flow) ? validated.page_flow : [],
          content_html: validated.content_html,
          image_prompt: validated.image_prompt,
          image_alt: validated.image_alt,
          recipe: validated.recipe,
        },
        validatorSummary: {
          repair_attempts: attempt,
          repaired: attempt > 0,
          last_validation_error: lastErrorMessage || lastValidationError,
          article_stage_quality_status: "pass",
          article_stage_checks: [],
          article_title_score: articleStageSummary.metrics.title_score,
          article_title_strong: articleStageSummary.metrics.title_strong,
          article_title_front_load_score: articleStageSummary.metrics.title_front_load_score,
          article_opening_alignment_score: articleStageSummary.metrics.opening_alignment_score,
          article_excerpt_signal_score: articleStageSummary.metrics.excerpt_signal_score,
          article_excerpt_front_load_score: articleStageSummary.metrics.excerpt_front_load_score,
          article_seo_signal_score: articleStageSummary.metrics.seo_signal_score,
          article_seo_front_load_score: articleStageSummary.metrics.seo_front_load_score,
          article_excerpt_adds_value: articleStageSummary.metrics.excerpt_adds_value,
          article_opening_adds_value: articleStageSummary.metrics.opening_adds_value,
          article_opening_front_load_score: articleStageSummary.metrics.opening_front_load_score,
        },
      };
    } catch (error) {
      lastErrorMessage = formatError(error);
      lastValidationError = buildArticleRepairNote(lastErrorMessage, job);
      if (attempt >= repairAttemptsAllowed) {
        throw error;
      }
    }
  }

  throw new Error("Core article generation failed unexpectedly.");
}

async function generateSocialCandidatePool(job, settings, article, selectedPages, preferredAngle = "") {
  const models = settings.contentMachine.models || {};
  const repairAttemptsAllowed = models.repair_enabled ? Math.max(0, Math.min(1, Number(models.repair_attempts || 0))) : 0;
  const desiredCount = Math.max(1, Array.isArray(selectedPages) ? selectedPages.length : 0);
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    const content = await requestOpenAiChat(settings, [
      {
        role: "system",
        content:
          "You are the Facebook creative engine for a premium food publication. Return strict JSON only with no markdown fences. The JSON must contain social_candidates, an array of candidate variants. Each variant must contain angle_key, hook, caption, cta_hint, and post_message. Non-negotiables: no links, no hashtags, no pagination mentions, no title-echo hooks, no hook repeated as the first caption line, and no empty hype. Make the copy specific, honest, and scroll-stopping without sounding fake.",
      },
      {
        role: "user",
        content: buildSocialCandidatePrompt(job, settings, article, selectedPages, preferredAngle, lastValidationError),
      },
    ]);

    if (!content || typeof content !== "string") {
      throw new Error("OpenAI did not return social candidate content.");
    }

    const parsed = parseJsonObject(content);
    const normalized = normalizeSocialPack(parsed.social_candidates || parsed.socialCandidates || [], job.content_type || "recipe");
    const poolSummary = summarizeSocialCandidatePool(normalized, article, desiredCount, job.content_type || "recipe");
    if (!poolSummary.issues.length || attempt >= repairAttemptsAllowed) {
      return {
        candidates: normalized,
        validatorSummary: {
          social_repair_attempts: attempt,
          social_repaired: attempt > 0,
          last_social_validation_error: poolSummary.issues.length ? poolSummary.issues.join(" ") : lastValidationError,
          social_pool_quality_status: poolSummary.issues.length ? "warn" : "pass",
          social_pool_size: poolSummary.metrics.pool_size,
          strong_social_candidates: poolSummary.metrics.strong_candidates,
          specific_social_candidates: poolSummary.metrics.specific_candidates,
          conversation_social_candidates: poolSummary.metrics.conversation_candidates,
          scannable_social_candidates: poolSummary.metrics.scannable_candidates,
          anchored_social_candidates: poolSummary.metrics.anchor_candidates,
          relatable_social_candidates: poolSummary.metrics.relatable_candidates,
          recognition_social_candidates: poolSummary.metrics.recognition_candidates,
          proof_social_candidates: poolSummary.metrics.proof_candidates,
          actionable_social_candidates: poolSummary.metrics.actionable_candidates,
          immediacy_social_candidates: poolSummary.metrics.immediacy_candidates,
          consequence_social_candidates: poolSummary.metrics.consequence_candidates,
          habit_shift_social_candidates: poolSummary.metrics.habit_shift_candidates,
          focused_social_candidates: poolSummary.metrics.focused_candidates,
          promise_sync_candidates: poolSummary.metrics.promise_sync_candidates,
          two_step_social_candidates: poolSummary.metrics.two_step_candidates,
          novelty_social_candidates: poolSummary.metrics.novelty_candidates,
          front_loaded_social_candidates: poolSummary.metrics.front_loaded_candidates,
          curiosity_social_candidates: poolSummary.metrics.curiosity_candidates,
          resolution_social_candidates: poolSummary.metrics.resolution_candidates,
          contrast_social_candidates: poolSummary.metrics.contrast_candidates,
          pain_point_social_candidates: poolSummary.metrics.pain_point_candidates,
          payoff_social_candidates: poolSummary.metrics.payoff_candidates,
          high_scoring_social_candidates: poolSummary.metrics.high_scoring_candidates,
        },
      };
    }

    lastValidationError = buildSocialPoolRepairNote(poolSummary, desiredCount, job.content_type || "recipe");
  }

  throw new Error("Social candidate generation failed unexpectedly.");
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

  content = await requestOpenAiResponses(settings, fallbackMessages);
  return content;
}

async function requestOpenAiResponses(settings, messages) {
  const payload = await openAiJsonRequest(settings, "/responses", {
    model: settings.openaiModel,
    input: messages.map((message) => ({
      role: message.role === "system" ? "developer" : message.role,
      content: [
        {
          type: "input_text",
          text: String(message.content || ""),
        },
      ],
    })),
    text: {
      format: {
        type: "json_object",
      },
    },
  });

  return extractOpenAiResponseText(payload);
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
      role: cleanMultilineText(
        publicationProfile.role ||
          raw.publication_role ||
          "You are the lead editorial writer for Kuchnia Twist, producing recipe articles and food explainers that are sharp enough to win the click and useful enough to justify it.",
      ),
      voice_brief: cleanText(publicationProfile.voice_brief || raw.brand_voice || config.fallbackBrandVoice),
      guardrails: cleanMultilineText(
        publicationProfile.guardrails ||
          raw.global_guardrails ||
          [publicationProfile.do_guidance, publicationProfile.dont_guidance, publicationProfile.banned_claims]
            .filter(Boolean)
            .join("\n") ||
          "No fake personal stories, invented reporting, fabricated authority, or made-up facts. No filler SEO intros, generic throat-clearing, or padded explanations. No spammy clickbait, fake cliffhangers, or hollow viral language. No medical or nutrition claims beyond ordinary kitchen guidance. Keep paragraphs short, specific, and human. Avoid generic openers like 'when it comes to' or 'in today's busy world.'",
      ),
    },
    contentPresets: {
      recipe: normalizePreset(contentPresets.recipe, "Create dependable, craveable, and realistic home-cooking recipes with believable timings, coherent ingredient amounts, repeatable results, and enough practical detail to justify the click.", 1200),
      food_fact: normalizePreset(contentPresets.food_fact, "Treat the entered title as a working topic, answer it directly, correct confusion, and finish with a practical takeaway worth the click.", 1100),
      food_story: normalizePreset(contentPresets.food_story, "Write a publication-voice kitchen essay with a clear observation and a reflective close.", 1100),
    },
    channelPresets: {
      recipe_master: {
        guidance: cleanMultilineText(
          channelPresets.recipe_master?.guidance ||
            raw.recipe_master_prompt ||
            "Turn a dish name into a publishable multi-page recipe article package with a strong title, excerpt, SEO description, recipe-card readiness, and image direction."
        ),
      },
      article: {
        recipe: {
          guidance: cleanMultilineText(channelPresets.article?.recipe?.guidance || channelPresets.article?.guidance || raw.article_prompt || "Open with appetite and concrete payoff, build 2 to 3 intentional pages, and keep the recipe practical, credible, and worth the click."),
        },
        food_fact: {
          guidance: cleanMultilineText(channelPresets.article?.food_fact?.guidance || raw.food_fact_article_prompt || "Treat the entered title as a working topic, answer it fast, explain what people get wrong, and land a practical takeaway without drifting into recipe structure."),
        },
        food_story: {
          guidance: cleanMultilineText(channelPresets.article?.food_story?.guidance || raw.food_story_article_prompt || raw.food_fact_article_prompt || "Lead with one clear observation, give it kitchen meaning, and close with reflection."),
        },
      },
      facebook_caption: {
        recipe: {
          guidance: cleanMultilineText(channelPresets.facebook_caption?.recipe?.guidance || channelPresets.facebook_caption?.guidance || raw.facebook_caption_guidance || "Generate a strong pool of recipe Facebook candidates with short hooks, 2 to 5 short caption lines, distinct angles, no title echo, no repeated hook-as-caption opener, and no links or hashtags."),
        },
        food_fact: {
          guidance: cleanMultilineText(channelPresets.facebook_caption?.food_fact?.guidance || raw.food_fact_facebook_caption_guidance || "Generate a strong pool of food-fact Facebook candidates with myth-busting, surprising-truth, or kitchen-mistake angles. No title echo, no links, no hashtags, and no empty hype."),
        },
        food_story: {
          guidance: cleanMultilineText(channelPresets.facebook_caption?.food_story?.guidance || raw.food_fact_facebook_caption_guidance || "Generate distinct editorial Facebook hooks and captions with curiosity, warmth, and no empty hype."),
        },
      },
      image: {
        guidance: cleanMultilineText(channelPresets.image?.guidance || raw.image_style || "Use realistic, appetizing food photography with natural light, clean composition, believable texture, and no text overlays. For recipes, bias toward finished-dish hero imagery. For food explainers, use the most useful food subject for the article."),
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
    defaultCta: cleanText(provided.default_cta || raw.default_cta || "Read the full article on the blog."),
  };
}

function resolveTypedGuidance(settings, channel, contentType, fallback = "") {
  const presets = settings.contentMachine.channelPresets || {};
  const channelPreset = presets[channel];

  if (isPlainObject(channelPreset)) {
    if (typeof channelPreset.guidance === "string" && cleanMultilineText(channelPreset.guidance)) {
      return cleanMultilineText(channelPreset.guidance);
    }

    const typed = channelPreset[contentType];
    if (isPlainObject(typed) && typeof typed.guidance === "string" && cleanMultilineText(typed.guidance)) {
      return cleanMultilineText(typed.guidance);
    }
  }

  return cleanMultilineText(fallback);
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

function angleDefinitionsForType(contentType) {
  return SOCIAL_ANGLE_LIBRARY[contentType] || SOCIAL_ANGLE_LIBRARY.recipe;
}

function normalizeAngleKey(value, contentType = "") {
  const key = cleanText(value || "").replace(/\s+/g, "_").toLowerCase();
  const definitions = contentType ? angleDefinitionsForType(contentType) : Object.values(SOCIAL_ANGLE_LIBRARY).flat();
  return definitions.some((angle) => angle.key === key) ? key : "";
}

function resolvePreferredAngle(job) {
  return normalizeAngleKey(job?.request_payload?.preferred_angle || "", job?.content_type || "recipe");
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

function buildAngleSequence(count, contentType = "recipe", preferredAngle = "") {
  const normalizedPreferred = normalizeAngleKey(preferredAngle, contentType);
  const keys = angleDefinitionsForType(contentType).map((angle) => angle.key);
  const ordered = normalizedPreferred
    ? [normalizedPreferred, ...keys.filter((key) => key !== normalizedPreferred)]
    : [...keys];

  return Array.from({ length: Math.max(1, count) }, (_, index) => ordered[index % ordered.length]);
}

function buildPageAnglePlan(pages, contentType = "recipe", preferredAngle = "") {
  const count = Math.max(1, Array.isArray(pages) ? pages.length : 0);
  const angles = buildAngleSequence(count, contentType, preferredAngle);
  const definitions = angleDefinitionsForType(contentType);

  return Array.from({ length: count }, (_, index) => {
    const angleKey = angles[index] || definitions[index % definitions.length].key;
    const angle = angleDefinition(angleKey, contentType);
    const page = Array.isArray(pages) ? pages[index] || null : null;

    return {
      index,
      angle_key: angleKey,
      page_label: cleanText(page?.label || `Page ${index + 1}`),
      instruction: angle?.instruction || "",
    };
  });
}

function angleDefinition(angleKey, contentType = "") {
  const normalized = normalizeAngleKey(angleKey, contentType);
  const definitions = contentType ? angleDefinitionsForType(contentType) : Object.values(SOCIAL_ANGLE_LIBRARY).flat();
  return definitions.find((angle) => angle.key === normalized) || null;
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

function trimWords(value, maxWords) {
  const words = cleanText(value).split(/\s+/).filter(Boolean);
  if (!words.length || words.length <= maxWords) {
    return words.join(" ");
  }

  return words.slice(0, maxWords).join(" ");
}

function sentenceCase(value) {
  const text = cleanText(value);
  if (!text) {
    return "";
  }

  return text.charAt(0).toUpperCase() + text.slice(1);
}

function firstSentence(value, maxLength = 160) {
  const text = cleanText(value);
  if (!text) {
    return "";
  }

  const sentence = text.split(/(?<=[.!?])\s+/)[0] || text;
  return trimText(sentence, maxLength);
}

function titleLooksStrong(title, topic = "", contentType = "recipe") {
  const text = cleanText(title || "");
  const wordCount = countWords(text);
  if (!text || wordCount < 4 || wordCount > 14) {
    return false;
  }
  if (/\b(you won'?t believe|best ever|game changer|what you need to know|everything you need to know|why everyone is talking about)\b/i.test(text)) {
    return false;
  }
  if (topic && sharedWordsRatio(text, topic) < 0.15) {
    return false;
  }
  if (frontLoadedClickSignalScore(text, contentType) < 0) {
    return false;
  }

  return true;
}

function excerptAddsNewValue(title, excerpt) {
  const text = cleanText(excerpt || "");
  if (countWords(text) < 12) {
    return false;
  }

  return sharedWordsRatio(text, title) < 0.82;
}

function openingParagraphAddsNewValue(contentHtml, title, excerpt = "") {
  const opening = cleanText(String((String(contentHtml || "").match(/<p\b[^>]*>(.*?)<\/p>/i)?.[1] || "")).replace(/<[^>]+>/g, " "));
  if (countWords(opening) < 16) {
    return false;
  }
  if (sharedWordsRatio(opening, title) >= 0.85) {
    return false;
  }
  if (excerpt && sharedWordsRatio(opening, excerpt) >= 0.9) {
    return false;
  }

  return true;
}

function joinNaturalList(items) {
  const cleanItems = ensureStringArray(items);
  if (!cleanItems.length) {
    return "";
  }
  if (cleanItems.length === 1) {
    return cleanItems[0];
  }
  if (cleanItems.length === 2) {
    return `${cleanItems[0]} and ${cleanItems[1]}`;
  }

  return `${cleanItems.slice(0, -1).join(", ")}, and ${cleanItems[cleanItems.length - 1]}`;
}

function extractArticleHeadings(article, limit = 5) {
  const html = Array.isArray(article?.content_pages) && article.content_pages.length
    ? article.content_pages.join("\n")
    : String(article?.content_html || "");

  return Array.from(html.matchAll(/<h2\b[^>]*>(.*?)<\/h2>/gi))
    .map((match) => cleanText(String(match[1] || "").replace(/<[^>]+>/g, " ")))
    .filter(Boolean)
    .slice(0, limit);
}

function extractArticlePlainText(article, maxLength = 900) {
  const html = Array.isArray(article?.content_pages) && article.content_pages.length
    ? article.content_pages.join(" ")
    : String(article?.content_html || "");

  return trimText(cleanText(html.replace(/<[^>]+>/g, " ")), maxLength);
}

function cleanHeadingTopic(value) {
  return trimText(
    cleanText(
      String(value || "")
        .replace(/^[0-9]+\s*[:.)-]?\s*/, "")
        .replace(/[.!?]+$/g, ""),
    ),
    90,
  );
}

function buildSocialHookTopic(title, contentType = "recipe") {
  const source = contentType === "recipe"
    ? cleanText(title)
    : cleanText(String(title || "").replace(/^\d+\s+/, ""));

  return trimWords(cleanHeadingTopic(source) || cleanText(title), contentType === "recipe" ? 8 : 7);
}

function extractOpeningParagraphText(article) {
  const html = Array.isArray(article?.content_pages) && article.content_pages.length
    ? String(article.content_pages[0] || "")
    : String(article?.content_html || "");
  const match = html.match(/<p\b[^>]*>(.*?)<\/p>/i);
  return cleanText(String(match?.[1] || html).replace(/<[^>]+>/g, " "));
}

function frontLoadedClickSignalScore(text, contentType = "recipe") {
  const lead = trimWords(cleanText(text || ""), 5).toLowerCase();
  if (!lead) {
    return 0;
  }

  let score = 0;
  if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|truth|myth|actually|really|better|crispy|creamy|budget|weeknight|juicy|quick|simple|get wrong|most people)\b/i.test(lead)) {
    score += 2;
  }
  if (contentType === "recipe" && /\b(one-pan|sheet pan|air fryer|skillet|cheesy|garlicky|comfort|dinner|takeout)\b/i.test(lead)) {
    score += 1;
  }
  if (contentType === "food_fact" && /\b(why|how|what|truth|myth|mistake|actually)\b/i.test(lead)) {
    score += 1;
  }
  if (/\b\d+\b/.test(lead)) {
    score += 1;
  }
  if (/^(you need to|you should|this is|this one|these are|here'?s why|the best)\b/i.test(lead)) {
    score -= 2;
  }

  return score;
}

function contrastClickSignalScore(text) {
  const normalized = cleanText(text || "").toLowerCase();
  if (!normalized) {
    return 0;
  }

  return /\b(instead of|rather than|not just|not the|more than|less about|what most people miss|what changes|vs\.?|versus)\b/i.test(normalized)
    ? 1
    : 0;
}

function headlineSpecificityScore(title, contentType = "recipe", topic = "") {
  const text = cleanText(title || "");
  const normalizedTitle = normalizeSlug(text);
  const normalizedTopic = normalizeSlug(topic || "");
  const words = countWords(text);
  let score = 0;

  if (!text) {
    return 0;
  }
  if (words >= 5 && words <= 13) {
    score += 3;
  } else if (words >= 4 && words <= 16) {
    score += 1;
  } else {
    score -= 2;
  }

  if (normalizedTopic && normalizedTitle && normalizedTitle !== normalizedTopic) {
    score += 2;
  }

  if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|why|how|actually|really|most people|get wrong)\b/i.test(text)) {
    score += 3;
  }
  if (/\b(one-pan|weeknight|crispy|creamy|cheesy|garlicky|juicy|budget|air fryer|oven|skillet|better than takeout)\b/i.test(text)) {
    score += 2;
  }
  if (/\b\d+\b/.test(text)) {
    score += 1;
  }
  score += frontLoadedClickSignalScore(text, contentType);
  score += contrastClickSignalScore(text);
  if (/[?]/.test(text)) {
    score -= 1;
  }
  if (/\b(recipe|guide|tips|ideas|facts|article)\b/i.test(text) && words <= 6) {
    score -= 2;
  }
  if (contentType === "food_fact" && normalizedTopic && normalizedTitle === normalizedTopic) {
    score -= 2;
  }

  return score;
}

function openingPromiseAlignmentScore(title, openingParagraph) {
  const titleText = cleanText(title || "");
  const openingText = cleanText(openingParagraph || "");
  if (!titleText || !openingText) {
    return 0;
  }

  const overlap = sharedWordsRatio(titleText, openingText);
  let score = 0;
  if (overlap >= 0.24) {
    score += 3;
  } else if (overlap >= 0.14) {
    score += 2;
  } else if (overlap >= 0.08) {
    score += 1;
  }
  if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|payoff|problem|why|how)\b/i.test(openingText)) {
    score += 1;
  }
  if (frontLoadedClickSignalScore(openingText) > 0) {
    score += 1;
  }
  score += contrastClickSignalScore(openingText);

  return score;
}

function excerptClickSignalScore(excerpt, title = "", openingParagraph = "") {
  const text = cleanText(excerpt || "");
  const words = countWords(text);
  const titleOverlap = sharedWordsRatio(text, title);
  const openingOverlap = openingParagraph ? sharedWordsRatio(text, openingParagraph) : 0;
  let score = 0;

  if (!text) {
    return 0;
  }
  if (words >= 12 && words <= 30) {
    score += 2;
  } else if (words >= 10 && words <= 36) {
    score += 1;
  }
  if (titleOverlap <= 0.72) {
    score += 2;
  } else if (titleOverlap >= 0.9) {
    score -= 2;
  }
  if (openingParagraph && openingOverlap >= 0.08 && openingOverlap <= 0.7) {
    score += 1;
  }
  if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i.test(text)) {
    score += 2;
  }
  if (frontLoadedClickSignalScore(text) > 0) {
    score += 1;
  }
  score += contrastClickSignalScore(text);

  return score;
}

function seoDescriptionSignalScore(seoDescription, title = "", excerpt = "") {
  const text = cleanText(seoDescription || "");
  const words = countWords(text);
  const titleOverlap = sharedWordsRatio(text, title);
  const excerptOverlap = excerpt ? sharedWordsRatio(text, excerpt) : 0;
  let score = 0;

  if (!text) {
    return 0;
  }
  if (words >= 12 && words <= 28) {
    score += 2;
  } else if (words >= 10 && words <= 32) {
    score += 1;
  }
  if (titleOverlap <= 0.72) {
    score += 2;
  } else if (titleOverlap >= 0.9) {
    score -= 2;
  }
  if (excerpt && excerptOverlap >= 0.08 && excerptOverlap <= 0.8) {
    score += 1;
  }
  if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i.test(text)) {
    score += 2;
  }
  if (frontLoadedClickSignalScore(text) > 0) {
    score += 1;
  }
  score += contrastClickSignalScore(text);

  return score;
}

function findSpecificArticleHeading(headings, contentType = "recipe") {
  const genericPatterns = contentType === "recipe"
    ? [
        /^why this recipe works$/i,
        /^what you'?ll need$/i,
        /^ingredient notes?$/i,
        /^ingredients?$/i,
        /^instructions?$/i,
        /^how to make\b/i,
        /^serving(?: and storage)?$/i,
        /^storage(?: and reheating)?$/i,
        /^recipe notes?$/i,
        /^tips?$/i,
      ]
    : [
        /^why it matters$/i,
        /^the takeaway$/i,
        /^bottom line$/i,
        /^what this means$/i,
        /^final thoughts?$/i,
      ];

  return ensureStringArray(headings).find((heading) => !genericPatterns.some((pattern) => pattern.test(heading))) || ensureStringArray(headings)[0] || "";
}

function extractRecipeIngredientFocus(ingredients, limit = 3) {
  const ignoreTokens = new Set([
    "cup", "cups", "tablespoon", "tablespoons", "tbsp", "teaspoon", "teaspoons", "tsp",
    "gram", "grams", "g", "kg", "kilogram", "kilograms", "ml", "l", "oz", "ounce", "ounces",
    "lb", "lbs", "pound", "pounds", "pinch", "dash", "can", "cans", "package", "packages",
    "clove", "cloves", "slice", "slices", "small", "medium", "large", "extra", "freshly",
    "ground", "optional", "divided", "plus", "more", "less", "to", "taste", "boneless",
    "skinless", "chopped", "diced", "minced", "shredded", "grated",
  ]);
  const lowValuePhrases = new Set(["salt", "black pepper", "pepper", "water"]);
  const focus = [];
  const seen = new Set();

  for (const ingredient of ensureStringArray(ingredients)) {
    const cleaned = cleanText(
      ingredient
        .toLowerCase()
        .replace(/\([^)]*\)/g, " ")
        .replace(/^[0-9\/.,\-\s]+/, " ")
        .replace(/[^a-z0-9\s]/g, " "),
    );
    const tokens = cleaned
      .split(/\s+/)
      .map((token) => token.trim())
      .filter((token) => token && !ignoreTokens.has(token) && !/^\d/.test(token));
    const phrase = tokens.slice(0, 2).join(" ").trim();

    if (!phrase || lowValuePhrases.has(phrase) || seen.has(phrase)) {
      continue;
    }

    seen.add(phrase);
    focus.push(phrase);

    if (focus.length >= limit) {
      break;
    }
  }

  return joinNaturalList(focus);
}

function resolveArticlePageFlow(article, contentType = "recipe") {
  const contentPages = Array.isArray(article?.content_pages) && article.content_pages.length
    ? article.content_pages
    : splitHtmlIntoPages(String(article?.content_html || ""), contentType).slice(0, 3);

  return normalizeGeneratedPageFlow(Array.isArray(article?.page_flow) ? article.page_flow : [], contentPages);
}

function buildArticleSocialSignals(article, contentType = "recipe") {
  const title = cleanText(article?.title || "");
  const headings = extractArticleHeadings(article, 5);
  const headingTopic = cleanHeadingTopic(findSpecificArticleHeading(headings, contentType));
  const pageFlow = resolveArticlePageFlow(article, contentType);
  const excerptSentence = firstSentence(article?.excerpt || "", 150);
  const bodySentence = firstSentence(extractArticlePlainText(article, 500), 150);
  const summaryLine = [excerptSentence, bodySentence].find((line) => line && sharedWordsRatio(line, title) < 0.85) || excerptSentence || bodySentence || "";
  const pageSignalLine = trimText(
    pageFlow
      .map((page) => cleanText([page?.label || "", page?.summary || ""].filter(Boolean).join(". ")))
      .find((line) => line && sharedWordsRatio(line, title) < 0.85 && sharedWordsRatio(line, summaryLine || title) < 0.9) || "",
    150,
  );
  const finalPage = pageFlow.length ? pageFlow[pageFlow.length - 1] : null;
  const finalRewardLine = trimText(cleanText([finalPage?.label || "", finalPage?.summary || ""].filter(Boolean).join(". ")), 150);
  const recipe = isPlainObject(article?.recipe) ? article.recipe : {};
  const ingredientFocus = contentType === "recipe" ? extractRecipeIngredientFocus(recipe.ingredients || [], 3) : "";
  const metaBits = [];

  if (contentType === "recipe") {
    if (cleanText(recipe.total_time || "")) {
      metaBits.push(`${cleanText(recipe.total_time)} total`);
    }
    if (cleanText(recipe.yield || "")) {
      metaBits.push(`makes ${cleanText(recipe.yield)}`);
    }
  }

  const metaLine = metaBits.length ? sentenceCase(metaBits.join(" and ")) : "";
  const proofLine = trimText(
    contentType === "recipe"
      ? (
          ingredientFocus
            ? `The payoff leans on ${ingredientFocus} without turning dinner into a project.`
            : (metaLine
                ? `${metaLine} with a method that stays clear and repeatable.`
                : (headingTopic
                    ? `It leans into ${headingTopic} without overcomplicating dinner.`
                    : "It keeps the payoff high without making the method drag."))
        )
      : (
          headingTopic
            ? `It gets specific about ${headingTopic} instead of staying vague.`
            : "It moves past the fuzzy version and gives the useful kitchen answer."
        ),
    150,
  );
  const detailLine = trimText(
    contentType === "recipe"
      ? (
          pageSignalLine && sharedWordsRatio(pageSignalLine, summaryLine || title) < 0.9 && sharedWordsRatio(pageSignalLine, proofLine || title) < 0.9
            ? pageSignalLine
            : (
              headingTopic && !proofLine.toLowerCase().includes(headingTopic.toLowerCase())
            ? `It also leans into ${headingTopic} so the article earns the click.`
            : (metaLine && !proofLine.toLowerCase().includes(metaLine.toLowerCase())
                ? `${metaLine} without padding the method.`
                : (finalRewardLine && sharedWordsRatio(finalRewardLine, proofLine || title) < 0.9 ? finalRewardLine : ""))
            )
        )
      : (
          pageSignalLine && sharedWordsRatio(pageSignalLine, summaryLine || title) < 0.9
            ? pageSignalLine
            : (
              bodySentence && bodySentence !== summaryLine && sharedWordsRatio(bodySentence, title) < 0.9
            ? bodySentence
            : (headingTopic ? `The useful part is what ${headingTopic} changes in a real kitchen.` : (finalRewardLine || ""))
            )
        ),
    150,
  );
  const painLine = trimText(
    contentType === "recipe"
      ? (
          headingTopic
            ? `It solves the usual ${headingTopic.toLowerCase()} problem without turning dinner into a project.`
            : (metaLine
                ? `It solves the \"too much effort for a satisfying meal\" problem while staying practical.`
                : "It solves the \"what can I make that still feels worth it\" problem without making the method drag."))
      : (
          headingTopic
            ? `It fixes what most people get wrong about ${headingTopic}.`
            : "It clears up a kitchen belief that wastes time, creates confusion, or leads to worse results."
        ),
    150,
  );
  const consequenceLine = trimText(
    contentType === "recipe"
      ? (
          headingTopic
            ? `Miss that ${headingTopic.toLowerCase()} detail and dinner starts feeling like more effort for less payoff.`
            : (ingredientFocus
                ? `Miss the ${ingredientFocus} detail and the result starts feeling less worth the effort.`
                : "Miss the useful detail and dinner slips back into more effort, less payoff, or another flat repeat."))
      : (
          headingTopic
            ? `Miss that ${headingTopic.toLowerCase()} detail and the same fuzzy kitchen decision keeps repeating.`
            : "Miss the useful detail and the same bad kitchen assumption keeps costing time, clarity, or better results."
        ),
    150,
  );
  const payoffLine = trimText(
    finalRewardLine || detailLine || proofLine || summaryLine,
    150,
  );

  return {
    hook_topic: buildSocialHookTopic(title, contentType) || title,
    heading_topic: headingTopic,
    ingredient_focus: ingredientFocus,
    meta_line: metaLine,
    summary_line: trimText(summaryLine, 150),
    pain_line: painLine,
    consequence_line: consequenceLine,
    payoff_line: payoffLine,
    proof_line: proofLine,
    detail_line: detailLine,
    page_signal_line: pageSignalLine,
    final_reward_line: finalRewardLine,
    page_flow_text: cleanText(pageFlow.map((page) => [page?.label || "", page?.summary || ""].filter(Boolean).join(". ")).join(" ")),
    context_text: cleanText([summaryLine, painLine, consequenceLine, payoffLine, proofLine, detailLine, pageSignalLine, finalRewardLine, headingTopic, ingredientFocus, metaLine].join(" ")),
  };
}

function buildFallbackCaption(primaryLine, secondaryLine, tertiaryLine, closer) {
  const lines = [];
  const seen = new Set();

  for (const line of [primaryLine, secondaryLine, tertiaryLine]) {
    const cleaned = cleanText(line);
    const fingerprint = normalizeSocialLineFingerprint(cleaned);
    if (!cleaned || !fingerprint || seen.has(fingerprint)) {
      continue;
    }
    seen.add(fingerprint);
    lines.push(cleaned);
    if (lines.length >= 2) {
      break;
    }
  }

  lines.push(cleanText(closer));

  return cleanMultilineText(lines.filter(Boolean).join("\n"));
}

function pageStartsWithExpectedLead(pageHtml, index) {
  const page = String(pageHtml || "").trim();
  if (!page) {
    return false;
  }

  if (index === 0) {
    return /^<p\b/i.test(page);
  }

  return /^<(h2|blockquote|ul|ol)\b/i.test(page);
}

function socialVariantLooksWeak(variant, articleTitle = "", contentType = "recipe", articleSignals = null) {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const hookWords = countWords(hook);
  const captionWords = countWords(caption);
  const captionLines = countLines(caption);
  const normalizedHook = normalizeSlug(hook);
  const normalizedTitle = normalizeSlug(articleTitle);
  const hookFrontLoadScore = frontLoadedClickSignalScore(hook, contentType);
  const unanchoredPronounLead = /^(it|this|that|these|they)\b/i.test(hook) && articleSignals && !socialVariantAnchorSignal(variant, articleSignals);
  const superiorityBait = /\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you're doing|amateurs?|rookie move|lazy cooks)\b/i.test(`${hook} ${caption}`);

  return (
    !hook ||
    hookWords < 4 ||
    hookWords > 18 ||
    !caption ||
    captionWords < 14 ||
    captionWords > 85 ||
    captionLines < 2 ||
    captionLines > 5 ||
    hookFrontLoadScore < 0 ||
    unanchoredPronounLead ||
    superiorityBait ||
    containsCheapSuspensePattern(hook) ||
    (normalizedTitle !== "" && normalizedHook === normalizedTitle) ||
    /(https?:\/\/|www\.)/i.test(caption) ||
    /(^|\s)#[a-z0-9_]+/i.test(caption)
  );
}

function sharedWordsRatio(left, right) {
  const stopWords = new Set(["the", "a", "an", "and", "or", "for", "with", "your", "this", "that", "from", "into", "about", "what", "when", "why", "how", "most", "more", "than"]);
  const tokenize = (value) =>
    cleanText(value)
      .toLowerCase()
      .split(/[^a-z0-9]+/)
      .map((token) => token.trim())
      .filter((token) => token && token.length > 2 && !stopWords.has(token));

  const leftTokens = Array.from(new Set(tokenize(left)));
  const rightTokens = new Set(tokenize(right));
  if (!leftTokens.length || !rightTokens.size) {
    return 0;
  }

  const shared = leftTokens.filter((token) => rightTokens.has(token)).length;
  return shared / Math.max(1, leftTokens.length);
}

function containsCheapSuspensePattern(text) {
  return /\b(what happens next|nobody tells you|no one tells you|what they don't tell you|the secret(?: to)?|finally revealed|you(?:'ll| will) never guess|hidden truth)\b/i.test(cleanText(text || ""));
}

function socialVariantGenericPenalty(variant) {
  const hook = cleanText(variant?.hook || "").toLowerCase();
  const caption = cleanMultilineText(variant?.caption || "").toLowerCase();
  const genericPatterns = [
    /\byou need to try\b/,
    /\byou should\b/,
    /\bmust try\b/,
    /\bthis is\b/,
    /\bthis one\b/,
    /\bthese are\b/,
    /\bhere'?s why\b/,
    /\bso good\b/,
    /\bbest ever\b/,
    /\byou won't believe\b/,
    /\bi'm obsessed\b/,
    /\bgame changer\b/,
    /\breal cooks\b/,
    /\bgood cooks know\b/,
    /\bsmart cooks\b/,
    /\bserious cooks\b/,
    /\bpeople who know better\b/,
    /\bif you know what you're doing\b/,
    /\bamateurs?\b/,
    /\brookie move\b/,
    /\blazy cooks\b/,
    /\bthis one is everything\b/,
    /\btotal winner\b/,
    /\bwhat happens next\b/,
    /\bnobody tells you\b/,
    /\bno one tells you\b/,
    /\bwhat they don't tell you\b/,
    /\bthe secret(?: to)?\b/,
    /\bfinally revealed\b/,
    /\byou(?:'ll| will) never guess\b/,
    /\bhidden truth\b/,
  ];

  let penalty = 0;
  for (const pattern of genericPatterns) {
    if (pattern.test(hook)) {
      penalty += 6;
    }
    if (pattern.test(caption)) {
      penalty += 4;
    }
  }

  return penalty;
}

function classifySocialHookForm(variant) {
  const hook = cleanText(variant?.hook || "").toLowerCase();
  if (!hook) {
    return "";
  }
  if (/^\d+\b/.test(hook)) {
    return "numbered";
  }
  if (/[?]/.test(hook) || /^(why|how|what|when|which)\b/.test(hook)) {
    return "question";
  }
  if (/\b(instead of|rather than|not just|not the|what most people|get wrong|vs\.?|versus)\b/.test(hook)) {
    return "contrast";
  }
  if (/^(stop|avoid|fix|skip|quit|never)\b/.test(hook) || /\b(mistake|wrong|avoid|fix)\b/.test(hook)) {
    return "correction";
  }
  if (/^(save|make|keep|use|try|cook|shop)\b/.test(hook)) {
    return "directive";
  }
  if (/\b(faster|easier|better|crispy|creamy|juicy|budget|weeknight|shortcut|payoff|result)\b/.test(hook)) {
    return "payoff";
  }
  if (/\b(problem|waste|stuck|mistake|harder|overpay|dry|soggy|flat)\b/.test(hook)) {
    return "problem";
  }
  return "statement";
}

function socialVariantNoveltyScore(variant, articleTitle = "", articleSignals = {}) {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  if (!combined) {
    return 0;
  }

  const titleOverlap = articleTitle ? sharedWordsRatio(combined, articleTitle) : 0;
  const noveltyTargets = [
    articleSignals.detail_line,
    articleSignals.proof_line,
    articleSignals.page_signal_line,
    articleSignals.final_reward_line,
  ].filter(Boolean);
  let bestOverlap = 0;

  for (const target of noveltyTargets) {
    bestOverlap = Math.max(bestOverlap, sharedWordsRatio(combined, target));
  }

  let score = 0;
  if (bestOverlap >= 0.18) {
    score += 2;
  } else if (bestOverlap >= 0.1) {
    score += 1;
  }
  if (titleOverlap > 0 && titleOverlap <= 0.58 && bestOverlap >= 0.08) {
    score += 1;
  }
  if (articleSignals?.page_signal_line && sharedWordsRatio(combined, articleSignals.page_signal_line) >= 0.12) {
    score += 1;
  }
  if (containsCheapSuspensePattern(hook) || containsCheapSuspensePattern(caption)) {
    score -= 1;
  }

  return Math.max(0, score);
}

function buildArticleAnchorPhrases(articleSignals = {}) {
  const rawPhrases = [
    articleSignals.hook_topic,
    articleSignals.heading_topic,
    articleSignals.ingredient_focus,
    articleSignals.detail_line,
    articleSignals.page_signal_line,
  ]
    .filter(Boolean)
    .flatMap((value) =>
      cleanText(value)
        .split(/\s*(?:,| and | with | without | or )\s*/i)
        .map((part) => trimText(cleanText(part), 60))
        .filter(Boolean),
    );
  const seen = new Set();

  return rawPhrases.filter((phrase) => {
    const fingerprint = normalizeSlug(phrase);
    if (!fingerprint || seen.has(fingerprint) || countWords(phrase) < 1) {
      return false;
    }
    seen.add(fingerprint);
    return true;
  });
}

function socialVariantAnchorSignal(variant, articleSignals = {}) {
  const combined = cleanText(`${variant?.hook || ""} ${variant?.caption || ""}`);
  if (!combined) {
    return false;
  }

  return buildArticleAnchorPhrases(articleSignals).some((target) => {
    const overlap = sharedWordsRatio(combined, target);
    return overlap >= 0.18 || (countWords(target) <= 2 && overlap >= 0.12);
  });
}

function socialVariantSpecificityScore(variant, articleSignals = {}) {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = `${hook} ${caption}`.trim();
  const scoringTargets = [
    articleSignals.summary_line,
    articleSignals.pain_line,
    articleSignals.payoff_line,
    articleSignals.proof_line,
    articleSignals.detail_line,
    articleSignals.page_signal_line,
    articleSignals.final_reward_line,
  ].filter(Boolean);
  const focusTargets = [
    articleSignals.heading_topic,
    articleSignals.ingredient_focus,
    articleSignals.meta_line,
  ].filter(Boolean);
  let score = 0;

  if (/\b\d+\b/.test(hook)) {
    score += 1;
  }
  if (/\b(crispy|creamy|cheesy|garlicky|juicy|buttery|sticky|caramelized|faster|easier|mistake|shortcut|truth)\b/i.test(combined)) {
    score += 1;
  }

  for (const target of scoringTargets) {
    const overlap = sharedWordsRatio(combined, target);
    if (overlap >= 0.18) {
      score += 2;
      break;
    }
    if (overlap >= 0.1) {
      score += 1;
      break;
    }
  }

  for (const target of focusTargets) {
    const overlap = sharedWordsRatio(combined, target);
    if (overlap >= 0.2) {
      score += 1;
      break;
    }
  }

  return score;
}

function socialVariantPainPointSignal(variant, articleSignals = {}) {
  const combined = cleanText(`${variant?.hook || ""} ${variant?.caption || ""}`);
  if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|ruin|dry|bland|soggy|overcook|underseason|what most people get wrong)\b/i.test(combined)) {
    return true;
  }

  return Boolean(articleSignals?.pain_line) && sharedWordsRatio(combined, articleSignals.pain_line) >= 0.18;
}

function socialVariantPayoffSignal(variant, articleSignals = {}) {
  const combined = cleanText(`${variant?.hook || ""} ${variant?.caption || ""}`);
  if (/\b(payoff|result|worth it|comfort|crispy|creamy|juicy|easier|faster|better|clearer|simpler|repeatable|useful|satisfying)\b/i.test(combined)) {
    return true;
  }

  if (Boolean(articleSignals?.payoff_line) && sharedWordsRatio(combined, articleSignals.payoff_line) >= 0.18) {
    return true;
  }

  return Boolean(articleSignals?.final_reward_line) && sharedWordsRatio(combined, articleSignals.final_reward_line) >= 0.18;
}

function socialVariantProofSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");

  if (!combined) {
    return false;
  }

  if (/\b(\d+\s?(?:minute|min|minutes|mins|step|steps)|one[- ]pan|sheet pan|air fryer|skillet|oven|temperature|label|pantry|fridge|crispy|creamy|cheesy|garlicky|juicy|golden|without drying|without going soggy|that keeps|which keeps|because|so it stays|so you get)\b/i.test(combined)) {
    return true;
  }

  const clueTargets = [
    articleSignals?.proof_line,
    articleSignals?.detail_line,
    articleSignals?.page_signal_line,
    articleSignals?.final_reward_line,
  ].filter(Boolean);
  if (clueTargets.some((target) => sharedWordsRatio(combined, target) >= 0.18)) {
    return true;
  }

  return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
    && /\b(because|so|without|keeps|stays|means|difference|detail|result)\b/i.test(earlyCaption || hook)
    && socialVariantSpecificityScore(variant, articleSignals) >= 2;
}

function socialVariantCuriositySignal(variant, articleSignals = {}) {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const hookHasCue = /\b(why|how|turns out|actually|the difference|detail|changes|what most people|get wrong|mistake|truth|assumption)\b/i.test(hook);
  const clueOverlap = [
    articleSignals?.page_signal_line,
    articleSignals?.proof_line,
    articleSignals?.detail_line,
    articleSignals?.final_reward_line,
  ]
    .filter(Boolean)
    .some((target) => sharedWordsRatio(combined, target) >= 0.18);

  if (!hookHasCue || containsCheapSuspensePattern(hook) || containsCheapSuspensePattern(caption)) {
    return false;
  }

  return clueOverlap || socialVariantSpecificityScore(variant, articleSignals) >= 2;
}

function socialVariantContrastSignal(variant, articleSignals = {}) {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  if (/\b(instead of|rather than|not just|not the|more than|less about|without turning|but not|vs\.?|versus|the part that|what changes|what most people miss)\b/i.test(combined)) {
    return true;
  }

  const painOverlap = Boolean(articleSignals?.pain_line) ? sharedWordsRatio(combined, articleSignals.pain_line) : 0;
  const payoffOverlap = Boolean(articleSignals?.payoff_line) ? sharedWordsRatio(combined, articleSignals.payoff_line) : 0;
  const proofOverlap = Boolean(articleSignals?.proof_line) ? sharedWordsRatio(combined, articleSignals.proof_line) : 0;

  return (painOverlap >= 0.12 && payoffOverlap >= 0.12) || (painOverlap >= 0.12 && proofOverlap >= 0.12);
}

function socialVariantRelatabilitySignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  if (!combined) {
    return false;
  }

  const recipePattern = /\b(busy night|weeknight|after work|family dinner|home cook|at home|takeout night|budget dinner|feed everyone|tonight|make this tonight|fridge|pantry)\b/i;
  const factPattern = /\b(in your kitchen|at home|home cook|next time you cook|next time you buy|next time you store|next time you shop|your pantry|your fridge|the label|grocery aisle|home kitchen)\b/i;
  const pattern = contentType === "recipe" ? recipePattern : factPattern;
  if (pattern.test(combined)) {
    return true;
  }

  if (/\b(you|your)\b/i.test(combined) && articleSignals?.pain_line && sharedWordsRatio(combined, articleSignals.pain_line) >= 0.16) {
    return true;
  }

  return Boolean(articleSignals?.summary_line) && sharedWordsRatio(combined, articleSignals.summary_line) >= 0.18 && /\b(you|your|home|kitchen|dinner|cook)\b/i.test(combined);
}

function socialVariantSelfRecognitionSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  if (!combined) {
    return false;
  }

  const recipePattern = /\b(if your|when your|if you keep|if dinner keeps|if this keeps|the reason your|why your|you know that moment when|you know the night when)\b/i;
  const factPattern = /\b(if your|when your|if you keep|if that label keeps|if this keeps|the reason your|why your|you know that moment when|you know the shopping moment when)\b/i;
  const pattern = contentType === "recipe" ? recipePattern : factPattern;
  const repeatedOutcomePattern = /\b(keeps getting|keeps turning|keeps ending up|still turns|still ends up|still feels|same mistake|same result|same flat|same soggy|same dry|same bland|same confusion|same waste)\b/i;
  const articlePainOverlap = Boolean(articleSignals?.pain_line) && sharedWordsRatio(combined, articleSignals.pain_line) >= 0.16;
  const articleConsequenceOverlap = Boolean(articleSignals?.consequence_line) && sharedWordsRatio(combined, articleSignals.consequence_line) >= 0.16;

  if (pattern.test(combined) && (repeatedOutcomePattern.test(combined) || articlePainOverlap || articleConsequenceOverlap)) {
    return true;
  }

  return /\b(your|you)\b/i.test(combined)
    && socialVariantRelatabilitySignal(variant, articleSignals, contentType)
    && (
      repeatedOutcomePattern.test(combined)
      || articlePainOverlap
      || articleConsequenceOverlap
      || socialVariantConsequenceSignal(variant, articleSignals, contentType)
    )
    && socialVariantAnchorSignal(variant, articleSignals);
}

function socialVariantConversationSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  if (!combined) {
    return false;
  }

  if (/\b(comment|tag|share|send this|drop a|tell me in the comments|let me know)\b/i.test(combined)) {
    return false;
  }

  const recipePattern = /\b(your house|your table|your family|in your family|the person who|the friend who|most home cooks|a lot of home cooks|everyone thinks|everyone assumes|if you always|the way you always|which one|debate|split)\b/i;
  const factPattern = /\b(your kitchen|your pantry|your fridge|your grocery cart|at the store|on the label|the version you always buy|what most people buy|most people think|a lot of people assume|if you always|which one|debate|split)\b/i;
  const pattern = contentType === "recipe" ? recipePattern : factPattern;
  if (pattern.test(combined)) {
    return true;
  }

  return socialVariantRelatabilitySignal(variant, articleSignals, contentType)
    && socialVariantAnchorSignal(variant, articleSignals)
    && (
      socialVariantContrastSignal(variant, articleSignals)
      || socialVariantPainPointSignal(variant, articleSignals)
      || /\b(people|everyone|most|house|family|table|friend|buy|shop|order)\b/i.test(combined)
    );
}

function socialVariantHabitShiftSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  if (!combined) {
    return false;
  }

  const explicitShiftPattern = contentType === "recipe"
    ? /\b(if you always|if you still|the way you always|usual move|usual dinner move|default dinner move|instead of|rather than|stop doing|stop treating|swap|trade|skip the|break the habit|usual habit|same dinner habit|keep doing)\b/i
    : /\b(if you always|if you still|the way you always|usual move|default move|instead of|rather than|stop doing|swap|trade|skip the|break the habit|usual habit|same shopping habit|same kitchen habit|keep doing)\b/i;
  const shiftWords = /\b(always|still|instead of|rather than|swap|trade|usual|default|habit|keep doing|stop doing|break the habit|same mistake)\b/i.test(combined);
  const betterResult =
    socialVariantContrastSignal(variant, articleSignals)
    || socialVariantConsequenceSignal(variant, articleSignals, contentType)
    || socialVariantPayoffSignal(variant, articleSignals)
    || socialVariantActionabilitySignal(variant, articleSignals, contentType);
  const grounded =
    socialVariantAnchorSignal(variant, articleSignals)
    || socialVariantSpecificityScore(variant, articleSignals) >= 2;
  const sociallyRecognizable =
    socialVariantRelatabilitySignal(variant, articleSignals, contentType)
    || socialVariantConversationSignal(variant, articleSignals, contentType);

  if (explicitShiftPattern.test(combined) && betterResult && grounded) {
    return true;
  }

  if (shiftWords && sociallyRecognizable && betterResult && grounded) {
    return true;
  }

  return Boolean(articleSignals?.consequence_line)
    && Boolean(articleSignals?.payoff_line)
    && betterResult
    && sharedWordsRatio(combined, `${articleSignals.consequence_line} ${articleSignals.payoff_line}`) >= 0.16;
}

function socialVariantSavvySignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");

  if (!combined) {
    return false;
  }

  if (/\b(smart cooks|real cooks|good cooks know|bad cooks|lazy cooks|amateurs?|rookie move)\b/i.test(combined)) {
    return false;
  }

  const explicitPattern = contentType === "recipe"
    ? /\b(smarter move|smarter dinner move|better move|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the method that works|the version worth making|the version worth repeating|worth using|worth making)\b/i
    : /\b(smarter move|smarter buy|better buy|better pick|better choice|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the version worth buying|the version worth keeping|the detail worth knowing|worth checking|worth buying|worth using)\b/i;
  const smartChoiceWords = /\b(smarter|cleaner|better|worth|reliable|more reliable|better call|better bet|better pick|better choice|better move|good call)\b/i.test(hook || earlyCaption || combined);
  const grounded =
    socialVariantAnchorSignal(variant, articleSignals)
    || socialVariantSpecificityScore(variant, articleSignals) >= 2;
  const usefulSignal =
    socialVariantProofSignal(variant, articleSignals, contentType)
    || socialVariantActionabilitySignal(variant, articleSignals, contentType)
    || socialVariantPromiseSyncSignal(variant, articleSignals?.title || "", articleSignals, contentType)
    || socialVariantHabitShiftSignal(variant, articleSignals, contentType)
    || socialVariantConsequenceSignal(variant, articleSignals, contentType)
    || socialVariantPayoffSignal(variant, articleSignals);
  const overlapSignal = [
    articleSignals?.proof_line,
    articleSignals?.detail_line,
    articleSignals?.payoff_line,
    articleSignals?.page_signal_line,
  ]
    .filter(Boolean)
    .some((target) => sharedWordsRatio(combined, target) >= 0.16);

  if (explicitPattern.test(combined) && grounded && usefulSignal) {
    return true;
  }

  return smartChoiceWords && grounded && (usefulSignal || overlapSignal);
}

function socialVariantIdentityShiftSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");

  if (!combined) {
    return false;
  }

  if (/\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you're doing|amateurs?|rookie move|lazy cooks)\b/i.test(combined)) {
    return false;
  }

  const explicitPattern = contentType === "recipe"
    ? /\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old weeknight move|past the usual dinner drag|no longer stuck with|graduate from)\b/i
    : /\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old shopping move|past the usual confusion|no longer stuck with|graduate from)\b/i;
  const shiftWords = /\b(done with|leave behind|move past|past the usual|no longer stuck with|stop settling|old default|usual default|graduate from|break out of)\b/i.test(hook || earlyCaption || combined);
  const grounded =
    socialVariantAnchorSignal(variant, articleSignals)
    || socialVariantSpecificityScore(variant, articleSignals) >= 2;
  const practicalLift =
    socialVariantSavvySignal(variant, articleSignals, contentType)
    || socialVariantHabitShiftSignal(variant, articleSignals, contentType)
    || socialVariantConsequenceSignal(variant, articleSignals, contentType)
    || socialVariantActionabilitySignal(variant, articleSignals, contentType)
    || socialVariantPayoffSignal(variant, articleSignals);
  const recognition =
    socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)
    || socialVariantRelatabilitySignal(variant, articleSignals, contentType)
    || socialVariantConversationSignal(variant, articleSignals, contentType);

  if (explicitPattern.test(combined) && grounded && practicalLift && recognition) {
    return true;
  }

  return shiftWords && grounded && practicalLift && recognition;
}

function socialVariantActionabilitySignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");

  if (!combined) {
    return false;
  }

  if (/\b(next time you|before you|use this|skip the|start with|watch for|look for|keep it|swap in|swap out|do this|try this|store it|cook it|buy it|save this for|make this when)\b/i.test(combined)) {
    return true;
  }

  const guidanceTargets = [
    articleSignals?.detail_line,
    articleSignals?.proof_line,
    articleSignals?.pain_line,
    articleSignals?.payoff_line,
  ].filter(Boolean);
  if (guidanceTargets.some((target) => sharedWordsRatio(combined, target) >= 0.18) && /\b(you|your|next|before|when|keep|skip|use|cook|store|buy|make|watch)\b/i.test(earlyCaption || combined)) {
    return true;
  }

  return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
    && /\b(next|before|when|keep|skip|use|cook|store|buy|make|watch)\b/i.test(earlyCaption || combined)
    && socialVariantSpecificityScore(variant, articleSignals) >= 2;
}

function socialVariantImmediacySignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");

  if (!combined) {
    return false;
  }

  const recipePattern = /\b(tonight|this week|this weekend|after work|before dinner|next grocery run|next shop|next time you cook|next time you shop|next time you make|weeknight|tomorrow night)\b/i;
  const factPattern = /\b(this week|this weekend|next grocery run|next time you buy|next time you shop|next time you cook|next time you order|next time you store|before you buy|before you cook|before you order)\b/i;
  const pattern = contentType === "recipe" ? recipePattern : factPattern;
  if (pattern.test(combined)) {
    return true;
  }

  const immediacyTargets = [
    articleSignals?.detail_line,
    articleSignals?.proof_line,
    articleSignals?.payoff_line,
    articleSignals?.page_signal_line,
    articleSignals?.final_reward_line,
  ].filter(Boolean);
  if (immediacyTargets.some((target) => sharedWordsRatio(combined, target) >= 0.16)
    && /\b(tonight|this week|this weekend|next|before|after work|grocery run|when you cook|when you buy|when you order)\b/i.test(earlyCaption || combined)) {
    return true;
  }

  return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
    && socialVariantSpecificityScore(variant, articleSignals) >= 2
    && /\b(tonight|this week|this weekend|next|before|after work|grocery run|when you cook|when you buy|when you order)\b/i.test(combined);
}

function socialVariantConsequenceSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const combined = cleanText(`${hook} ${caption}`);
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");

  if (!combined) {
    return false;
  }

  if (/\b(otherwise|or you keep|or it keeps|costs you|keeps costing|keeps wasting|wastes time|wastes money|ends up|turns dry|turns soggy|falls flat|miss the detail|miss that|without the detail|keep repeating|same mistake|less payoff|more effort|still paying for|still stuck with)\b/i.test(combined)) {
    return true;
  }

  if (Boolean(articleSignals?.consequence_line) && sharedWordsRatio(combined, articleSignals.consequence_line) >= 0.18) {
    return true;
  }

  return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
    && /\b(otherwise|miss|lose|cost|waste|repeat|stuck|flat|dry|soggy|harder|less payoff|more effort)\b/i.test(earlyCaption || combined)
    && socialVariantSpecificityScore(variant, articleSignals) >= 2;
}

function socialVariantPromiseFocusSignal(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");
  const leadWindow = cleanText(`${hook} ${earlyCaption}`);
  if (!leadWindow) {
    return false;
  }

  const separatorCount = (leadWindow.match(/,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/gi) || []).length;
  const promiseHitCount = [
    socialVariantPainPointSignal(variant, articleSignals),
    socialVariantPayoffSignal(variant, articleSignals),
    socialVariantProofSignal(variant, articleSignals, contentType),
    socialVariantActionabilitySignal(variant, articleSignals, contentType),
    socialVariantConsequenceSignal(variant, articleSignals, contentType),
    socialVariantCuriositySignal(variant, articleSignals),
    socialVariantContrastSignal(variant, articleSignals),
  ].filter(Boolean).length;
  const focusedOverlap = [
    articleSignals?.pain_line,
    articleSignals?.payoff_line,
    articleSignals?.proof_line,
    articleSignals?.detail_line,
    articleSignals?.consequence_line,
  ]
    .filter(Boolean)
    .some((target) => sharedWordsRatio(leadWindow, target) >= 0.18);

  return socialVariantSpecificityScore(variant, articleSignals) >= 2
    && frontLoadedClickSignalScore(hook, contentType) >= 0
    && countWords(hook) <= 13
    && countWords(earlyCaption || hook) <= 24
    && separatorCount <= 3
    && promiseHitCount <= 4
    && focusedOverlap;
}

function socialVariantTwoStepSignal(variant, articleSignals = {}, contentType = "recipe") {
  const captionLines = cleanMultilineText(variant?.caption || "")
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2);
  if (captionLines.length < 2) {
    return false;
  }

  const [line1, line2] = captionLines;
  const line1Words = countWords(line1);
  const line2Words = countWords(line2);
  const lineOverlap = Math.max(sharedWordsRatio(line1, line2), sharedWordsRatio(line2, line1));
  const line1Start = normalizeSlug(line1.split(/\s+/).slice(0, 2).join(" "));
  const line2Start = normalizeSlug(line2.split(/\s+/).slice(0, 2).join(" "));
  const genericLeadPattern = /^(this|it|that|these|they)\b|^(you should|this is|this one|these are|here'?s why)\b/i;
  const line1Variant = { hook: "", caption: line1 };
  const line2Variant = { hook: "", caption: line2 };
  const line1ProblemClue =
    socialVariantPainPointSignal(line1Variant, articleSignals)
    || socialVariantProofSignal(line1Variant, articleSignals, contentType)
    || socialVariantCuriositySignal(line1Variant, articleSignals)
    || socialVariantContrastSignal(line1Variant)
    || frontLoadedClickSignalScore(line1, contentType) > 0;
  const line1Payoff = socialVariantPayoffSignal(line1Variant, articleSignals);
  const line2UseOrResult =
    socialVariantPayoffSignal(line2Variant, articleSignals)
    || socialVariantActionabilitySignal(line2Variant, articleSignals, contentType)
    || socialVariantConsequenceSignal(line2Variant, articleSignals, contentType)
    || socialVariantProofSignal(line2Variant, articleSignals, contentType);
  const line2DistinctEnough =
    socialVariantSpecificityScore(line2Variant, articleSignals) >= 1
    || [
      articleSignals?.payoff_line,
      articleSignals?.proof_line,
      articleSignals?.detail_line,
      articleSignals?.consequence_line,
      articleSignals?.page_signal_line,
    ]
      .filter(Boolean)
      .some((target) => sharedWordsRatio(line2, target) >= 0.16);
  const complementaryFlow =
    (line1ProblemClue && line2UseOrResult)
    || (line1Payoff && (
      socialVariantProofSignal(line2Variant, articleSignals, contentType)
      || socialVariantActionabilitySignal(line2Variant, articleSignals, contentType)
      || socialVariantConsequenceSignal(line2Variant, articleSignals, contentType)
    ));

  return socialVariantSpecificityScore(variant, articleSignals) >= 2
    && line1Words >= 4
    && line1Words <= 14
    && line2Words >= 4
    && line2Words <= 16
    && !genericLeadPattern.test(line1)
    && !genericLeadPattern.test(line2)
    && lineOverlap <= 0.72
    && line1Start !== ""
    && line1Start !== line2Start
    && line2DistinctEnough
    && complementaryFlow;
}

function socialVariantScannabilitySignal(variant, contentType = "recipe") {
  const lines = cleanMultilineText(variant?.caption || "")
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 4);
  if (lines.length < 3) {
    return false;
  }

  const lineWordCounts = lines.map((line) => countWords(line));
  const shortLines = lineWordCounts.filter((count) => count >= 3 && count <= 12).length;
  const lineStarts = lines
    .map((line) => normalizeSlug(line.split(/\s+/).slice(0, 2).join(" ")))
    .filter(Boolean);
  const uniqueStarts = new Set(lineStarts);
  const repeatedAdjacent = lines.some((line, index) => {
    if (index === 0) {
      return false;
    }
    const previous = lines[index - 1] || "";
    return Math.max(sharedWordsRatio(line, previous), sharedWordsRatio(previous, line)) >= 0.72;
  });
  const overloadedLines = lines.filter((line) => /,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/gi.test(line)).length;
  const frontLoadedLines = lines.filter((line) => frontLoadedClickSignalScore(line, contentType) > 0).length;

  return shortLines >= 2
    && uniqueStarts.size >= Math.min(lines.length, 3)
    && !repeatedAdjacent
    && overloadedLines <= 1
    && frontLoadedLines >= 1;
}

function socialVariantResolvesEarly(variant, articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const captionLines = cleanMultilineText(variant?.caption || "")
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean);
  const earlyCaption = cleanText(captionLines.slice(0, 2).join(" "));
  const needsResolution = /[?]|\b(why|how|turns out|actually|the difference|changes|truth|mistake|what most people|get wrong|instead of|rather than|not just|more than|less about|vs\.?|versus)\b/i.test(hook);

  if (!earlyCaption) {
    return false;
  }

  const clueTargets = [
    articleSignals.pain_line,
    articleSignals.payoff_line,
    articleSignals.proof_line,
    articleSignals.detail_line,
    articleSignals.page_signal_line,
    articleSignals.final_reward_line,
  ].filter(Boolean);
  const overlapHit = clueTargets.some((target) => sharedWordsRatio(earlyCaption, target) >= 0.16);
  const frontLoadedHit = frontLoadedClickSignalScore(earlyCaption, contentType) > 0;
  const concreteHit = /\b(crispy|creamy|cheesy|garlicky|juicy|mistake|shortcut|truth|faster|easier|save|problem|result|payoff|difference|detail|reason|because|instead|clearer|better)\b/i.test(earlyCaption);

  if (!needsResolution) {
    return overlapHit || (frontLoadedHit && concreteHit);
  }

  return overlapHit || (frontLoadedHit && concreteHit);
}

function socialVariantPromiseSyncSignal(variant, articleTitle = "", articleSignals = {}, contentType = "recipe") {
  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const earlyCaption = caption
    .split(/\r?\n/)
    .map((line) => cleanText(line))
    .filter(Boolean)
    .slice(0, 2)
    .join(" ");
  const leadWindow = cleanText(`${hook} ${earlyCaption}`);
  if (!leadWindow) {
    return false;
  }

  const normalizedHook = normalizeSlug(hook);
  const normalizedTitle = normalizeSlug(articleTitle);
  const titleOverlap = articleTitle ? sharedWordsRatio(leadWindow, articleTitle) : 0;
  const signalTargets = [
    articleSignals?.summary_line,
    articleSignals?.pain_line,
    articleSignals?.payoff_line,
    articleSignals?.proof_line,
    articleSignals?.detail_line,
    articleSignals?.page_signal_line,
    articleSignals?.final_reward_line,
  ].filter(Boolean);
  const signalOverlap = signalTargets.reduce((max, target) => Math.max(max, sharedWordsRatio(leadWindow, target)), 0);
  const promiseHit =
    socialVariantPainPointSignal(variant, articleSignals)
    || socialVariantPayoffSignal(variant, articleSignals)
    || socialVariantProofSignal(variant, articleSignals, contentType)
    || socialVariantActionabilitySignal(variant, articleSignals, contentType)
    || socialVariantConsequenceSignal(variant, articleSignals, contentType);

  return socialVariantSpecificityScore(variant, articleSignals) >= 2
    && frontLoadedClickSignalScore(hook || earlyCaption, contentType) > 0
    && normalizedHook !== ""
    && normalizedHook !== normalizedTitle
    && (titleOverlap >= 0.12 || socialVariantAnchorSignal(variant, articleSignals))
    && (signalOverlap >= 0.14 || promiseHit);
}

function scoreSocialVariant(variant, articleTitle = "", contentType = "recipe", articleContext = "", articleSignals = null) {
  if (!variant || socialVariantLooksWeak(variant, articleTitle, contentType, articleSignals || null)) {
    return -100;
  }

  const hook = cleanText(variant?.hook || "");
  const caption = cleanMultilineText(variant?.caption || "");
  const hookWords = countWords(hook);
  const captionWords = countWords(caption);
  const captionLines = countLines(caption);
  const normalizedHook = normalizeSlug(hook);
  const normalizedTitle = normalizeSlug(articleTitle);
  const angleKey = normalizeAngleKey(variant?.angle_key || "", contentType);
  const overlap = sharedWordsRatio(hook, articleTitle);
  const contextOverlap = articleContext ? sharedWordsRatio(`${hook} ${caption}`, articleContext) : 0;
  const specificityScore = socialVariantSpecificityScore(variant, articleSignals || {});
  const noveltyScore = socialVariantNoveltyScore(variant, articleTitle, articleSignals || {});
  const anchorScore = socialVariantAnchorSignal(variant, articleSignals || {}) ? 2 : 0;
  const painPointScore = socialVariantPainPointSignal(variant, articleSignals || {}) ? 2 : 0;
  const payoffScore = socialVariantPayoffSignal(variant, articleSignals || {}) ? 2 : 0;
  const proofScore = socialVariantProofSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const selfRecognitionScore = socialVariantSelfRecognitionSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const savvyScore = socialVariantSavvySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const identityShiftScore = socialVariantIdentityShiftSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const actionabilityScore = socialVariantActionabilitySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const immediacyScore = socialVariantImmediacySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const consequenceScore = socialVariantConsequenceSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const habitShiftScore = socialVariantHabitShiftSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const focusScore = socialVariantPromiseFocusSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const scannabilityScore = socialVariantScannabilitySignal(variant, contentType) ? 1 : 0;
  const twoStepScore = socialVariantTwoStepSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const curiosityScore = socialVariantCuriositySignal(variant, articleSignals || {}) ? 1 : 0;
  const contrastScore = socialVariantContrastSignal(variant, articleSignals || {}) ? 1 : 0;
  const relatabilityScore = socialVariantRelatabilitySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const conversationScore = socialVariantConversationSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
  const resolutionScore = socialVariantResolvesEarly(variant, articleSignals || {}, contentType) ? 1 : 0;
  const promiseSyncScore = socialVariantPromiseSyncSignal(variant, articleTitle, articleSignals || {}, contentType) ? 1 : 0;
  const hookFrontLoadScore = frontLoadedClickSignalScore(hook, contentType);
  const payoffOverlap = articleSignals?.payoff_line ? sharedWordsRatio(`${hook} ${caption}`, articleSignals.payoff_line) : 0;
  let score = 0;

  if (angleKey) {
    score += 4;
  }
  score += hookWords >= 6 && hookWords <= 11 ? 6 : 3;
  score += captionWords >= 22 && captionWords <= 55 ? 5 : 2;
  score += captionLines >= 3 && captionLines <= 4 ? 4 : 2;
  if (normalizedTitle !== "" && normalizedHook !== normalizedTitle) {
    score += 4;
  }
  if (overlap <= 0.45) {
    score += 3;
  } else if (overlap >= 0.8) {
    score -= 5;
  }
  if (stripHookEchoFromCaption(hook, caption) === caption) {
    score += 2;
  }
  if (cleanMultilineText(variant?.post_message || "")) {
    score += 1;
  }
  if (articleContext) {
    if (contextOverlap >= 0.16 && contextOverlap <= 0.7) {
      score += 4;
    } else if (contextOverlap >= 0.08) {
      score += 2;
    } else if (contextOverlap === 0) {
      score -= 2;
    }
  }
  score += specificityScore;
  score += noveltyScore;
  score += anchorScore;
  score += painPointScore;
  score += payoffScore;
  score += proofScore;
  score += selfRecognitionScore;
  score += savvyScore;
  score += identityShiftScore;
  score += actionabilityScore;
  score += immediacyScore;
  score += consequenceScore;
  score += habitShiftScore;
  score += focusScore;
  score += scannabilityScore;
  score += twoStepScore;
  score += curiosityScore;
  score += contrastScore;
  score += relatabilityScore;
  score += conversationScore;
  score += resolutionScore;
  score += promiseSyncScore;
  score += hookFrontLoadScore;
  if (payoffOverlap >= 0.18) {
    score += 2;
  } else if (payoffOverlap >= 0.1) {
    score += 1;
  }

  return score - socialVariantGenericPenalty(variant);
}

function summarizeSocialCandidatePool(candidates, article, desiredCount, contentType = "recipe") {
  const normalized = normalizeSocialPack(candidates, contentType);
  const angleLimit = angleDefinitionsForType(contentType).length;
  const poolTarget = Math.max(6, Math.min(10, desiredCount + 3));
  const strongTarget = Math.max(desiredCount, Math.min(4, poolTarget));
  const distinctVariantTarget = Math.max(1, Math.min(normalized.length, Math.max(desiredCount, 4)));
  const distinctHookTarget = Math.max(1, Math.min(normalized.length, Math.max(desiredCount, 3)));
  const distinctOpeningTarget = desiredCount > 1 ? Math.max(1, Math.min(normalized.length, desiredCount)) : 1;
  const distinctAngleTarget = desiredCount > 1 ? Math.max(2, Math.min(angleLimit, desiredCount)) : 1;
  const articleTitle = cleanText(article?.title || "");
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const articleContext = articleSignals.context_text;
  const fingerprints = new Set(normalized.map((variant) => normalizeSocialFingerprint(variant)).filter(Boolean));
  const hookFingerprints = new Set(normalized.map((variant) => normalizeHookFingerprint(variant)).filter(Boolean));
  const openingFingerprints = new Set(normalized.map((variant) => normalizeCaptionOpeningFingerprint(variant)).filter(Boolean));
  const angleKeys = new Set(normalized.map((variant) => normalizeAngleKey(variant?.angle_key || "", contentType)).filter(Boolean));
  const hookForms = new Set(normalized.map((variant) => classifySocialHookForm(variant)).filter(Boolean));
  const strongCandidates = normalized.filter((variant) => !socialVariantLooksWeak(variant, articleTitle, contentType, articleSignals)).length;
  const specificCandidates = normalized.filter((variant) => socialVariantSpecificityScore(variant, articleSignals) >= 2).length;
  const noveltyCandidates = normalized.filter((variant) => socialVariantNoveltyScore(variant, articleTitle, articleSignals) >= 2).length;
  const anchorCandidates = normalized.filter((variant) => socialVariantAnchorSignal(variant, articleSignals)).length;
  const relatableCandidates = normalized.filter((variant) => socialVariantRelatabilitySignal(variant, articleSignals, contentType)).length;
  const recognitionCandidates = normalized.filter((variant) => socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)).length;
  const conversationCandidates = normalized.filter((variant) => socialVariantConversationSignal(variant, articleSignals, contentType)).length;
  const savvyCandidates = normalized.filter((variant) => socialVariantSavvySignal(variant, articleSignals, contentType)).length;
  const identityShiftCandidates = normalized.filter((variant) => socialVariantIdentityShiftSignal(variant, articleSignals, contentType)).length;
  const painPointCandidates = normalized.filter((variant) => socialVariantPainPointSignal(variant, articleSignals)).length;
  const payoffCandidates = normalized.filter((variant) => socialVariantPayoffSignal(variant, articleSignals)).length;
  const proofCandidates = normalized.filter((variant) => socialVariantProofSignal(variant, articleSignals, contentType)).length;
  const actionableCandidates = normalized.filter((variant) => socialVariantActionabilitySignal(variant, articleSignals, contentType)).length;
  const immediacyCandidates = normalized.filter((variant) => socialVariantImmediacySignal(variant, articleSignals, contentType)).length;
  const consequenceCandidates = normalized.filter((variant) => socialVariantConsequenceSignal(variant, articleSignals, contentType)).length;
  const habitShiftCandidates = normalized.filter((variant) => socialVariantHabitShiftSignal(variant, articleSignals, contentType)).length;
  const focusedCandidates = normalized.filter((variant) => socialVariantPromiseFocusSignal(variant, articleSignals, contentType)).length;
  const promiseSyncCandidates = normalized.filter((variant) => socialVariantPromiseSyncSignal(variant, articleTitle, articleSignals, contentType)).length;
  const scannableCandidates = normalized.filter((variant) => socialVariantScannabilitySignal(variant, contentType)).length;
  const twoStepCandidates = normalized.filter((variant) => socialVariantTwoStepSignal(variant, articleSignals, contentType)).length;
  const curiosityCandidates = normalized.filter((variant) => socialVariantCuriositySignal(variant, articleSignals)).length;
  const contrastCandidates = normalized.filter((variant) => socialVariantContrastSignal(variant, articleSignals)).length;
  const resolutionCandidates = normalized.filter((variant) => socialVariantResolvesEarly(variant, articleSignals, contentType)).length;
  const frontLoadedCandidates = normalized.filter((variant) => frontLoadedClickSignalScore(variant?.hook || "", contentType) > 0).length;
  const highScoringCandidates = normalized.filter((variant) => scoreSocialVariant(variant, articleTitle, contentType, articleContext, articleSignals) >= 18).length;
  const issues = [];

  if (normalized.length < poolTarget) {
    issues.push(`The social candidate pool is too small (${normalized.length}/${poolTarget}).`);
  }
  if (strongCandidates < strongTarget) {
    issues.push(`Too few strong social candidates survived local checks (${strongCandidates}/${strongTarget}).`);
  }
  if (specificCandidates < Math.max(desiredCount, Math.min(4, poolTarget))) {
    issues.push(`Too few specific social candidates anchor the pool in real article payoff (${specificCandidates}/${Math.max(desiredCount, Math.min(4, poolTarget))}).`);
  }
  if (noveltyCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates add a concrete new detail beyond the title (${noveltyCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (anchorCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates name a concrete article anchor instead of relying on vague pronouns (${anchorCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (relatableCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates frame a recognizable real-life kitchen moment (${relatableCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (recognitionCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates create a direct self-recognition moment around a repeated kitchen result or mistake (${recognitionCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (conversationCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates feel socially discussable through a real household habit, shopping split, or recognizable choice (${conversationCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (savvyCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates make the reader feel they are about to make a smarter kitchen or shopping move (${savvyCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (identityShiftCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates create a clean break from the reader's old default behavior (${identityShiftCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (painPointCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates frame a concrete problem, mistake, or shortcut (${painPointCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (payoffCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates frame a clear result, payoff, or reason to care (${payoffCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (proofCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates carry a small believable proof or concrete clue (${proofCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (actionableCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates make the next move or practical use feel obvious (${actionableCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (immediacyCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates make the relevance feel immediate to the reader's next cook, shop, or order (${immediacyCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (consequenceCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates make the cost, waste, or repeated mistake feel concrete (${consequenceCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (habitShiftCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates create a clear old-habit-vs-better-result shift (${habitShiftCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (focusedCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates stay centered on one clean dominant promise instead of stacking too many claims (${focusedCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (promiseSyncCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates stay aligned with the article's title-and-opening promise without simply echoing the headline (${promiseSyncCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (scannableCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates stay easy to scan in short distinct caption lines (${scannableCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (twoStepCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates use caption line 1 and line 2 for distinct useful jobs instead of repeating the same idea (${twoStepCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (curiosityCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates create honest curiosity with a concrete clue (${curiosityCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (resolutionCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates resolve the hook with a concrete clue in the first caption lines (${resolutionCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (contrastCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates use a clean expectation-vs-reality or mistake-vs-fix contrast (${contrastCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (frontLoadedCandidates < Math.max(1, Math.min(desiredCount, 2))) {
    issues.push(`Too few candidates lead with a concrete problem, payoff, or surprise in the first words (${frontLoadedCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  }
  if (highScoringCandidates < Math.max(desiredCount, Math.min(4, poolTarget))) {
    issues.push(`Too few candidates score high on specificity and click promise (${highScoringCandidates}/${Math.max(desiredCount, Math.min(4, poolTarget))}).`);
  }
  if (fingerprints.size < distinctVariantTarget) {
    issues.push(`The social candidates are too repetitive overall (${fingerprints.size} distinct full variants).`);
  }
  if (hookFingerprints.size < distinctHookTarget) {
    issues.push(`The hooks are too repetitive (${hookFingerprints.size} distinct hooks).`);
  }
  if (desiredCount > 1 && openingFingerprints.size < distinctOpeningTarget) {
    issues.push(`The caption openings are too repetitive (${openingFingerprints.size} distinct openings).`);
  }
  if (desiredCount > 1 && angleKeys.size < distinctAngleTarget) {
    issues.push(`The angle mix is too narrow (${angleKeys.size} distinct angles).`);
  }
  if (desiredCount > 1 && hookForms.size < Math.max(2, Math.min(3, desiredCount))) {
    issues.push(`The hook shapes are too narrow (${hookForms.size} distinct hook forms).`);
  }

  return {
    issues,
    metrics: {
      pool_size: normalized.length,
      strong_candidates: strongCandidates,
      specific_candidates: specificCandidates,
      novelty_candidates: noveltyCandidates,
      anchor_candidates: anchorCandidates,
      relatable_candidates: relatableCandidates,
      recognition_candidates: recognitionCandidates,
      conversation_candidates: conversationCandidates,
      savvy_candidates: savvyCandidates,
      identity_shift_candidates: identityShiftCandidates,
      pain_point_candidates: painPointCandidates,
      payoff_candidates: payoffCandidates,
      proof_candidates: proofCandidates,
      actionable_candidates: actionableCandidates,
      immediacy_candidates: immediacyCandidates,
      consequence_candidates: consequenceCandidates,
      habit_shift_candidates: habitShiftCandidates,
      focused_candidates: focusedCandidates,
      promise_sync_candidates: promiseSyncCandidates,
      scannable_candidates: scannableCandidates,
      two_step_candidates: twoStepCandidates,
      curiosity_candidates: curiosityCandidates,
      resolution_candidates: resolutionCandidates,
      contrast_candidates: contrastCandidates,
      front_loaded_candidates: frontLoadedCandidates,
      high_scoring_candidates: highScoringCandidates,
      unique_variants: fingerprints.size,
      unique_hooks: hookFingerprints.size,
      unique_openings: openingFingerprints.size,
      unique_angles: angleKeys.size,
      unique_hook_forms: hookForms.size,
    },
  };
}

function summarizeSelectedSocialPack(socialPack, article, contentType = "recipe") {
  const normalized = normalizeSocialPack(socialPack, contentType);
  const articleTitle = cleanText(article?.title || "");
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const articleContext = articleSignals.context_text;
  const scores = normalized
    .map((variant) => scoreSocialVariant(variant, articleTitle, contentType, articleContext, articleSignals))
    .filter((score) => Number.isFinite(score));
  const specificVariants = normalized.filter((variant) => socialVariantSpecificityScore(variant, articleSignals) >= 2).length;
  const noveltyVariants = normalized.filter((variant) => socialVariantNoveltyScore(variant, articleTitle, articleSignals) >= 2).length;
  const anchorVariants = normalized.filter((variant) => socialVariantAnchorSignal(variant, articleSignals)).length;
  const relatableVariants = normalized.filter((variant) => socialVariantRelatabilitySignal(variant, articleSignals, contentType)).length;
  const recognitionVariants = normalized.filter((variant) => socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)).length;
  const conversationVariants = normalized.filter((variant) => socialVariantConversationSignal(variant, articleSignals, contentType)).length;
  const savvyVariants = normalized.filter((variant) => socialVariantSavvySignal(variant, articleSignals, contentType)).length;
  const identityShiftVariants = normalized.filter((variant) => socialVariantIdentityShiftSignal(variant, articleSignals, contentType)).length;
  const painPointVariants = normalized.filter((variant) => socialVariantPainPointSignal(variant, articleSignals)).length;
  const payoffVariants = normalized.filter((variant) => socialVariantPayoffSignal(variant, articleSignals)).length;
  const proofVariants = normalized.filter((variant) => socialVariantProofSignal(variant, articleSignals, contentType)).length;
  const actionableVariants = normalized.filter((variant) => socialVariantActionabilitySignal(variant, articleSignals, contentType)).length;
  const immediacyVariants = normalized.filter((variant) => socialVariantImmediacySignal(variant, articleSignals, contentType)).length;
  const consequenceVariants = normalized.filter((variant) => socialVariantConsequenceSignal(variant, articleSignals, contentType)).length;
  const habitShiftVariants = normalized.filter((variant) => socialVariantHabitShiftSignal(variant, articleSignals, contentType)).length;
  const focusedVariants = normalized.filter((variant) => socialVariantPromiseFocusSignal(variant, articleSignals, contentType)).length;
  const promiseSyncVariants = normalized.filter((variant) => socialVariantPromiseSyncSignal(variant, articleTitle, articleSignals, contentType)).length;
  const scannableVariants = normalized.filter((variant) => socialVariantScannabilitySignal(variant, contentType)).length;
  const twoStepVariants = normalized.filter((variant) => socialVariantTwoStepSignal(variant, articleSignals, contentType)).length;
  const curiosityVariants = normalized.filter((variant) => socialVariantCuriositySignal(variant, articleSignals)).length;
  const contrastVariants = normalized.filter((variant) => socialVariantContrastSignal(variant, articleSignals)).length;
  const resolutionVariants = normalized.filter((variant) => socialVariantResolvesEarly(variant, articleSignals, contentType)).length;
  const frontLoadedVariants = normalized.filter((variant) => frontLoadedClickSignalScore(variant?.hook || "", contentType) > 0).length;
  const leadVariant = normalized[0] || null;
  const hookForms = new Set(normalized.map((variant) => classifySocialHookForm(variant)).filter(Boolean));
  const leadHookForm = leadVariant ? classifySocialHookForm(leadVariant) : "";
  const leadScore = leadVariant ? scoreSocialVariant(leadVariant, articleTitle, contentType, articleContext, articleSignals) : 0;
  const leadSpecific = leadVariant ? socialVariantSpecificityScore(leadVariant, articleSignals) >= 2 : false;
  const leadNovelty = leadVariant ? socialVariantNoveltyScore(leadVariant, articleTitle, articleSignals) >= 2 : false;
  const leadAnchored = leadVariant ? socialVariantAnchorSignal(leadVariant, articleSignals) : false;
  const leadRelatable = leadVariant ? socialVariantRelatabilitySignal(leadVariant, articleSignals, contentType) : false;
  const leadRecognition = leadVariant ? socialVariantSelfRecognitionSignal(leadVariant, articleSignals, contentType) : false;
  const leadConversation = leadVariant ? socialVariantConversationSignal(leadVariant, articleSignals, contentType) : false;
  const leadSavvy = leadVariant ? socialVariantSavvySignal(leadVariant, articleSignals, contentType) : false;
  const leadIdentityShift = leadVariant ? socialVariantIdentityShiftSignal(leadVariant, articleSignals, contentType) : false;
  const leadPainPoint = leadVariant ? socialVariantPainPointSignal(leadVariant, articleSignals) : false;
  const leadPayoff = leadVariant ? socialVariantPayoffSignal(leadVariant, articleSignals) : false;
  const leadProof = leadVariant ? socialVariantProofSignal(leadVariant, articleSignals, contentType) : false;
  const leadActionable = leadVariant ? socialVariantActionabilitySignal(leadVariant, articleSignals, contentType) : false;
  const leadImmediacy = leadVariant ? socialVariantImmediacySignal(leadVariant, articleSignals, contentType) : false;
  const leadConsequence = leadVariant ? socialVariantConsequenceSignal(leadVariant, articleSignals, contentType) : false;
  const leadHabitShift = leadVariant ? socialVariantHabitShiftSignal(leadVariant, articleSignals, contentType) : false;
  const leadFocused = leadVariant ? socialVariantPromiseFocusSignal(leadVariant, articleSignals, contentType) : false;
  const leadPromiseSync = leadVariant ? socialVariantPromiseSyncSignal(leadVariant, articleTitle, articleSignals, contentType) : false;
  const leadScannable = leadVariant ? socialVariantScannabilitySignal(leadVariant, contentType) : false;
  const leadTwoStep = leadVariant ? socialVariantTwoStepSignal(leadVariant, articleSignals, contentType) : false;
  const leadCuriosity = leadVariant ? socialVariantCuriositySignal(leadVariant, articleSignals) : false;
  const leadContrast = leadVariant ? socialVariantContrastSignal(leadVariant, articleSignals) : false;
  const leadResolved = leadVariant ? socialVariantResolvesEarly(leadVariant, articleSignals, contentType) : false;
  const leadFrontLoaded = leadVariant ? frontLoadedClickSignalScore(leadVariant?.hook || "", contentType) > 0 : false;
  const averageScore = scores.length
    ? Number((scores.reduce((sum, value) => sum + value, 0) / scores.length).toFixed(1))
    : 0;

  return {
    selected_social_average_score: averageScore,
    specific_social_variants: specificVariants,
    novelty_variants: noveltyVariants,
    anchored_variants: anchorVariants,
    relatable_variants: relatableVariants,
    recognition_variants: recognitionVariants,
    conversation_variants: conversationVariants,
    savvy_variants: savvyVariants,
    identity_shift_variants: identityShiftVariants,
    pain_point_variants: painPointVariants,
    payoff_variants: payoffVariants,
    proof_variants: proofVariants,
    actionable_variants: actionableVariants,
    immediacy_variants: immediacyVariants,
    consequence_variants: consequenceVariants,
    habit_shift_variants: habitShiftVariants,
    focused_variants: focusedVariants,
    promise_sync_variants: promiseSyncVariants,
    scannable_variants: scannableVariants,
    two_step_variants: twoStepVariants,
    curiosity_variants: curiosityVariants,
    resolution_variants: resolutionVariants,
    contrast_variants: contrastVariants,
    front_loaded_social_variants: frontLoadedVariants,
    unique_hook_forms: hookForms.size,
    lead_social_score: leadScore,
    lead_social_specific: leadSpecific,
    lead_social_novelty: leadNovelty,
    lead_social_anchored: leadAnchored,
    lead_social_relatable: leadRelatable,
    lead_social_recognition: leadRecognition,
    lead_social_conversation: leadConversation,
    lead_social_savvy: leadSavvy,
    lead_social_identity_shift: leadIdentityShift,
    lead_social_proof: leadProof,
    lead_social_actionable: leadActionable,
    lead_social_immediacy: leadImmediacy,
    lead_social_consequence: leadConsequence,
    lead_social_habit_shift: leadHabitShift,
    lead_social_focused: leadFocused,
    lead_social_promise_sync: leadPromiseSync,
    lead_social_scannable: leadScannable,
    lead_social_two_step: leadTwoStep,
    lead_social_curiosity: leadCuriosity,
    lead_social_resolved: leadResolved,
    lead_social_contrast: leadContrast,
    lead_social_pain_point: leadPainPoint,
    lead_social_payoff: leadPayoff,
    lead_social_front_loaded: leadFrontLoaded,
    lead_social_hook_form: leadHookForm,
  };
}

function buildSocialPoolRepairNote(summary, desiredCount, contentType = "recipe") {
  const metrics = isPlainObject(summary?.metrics) ? summary.metrics : {};
  const poolTarget = Math.max(6, Math.min(10, desiredCount + 3));
  const strongTarget = Math.max(desiredCount, Math.min(4, poolTarget));
  const distinctVariantTarget = Math.max(1, Math.max(desiredCount, 4));
  const distinctHookTarget = Math.max(1, Math.max(desiredCount, 3));
  const distinctOpeningTarget = desiredCount > 1 ? Math.max(1, desiredCount) : 1;
  const distinctAngleTarget = desiredCount > 1 ? Math.max(2, Math.min(angleDefinitionsForType(contentType).length, desiredCount)) : 1;
  const fixes = [];

  if (Number(metrics.pool_size || 0) < poolTarget) {
    fixes.push(`Return at least ${poolTarget} candidates.`);
  }
  if (Number(metrics.strong_candidates || 0) < strongTarget) {
    fixes.push("Make more candidates strong, specific, and publishable.");
  }
  if (Number(metrics.specific_candidates || 0) < Math.max(desiredCount, Math.min(4, poolTarget))) {
    fixes.push("Anchor more hooks and captions in concrete article payoff, proof, ingredient focus, or useful detail.");
  }
  if (Number(metrics.novelty_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates add a concrete new detail or clue instead of restating the title.");
  }
  if (Number(metrics.anchor_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Name the actual dish, ingredient, mistake, method, or topic more often instead of leaning on vague 'this' or 'it' hooks.");
  }
  if (Number(metrics.relatable_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Frame more candidates around a recognizable home-kitchen moment or use case the reader can see themselves in.");
  }
  if (Number(metrics.recognition_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates create a direct 'that's me' moment by naming the repeated bad result, mistake, or kitchen symptom the reader already knows.");
  }
  if (Number(metrics.conversation_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates feel socially discussable by naming a real household habit, shopping split, or recognizable choice without asking for comments or tags.");
  }
  if (Number(metrics.savvy_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates feel like the reader is about to make a smarter kitchen or shopping move, but keep it grounded, practical, and never smug.");
  }
  if (Number(metrics.identity_shift_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates feel like the reader is leaving behind the old default move, but keep it honest and non-judgmental.");
  }
  if (Number(metrics.pain_point_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Include more candidates framed around a real mistake, problem, shortcut, or pain point.");
  }
  if (Number(metrics.payoff_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Include more candidates that front-load a clear payoff, result, or useful reason to care.");
  }
  if (Number(metrics.proof_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Give more candidates a small believable proof or concrete clue like timing, texture, ingredient job, label detail, or before-versus-after result.");
  }
  if (Number(metrics.immediacy_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates feel relevant right now, tied to tonight, this week, the next grocery run, or the reader's next cook, shop, or order.");
  }
  if (Number(metrics.consequence_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Show the consequence more often: what gets wasted, repeated, overcomplicated, or missed if the reader keeps doing the usual thing.");
  }
  if (Number(metrics.habit_shift_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make more candidates create a clear old-habit-vs-better-result snap by naming the usual move and the better outcome without sounding preachy.");
  }
  if (Number(metrics.focused_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Keep more candidates centered on one clean dominant promise instead of stacking too many benefits, claims, or angles into one post.");
  }
  if (Number(metrics.promise_sync_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Keep more candidates aligned with the title and page-one promise by cashing the same core problem or payoff without echoing the headline.");
  }
  if (Number(metrics.scannable_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Keep more candidates easy to scan. Use short caption lines with distinct jobs instead of dense lines that all feel the same.");
  }
  if (Number(metrics.two_step_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Make caption line 1 and line 2 do different jobs more often: let line 1 give the clue, problem, or proof, and let line 2 sharpen the payoff, use, or result.");
  }
  if (Number(metrics.curiosity_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Include more candidates that open honest curiosity with a concrete clue instead of empty withholding.");
  }
  if (Number(metrics.resolution_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Resolve more hooks with a concrete clue in caption line 1 or 2 instead of delaying the useful answer.");
  }
  if (Number(metrics.contrast_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Include more candidates that create a clean contrast like expectation versus reality, mistake versus fix, or effort versus payoff.");
  }
  if (Number(metrics.front_loaded_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) {
    fixes.push("Lead more hooks with the concrete problem, payoff, shortcut, or surprise in the first few words.");
  }
  if (Number(metrics.high_scoring_candidates || 0) < Math.max(desiredCount, Math.min(4, poolTarget))) {
    fixes.push("Raise the click quality so more candidates feel specific, useful, and worth tapping.");
  }
  if (Number(metrics.strong_candidates || 0) < strongTarget || Number(metrics.high_scoring_candidates || 0) < Math.max(desiredCount, Math.min(4, poolTarget))) {
    fixes.push("Make the first few words of more hooks carry the concrete problem, payoff, shortcut, or surprise instead of vague setup.");
  }
  if (Number(metrics.unique_variants || 0) < distinctVariantTarget) {
    fixes.push("Make the full variants feel less repetitive overall.");
  }
  if (Number(metrics.unique_hooks || 0) < distinctHookTarget) {
    fixes.push("Use more distinct hooks.");
  }
  if (desiredCount > 1 && Number(metrics.unique_openings || 0) < distinctOpeningTarget) {
    fixes.push("Use more distinct caption openings.");
  }
  if (desiredCount > 1 && Number(metrics.unique_angles || 0) < distinctAngleTarget) {
    fixes.push("Broaden the angle mix across the pool.");
  }
  if (desiredCount > 1 && Number(metrics.unique_hook_forms || 0) < Math.max(2, Math.min(3, desiredCount))) {
    fixes.push("Use a wider mix of hook shapes across the pool: not just one repeated sentence pattern.");
  }

  return [
    `Previous social candidate pool was too weak for ${desiredCount} selected pages.`,
    "Fix these constraints:",
    ...fixes.map((fix) => `- ${fix}`),
    "- Keep the copy honest, specific, short, and hook-led.",
    "- If a hook opens a question or contradiction, resolve part of it by caption line 1 or 2.",
  ]
    .filter(Boolean)
    .join("\n");
}

function buildArticleRepairNote(errorMessage, job) {
  const message = cleanText(errorMessage || "");
  const contentType = job?.content_type || "recipe";
  const fixes = [];

  if (/not valid json/i.test(message)) {
    fixes.push("Return one valid JSON object only with no markdown, arrays, or wrapper prose.");
  }
  if (/article body was empty/i.test(message)) {
    fixes.push("Return non-empty content_pages with real WordPress-ready HTML. Do not return [], null, or empty wrappers.");
  }
  if (/missing ingredients or instructions/i.test(message)) {
    fixes.push("For recipe output, fill recipe.ingredients[] and recipe.instructions[] completely.");
  }
  if (/blocked generic opening phrase/i.test(message)) {
    fixes.push("Replace the opening with a concrete, human paragraph. Do not use generic filler or AI-style disclaimers.");
  }
  if (/first-person voice/i.test(message)) {
    fixes.push("Avoid first-person voice and memoir framing. Use the publication voice instead.");
  }

  if (!fixes.length) {
    fixes.push("Return a complete, publish-ready article package that matches the JSON contract exactly.");
    fixes.push("Keep the title, excerpt, seo_description, content_pages, page_flow, image fields, and recipe object filled correctly.");
  }

  if (contentType === "recipe" && !fixes.some((fix) => /recipe\.ingredients|recipe\.instructions/i.test(fix))) {
    fixes.push("Keep the recipe practical, cookable, and fully structured.");
  }
  if (contentType === "food_fact") {
    fixes.push("Stay in editorial explainer territory only and avoid recipe-style metadata.");
  }

  return [
    `Previous article attempt failed for ${contentType}.`,
    "Fix these constraints:",
    ...fixes.map((fix) => `- ${fix}`),
  ].join("\n");
}

function desiredSocialSignalTargets(totalCount) {
  const minimum = totalCount > 1 ? 1 : 0;

  return {
    hookFormTarget: totalCount > 1 ? Math.max(2, Math.min(3, totalCount || 1)) : 1,
    frontLoadedMin: Math.max(1, Math.min(totalCount || 1, 2)),
    noveltyMin: 1,
    relatabilityMin: totalCount > 1 ? 1 : 0,
    recognitionMin: totalCount > 1 ? 1 : 0,
    conversationMin: totalCount > 1 ? 1 : 0,
    savvyMin: totalCount > 1 ? 1 : 0,
    identityShiftMin: totalCount > 1 ? 1 : 0,
    proofMin: totalCount > 1 ? 1 : 0,
    actionabilityMin: totalCount > 1 ? 1 : 0,
    immediacyMin: totalCount > 1 ? 1 : 0,
    consequenceMin: totalCount > 1 ? 1 : 0,
    habitShiftMin: totalCount > 1 ? 1 : 0,
    focusMin: totalCount > 1 ? 1 : 0,
    promiseSyncMin: totalCount > 0 ? 1 : 0,
    scannableMin: totalCount > 1 ? 1 : 0,
    twoStepMin: totalCount > 1 ? 1 : 0,
    curiosityMin: totalCount > 1 ? 1 : 0,
    resolutionMin: totalCount > 1 ? 1 : 0,
    contrastMin: totalCount > 1 ? 1 : 0,
    painPointMin: minimum,
    payoffMin: minimum,
  };
}

function findBestSocialCandidateIndex(candidates, article, contentType, desiredAngle, usedFingerprints, usedHookFingerprints, usedCaptionOpenings, selectionState = null) {
  const normalizedDesiredAngle = normalizeAngleKey(desiredAngle || "", contentType);
  const articleTitle = cleanText(article?.title || "");
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const articleContext = articleSignals.context_text;
  let bestIndex = -1;
  let bestScore = -Infinity;

  for (let index = 0; index < candidates.length; index += 1) {
    const candidate = candidates[index];
    if (!candidate) {
      continue;
    }

    const fingerprint = normalizeSocialFingerprint(candidate);
    const hookFingerprint = normalizeHookFingerprint(candidate);
    const captionOpeningFingerprint = normalizeCaptionOpeningFingerprint(candidate);
    let score = scoreSocialVariant(candidate, articleTitle, contentType, articleContext, articleSignals);
    const candidateSpecificity = socialVariantSpecificityScore(candidate, articleSignals);
    const slotIndex = Math.max(0, Number(selectionState?.slotIndex || 0));
    const remainingSlots = Math.max(1, Number(selectionState?.remainingSlots || 1));

    if (!fingerprint) {
      score -= 50;
    }
    if (fingerprint && usedFingerprints.has(fingerprint)) {
      score -= 25;
    }
    if (hookFingerprint && usedHookFingerprints.has(hookFingerprint)) {
      score -= 18;
    }
    if (captionOpeningFingerprint && usedCaptionOpenings.has(captionOpeningFingerprint)) {
      score -= 14;
    }

    const candidateHookForm = classifySocialHookForm(candidate);
    const usedHookForms = selectionState?.usedHookForms instanceof Set ? selectionState.usedHookForms : new Set();
    const hookFormTarget = Math.max(1, Number(selectionState?.targets?.hookFormTarget || 1));

    const candidateAngle = normalizeAngleKey(candidate?.angle_key || "", contentType);
    if (normalizedDesiredAngle && candidateAngle === normalizedDesiredAngle) {
      score += slotIndex === 0 ? 5 : 8;
    } else if (normalizedDesiredAngle && candidateAngle !== "") {
      score -= slotIndex === 0 ? 1 : 3;
    }

    const candidatePain = socialVariantPainPointSignal(candidate, articleSignals);
    const candidatePayoff = socialVariantPayoffSignal(candidate, articleSignals);
    const candidateCuriosity = socialVariantCuriositySignal(candidate, articleSignals);
    const candidateResolved = socialVariantResolvesEarly(candidate, articleSignals, contentType);
    const candidateContrast = socialVariantContrastSignal(candidate, articleSignals);
    const candidateNovelty = socialVariantNoveltyScore(candidate, articleTitle, articleSignals) >= 2;
    const candidateRelatable = socialVariantRelatabilitySignal(candidate, articleSignals, contentType);
    const candidateRecognition = socialVariantSelfRecognitionSignal(candidate, articleSignals, contentType);
    const candidateConversation = socialVariantConversationSignal(candidate, articleSignals, contentType);
    const candidateSavvy = socialVariantSavvySignal(candidate, articleSignals, contentType);
    const candidateIdentityShift = socialVariantIdentityShiftSignal(candidate, articleSignals, contentType);
    const candidateProof = socialVariantProofSignal(candidate, articleSignals, contentType);
    const candidateActionable = socialVariantActionabilitySignal(candidate, articleSignals, contentType);
    const candidateImmediacy = socialVariantImmediacySignal(candidate, articleSignals, contentType);
    const candidateConsequence = socialVariantConsequenceSignal(candidate, articleSignals, contentType);
    const candidateHabitShift = socialVariantHabitShiftSignal(candidate, articleSignals, contentType);
    const candidateFocused = socialVariantPromiseFocusSignal(candidate, articleSignals, contentType);
    const candidatePromiseSync = socialVariantPromiseSyncSignal(candidate, articleTitle, articleSignals, contentType);
    const candidateScannable = socialVariantScannabilitySignal(candidate, contentType);
    const candidateTwoStep = socialVariantTwoStepSignal(candidate, articleSignals, contentType);
    const candidateFrontLoaded = frontLoadedClickSignalScore(candidate?.hook || "", contentType) > 0;
    const frontLoadedNeeded = Math.max(0, Number(selectionState?.targets?.frontLoadedMin || 0) - Number(selectionState?.frontLoadedCount || 0));
    const noveltyNeeded = Math.max(0, Number(selectionState?.targets?.noveltyMin || 0) - Number(selectionState?.noveltyCount || 0));
    const relatabilityNeeded = Math.max(0, Number(selectionState?.targets?.relatabilityMin || 0) - Number(selectionState?.relatableCount || 0));
    const recognitionNeeded = Math.max(0, Number(selectionState?.targets?.recognitionMin || 0) - Number(selectionState?.recognitionCount || 0));
    const conversationNeeded = Math.max(0, Number(selectionState?.targets?.conversationMin || 0) - Number(selectionState?.conversationCount || 0));
    const savvyNeeded = Math.max(0, Number(selectionState?.targets?.savvyMin || 0) - Number(selectionState?.savvyCount || 0));
    const identityShiftNeeded = Math.max(0, Number(selectionState?.targets?.identityShiftMin || 0) - Number(selectionState?.identityShiftCount || 0));
    const proofNeeded = Math.max(0, Number(selectionState?.targets?.proofMin || 0) - Number(selectionState?.proofCount || 0));
    const actionabilityNeeded = Math.max(0, Number(selectionState?.targets?.actionabilityMin || 0) - Number(selectionState?.actionableCount || 0));
    const immediacyNeeded = Math.max(0, Number(selectionState?.targets?.immediacyMin || 0) - Number(selectionState?.immediacyCount || 0));
    const consequenceNeeded = Math.max(0, Number(selectionState?.targets?.consequenceMin || 0) - Number(selectionState?.consequenceCount || 0));
    const habitShiftNeeded = Math.max(0, Number(selectionState?.targets?.habitShiftMin || 0) - Number(selectionState?.habitShiftCount || 0));
    const focusNeeded = Math.max(0, Number(selectionState?.targets?.focusMin || 0) - Number(selectionState?.focusedCount || 0));
    const promiseSyncNeeded = Math.max(0, Number(selectionState?.targets?.promiseSyncMin || 0) - Number(selectionState?.promiseSyncCount || 0));
    const scannableNeeded = Math.max(0, Number(selectionState?.targets?.scannableMin || 0) - Number(selectionState?.scannableCount || 0));
    const twoStepNeeded = Math.max(0, Number(selectionState?.targets?.twoStepMin || 0) - Number(selectionState?.twoStepCount || 0));
    const curiosityNeeded = Math.max(0, Number(selectionState?.targets?.curiosityMin || 0) - Number(selectionState?.curiosityCount || 0));
    const resolutionNeeded = Math.max(0, Number(selectionState?.targets?.resolutionMin || 0) - Number(selectionState?.resolutionCount || 0));
    const contrastNeeded = Math.max(0, Number(selectionState?.targets?.contrastMin || 0) - Number(selectionState?.contrastCount || 0));
    const painNeeded = Math.max(0, Number(selectionState?.targets?.painPointMin || 0) - Number(selectionState?.painPointCount || 0));
    const payoffNeeded = Math.max(0, Number(selectionState?.targets?.payoffMin || 0) - Number(selectionState?.payoffCount || 0));

    if (frontLoadedNeeded > 0) {
      if (candidateFrontLoaded) {
        score += remainingSlots <= frontLoadedNeeded ? 8 : 4;
      } else if (remainingSlots <= frontLoadedNeeded) {
        score -= 8;
      }
    }

    if (curiosityNeeded > 0) {
      if (candidateCuriosity) {
        score += remainingSlots <= curiosityNeeded ? 6 : 3;
      } else if (remainingSlots <= curiosityNeeded) {
        score -= 6;
      }
    }

    if (resolutionNeeded > 0) {
      if (candidateResolved) {
        score += remainingSlots <= resolutionNeeded ? 7 : 3;
      } else if (remainingSlots <= resolutionNeeded) {
        score -= 7;
      }
    }

    if (noveltyNeeded > 0) {
      if (candidateNovelty) {
        score += remainingSlots <= noveltyNeeded ? 7 : 3;
      } else if (remainingSlots <= noveltyNeeded) {
        score -= 7;
      }
    }

    if (relatabilityNeeded > 0) {
      if (candidateRelatable) {
        score += remainingSlots <= relatabilityNeeded ? 6 : 3;
      } else if (remainingSlots <= relatabilityNeeded) {
        score -= 6;
      }
    }

    if (recognitionNeeded > 0) {
      if (candidateRecognition) {
        score += remainingSlots <= recognitionNeeded ? 7 : 3;
      } else if (remainingSlots <= recognitionNeeded) {
        score -= 7;
      }
    }

    if (conversationNeeded > 0) {
      if (candidateConversation) {
        score += remainingSlots <= conversationNeeded ? 6 : 3;
      } else if (remainingSlots <= conversationNeeded) {
        score -= 6;
      }
    }

    if (savvyNeeded > 0) {
      if (candidateSavvy) {
        score += remainingSlots <= savvyNeeded ? 6 : 3;
      } else if (remainingSlots <= savvyNeeded) {
        score -= 6;
      }
    }

    if (identityShiftNeeded > 0) {
      if (candidateIdentityShift) {
        score += remainingSlots <= identityShiftNeeded ? 6 : 3;
      } else if (remainingSlots <= identityShiftNeeded) {
        score -= 6;
      }
    }

    if (proofNeeded > 0) {
      if (candidateProof) {
        score += remainingSlots <= proofNeeded ? 6 : 3;
      } else if (remainingSlots <= proofNeeded) {
        score -= 6;
      }
    }

    if (actionabilityNeeded > 0) {
      if (candidateActionable) {
        score += remainingSlots <= actionabilityNeeded ? 6 : 3;
      } else if (remainingSlots <= actionabilityNeeded) {
        score -= 6;
      }
    }

    if (immediacyNeeded > 0) {
      if (candidateImmediacy) {
        score += remainingSlots <= immediacyNeeded ? 6 : 3;
      } else if (remainingSlots <= immediacyNeeded) {
        score -= 6;
      }
    }

    if (consequenceNeeded > 0) {
      if (candidateConsequence) {
        score += remainingSlots <= consequenceNeeded ? 6 : 3;
      } else if (remainingSlots <= consequenceNeeded) {
        score -= 6;
      }
    }

    if (habitShiftNeeded > 0) {
      if (candidateHabitShift) {
        score += remainingSlots <= habitShiftNeeded ? 7 : 3;
      } else if (remainingSlots <= habitShiftNeeded) {
        score -= 7;
      }
    }

    if (focusNeeded > 0) {
      if (candidateFocused) {
        score += remainingSlots <= focusNeeded ? 6 : 3;
      } else if (remainingSlots <= focusNeeded) {
        score -= 6;
      }
    }

    if (promiseSyncNeeded > 0) {
      if (candidatePromiseSync) {
        score += remainingSlots <= promiseSyncNeeded ? 7 : 3;
      } else if (remainingSlots <= promiseSyncNeeded) {
        score -= 7;
      }
    }

    if (scannableNeeded > 0) {
      if (candidateScannable) {
        score += remainingSlots <= scannableNeeded ? 6 : 3;
      } else if (remainingSlots <= scannableNeeded) {
        score -= 6;
      }
    }

    if (twoStepNeeded > 0) {
      if (candidateTwoStep) {
        score += remainingSlots <= twoStepNeeded ? 6 : 3;
      } else if (remainingSlots <= twoStepNeeded) {
        score -= 6;
      }
    }

    if (contrastNeeded > 0) {
      if (candidateContrast) {
        score += remainingSlots <= contrastNeeded ? 6 : 3;
      } else if (remainingSlots <= contrastNeeded) {
        score -= 6;
      }
    }

    if (painNeeded > 0) {
      if (candidatePain) {
        score += remainingSlots <= painNeeded ? 9 : 4;
      } else if (remainingSlots <= painNeeded) {
        score -= 8;
      }
    }

    if (payoffNeeded > 0) {
      if (candidatePayoff) {
        score += remainingSlots <= payoffNeeded ? 9 : 4;
      } else if (remainingSlots <= payoffNeeded) {
        score -= 8;
      }
    }

    if (candidatePain && candidatePayoff && (painNeeded > 0 || payoffNeeded > 0)) {
      score += 1;
    }

    if ((candidateCuriosity || candidateContrast) && !candidateResolved) {
      score -= 4;
    }

    if (slotIndex > 0 && painNeeded === 0 && payoffNeeded === 0) {
      const painLead = Number(selectionState?.painPointCount || 0) - Number(selectionState?.payoffCount || 0);
      const payoffLead = Number(selectionState?.payoffCount || 0) - Number(selectionState?.painPointCount || 0);
      if (candidatePain && painLead >= 1 && !candidatePayoff) {
        score -= 3;
      }
      if (candidatePayoff && payoffLead >= 1 && !candidatePain) {
        score -= 3;
      }
    }

    if (slotIndex > 0 && candidateCuriosity && Number(selectionState?.curiosityCount || 0) >= Math.max(1, Math.floor((slotIndex + 1) / 2))) {
      score -= 2;
    }

    if (candidateHookForm) {
      if (!usedHookForms.has(candidateHookForm) && usedHookForms.size < hookFormTarget) {
        score += 4;
      } else if (usedHookForms.has(candidateHookForm) && usedHookForms.size < hookFormTarget && remainingSlots <= Math.max(1, hookFormTarget - usedHookForms.size)) {
        score -= 5;
      } else if (slotIndex > 0 && usedHookForms.has(candidateHookForm)) {
        score -= 1;
      }
    }

    if (slotIndex === 0) {
      if (score >= 18) {
        score += 6;
      } else if (score >= 14) {
        score += 3;
      }
      if (candidateSpecificity >= 3) {
        score += 2;
      } else if (candidateSpecificity < 2) {
        score -= 5;
      }
      if (candidateFrontLoaded) {
        score += 2;
      }
      if ((candidateCuriosity || candidateContrast) && candidateResolved) {
        score += 2;
      }
      if (!candidatePain && !candidatePayoff) {
        score -= 6;
      }
      if (!candidateConsequence && !candidateProof && !candidateActionable) {
        score -= 3;
      }
      if (!candidateHabitShift && !candidateContrast) {
        score -= 2;
      }
      if (!candidateFocused) {
        score -= 4;
      }
      if (candidateScannable) {
        score += 2;
      } else {
        score -= 3;
      }
      if (candidateRecognition) {
        score += 2;
      }
      if (candidateSavvy) {
        score += 2;
      }
      if (candidateIdentityShift) {
        score += 2;
      }
      if (candidateTwoStep) {
        score += 2;
      } else {
        score -= 3;
      }
    }

    if (score > bestScore) {
      bestScore = score;
      bestIndex = index;
    }
  }

  return bestIndex;
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

function buildPublicationInvariantLines(settings) {
  const profile = resolvePublicationProfile(settings);

  return [
    `Publication profile: ${profile.name || settings.siteName}`,
    `Publication role: ${profile.role || "You are the lead editorial writer for the publication."}`,
    `Voice brief: ${profile.voice_brief || settings.brandVoice}`,
    `Hard guardrails: ${profile.guardrails || "No fake personal stories, no filler SEO intros, no spammy clickbait, no generic opening filler, and no unsupported health or nutrition claims."}`,
    "Output discipline: stay publish-ready, specific, and honest. Do not drift into filler, fake memoir, or schema mistakes.",
  ];
}

function buildSocialCreativeBrief(article, contentType = "recipe") {
  const signals = buildArticleSocialSignals(article, contentType);
  const lines = [];

  const pushLine = (label, value) => {
    const text = trimText(cleanText(value || ""), 150);
    if (!text) {
      return;
    }

    const fingerprint = normalizeSocialLineFingerprint(text);
    const alreadyUsed = lines
      .map((entry) => normalizeSocialLineFingerprint(entry.split(":").slice(1).join(":") || entry))
      .filter(Boolean);
    if (!fingerprint || alreadyUsed.includes(fingerprint)) {
      return;
    }

    lines.push(`${label}: ${text}`);
  };

  pushLine("Core payoff", signals.summary_line);
  pushLine("Pain point", signals.pain_line);
  pushLine("Reader recognition", signals.pain_line || signals.consequence_line);
  pushLine("Smarter move", [signals.detail_line, signals.payoff_line || signals.proof_line].filter(Boolean).join(" "));
  pushLine("Identity shift", [signals.consequence_line, signals.payoff_line || signals.detail_line].filter(Boolean).join(" "));
  pushLine("Why it matters now", signals.consequence_line);
  pushLine("Concrete payoff", signals.payoff_line);
  pushLine("Habit shift", [signals.consequence_line, signals.payoff_line].filter(Boolean).join(" "));
  pushLine("Specific proof", signals.proof_line);
  pushLine("Useful detail", signals.detail_line);
  pushLine("Later-page tease", signals.page_signal_line);
  pushLine("Final reward", signals.final_reward_line);
  pushLine("Topic focus", signals.heading_topic);
  pushLine("Ingredient focus", signals.ingredient_focus);
  pushLine("Meta", signals.meta_line);

  return lines;
}

function buildCoreArticlePrompt(job, settings, repairNote = "") {
  const preset = resolveContentPreset(settings, job.content_type);
  const articleGuidance = resolveTypedGuidance(settings, "article", job.content_type, preset.guidance || "");
  const presetGuidance = cleanMultilineText(preset.guidance || "");
  const normalizedArticleGuidance = cleanMultilineText(articleGuidance || "");
  const internalLinkLibrary = internalLinkTargetsForJob(job)
    .map((item) => `- ${item.label}: [kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
    .join("\n");

  const contentTypeNotes = {
    recipe: [
      "Write a recipe-led article with strong sensory writing and practical kitchen guidance.",
      "Do not place the full ingredients and method inside the article body; the structured recipe box will render them separately.",
      "Use H2 sections for: why this works, ingredient notes, practical cooking method, and serving or storage guidance.",
      "Page flow: page 1 should hook the reader and establish the payoff, page 2 should deepen the method and value, and page 3, if used, should feel earned and lead naturally into the recipe card on the final page.",
      "The recipe object must include prep_time, cook_time, total_time, yield, ingredients[], and instructions[].",
    ],
    food_fact: [
      "Write a fact-led article that answers the kitchen question directly, corrects confusion, explains why it matters, and gives a practical takeaway.",
      "Use H2 sections for: the direct answer, what is happening, a common mistake, and a practical takeaway.",
      "Page flow: page 1 should deliver the direct answer fast, page 2 should explain the mechanism or mistake, and page 3, if used, should land the practical takeaway cleanly.",
      "The recipe object must contain empty strings and empty arrays.",
    ],
    food_story: [
      "Write a publication-voice kitchen essay with a clear narrative arc, practical food insight, and a reflective close.",
      "Do not write fabricated first-person memories, invented reporting, or autobiography.",
      "Use H2 sections for: the central observation, practical meaning in home cooking, and a reflective close.",
      "Page flow: each page should earn its place and move the essay forward instead of feeling artificially split.",
      "The recipe object must contain empty strings and empty arrays.",
    ],
  };

  return [
    ...buildPublicationInvariantLines(settings),
    `Topic: ${job.topic}`,
    `Content type: ${job.content_type}`,
    presetGuidance ? `Content standard: ${presetGuidance}` : "",
    normalizedArticleGuidance && normalizedArticleGuidance !== presetGuidance ? `Article structure guidance: ${normalizedArticleGuidance}` : "",
    `Length target: ${preset.min_words || config.minWords}-${config.maxWords} words for the main article body.`,
    `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
    job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate a strong, editorial article title.",
    "Article rules:",
    "- Write original, useful, human-sounding content.",
    "- Use a polished magazine tone, not generic SEO filler or padded introductions.",
    "- Do not mention AI or say the article was generated.",
    "- Prefer clarity over cleverness in the title. Make the title signal a concrete payoff, mistake, shortcut, question answered, or useful outcome.",
    "- Strong titles and openings can use honest contrast: common assumption versus useful truth, effort versus payoff, or mistake versus fix. Keep that contrast concrete, not sensational.",
    "- Front-load the useful words. In the first few words of the title, excerpt, SEO description, and opening paragraph, name the concrete problem, payoff, shortcut, question, or outcome whenever possible.",
    "- Title, excerpt, SEO description, and the opening paragraph must each do a different job instead of repeating each other.",
    "- Each page must be clean WordPress-ready HTML using paragraphs, h2 headings, lists, and blockquotes only.",
    "- The opening page must begin with a concrete introduction paragraph.",
    "- The opening paragraph must be concrete and specific, not generic throat-clearing.",
    "- Page 1 should cash the promise of the title quickly instead of delaying the real payoff.",
    "- excerpt should feel distinct, specific, and useful, not like a restatement of the title. Front-load one concrete detail, problem, or payoff.",
    "- seo_description should sound like a natural search snippet that adds one clear concrete reason to click. Front-load that reason instead of burying it.",
    "- seo_description should stay under 155 characters.",
    "- Return image_prompt and image_alt for the article hero image even if real images are already uploaded.",
    "- Include at least three internal Kuchnia Twist links across content_pages.",
    "- Avoid copy like 'when it comes to', 'in today's world', 'this article explores', or other generic filler openings.",
    "Pagination rules:",
    "- Return 2 or 3 article pages in content_pages. Use 2 pages for tighter topics and 3 only when every page earns its place.",
    "- Keep the pages in natural reading order. Do not include <!--nextpage--> markers yourself.",
    "- Every page must have one dominant job and one clear takeaway. Do not create a filler bridge page.",
    "- Prefer natural H2-led breakpoints that can survive same-post pagination cleanly.",
    "- When pages 2 or 3 open with H2 headings, make those headings short, specific, and strong enough to work as page labels.",
    "- If you use 3 pages, page 2 should be the deepest or most useful page, not a bridge.",
    "- If you use 3 pages, page 3 must still feel earned with a real section and enough substance to reward the click.",
    "- Do not dump a few leftover notes onto a thin final page.",
    "- Pages 2 and 3 should open with a strong H2 or a distinct section lead so the page labels feel intentional.",
    "- Return page_flow with one item per page. Each item needs a short label and a one-sentence summary that make the next click feel worth it.",
    "- page_flow labels should read like editorial chapter names, not generic copy like 'Page 2', 'Continue', or 'Next page'.",
    "- page_flow summaries should preview concrete value or curiosity from the page instead of restating the label.",
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
    '  "content_pages": ["html-string"],',
    '  "page_flow": [{"label":"string","summary":"string"}],',
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
  ]
    .filter(Boolean)
    .join("\n");
}

function buildRecipeMasterPrompt(job, settings, selectedPages, repairNote = "") {
  const preset = resolveContentPreset(settings, "recipe");
  const selectedLabels = selectedPages.map((page) => page.label).filter(Boolean);
  const socialPackCount = Math.max(1, selectedLabels.length || 1);
  const preferredAngle = resolvePreferredAngle(job);
  const angleSequence = buildAngleSequence(socialPackCount, "recipe", preferredAngle);
  const pageAnglePlan = buildPageAnglePlan(selectedPages, "recipe", preferredAngle);
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
    ...buildPublicationInvariantLines(settings),
    `Dish name: ${job.topic}`,
    `Recipe master direction: ${masterPrompt}`,
    `Recipe content standard: ${preset.guidance || ""}`,
    `Recipe article guidance: ${resolveTypedGuidance(settings, "article", "recipe", "")}`,
    `Recipe social guidance: ${resolveTypedGuidance(settings, "facebook_caption", "recipe", "")}`,
    `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
    job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate the best article title yourself.",
    `Create exactly ${socialPackCount} social variants in social_pack.`,
    selectedLabels.length ? `Target Facebook pages: ${selectedLabels.join(" | ")}` : "Target Facebook pages: Primary Facebook page",
    preferredAngle ? `Use ${preferredAngle} as the first social angle, then rotate the remaining angles distinctly.` : "Auto-rotate distinct Facebook angles across the selected pages.",
    `Image asset status: ${imageAssetPlan}`,
    "Social angle library:",
    ...angleDefinitionsForType("recipe").map((angle) => `- ${angle.key}: ${angle.instruction}`),
    `Use these angle keys in order when possible: ${angleSequence.join(", ")}`,
    "Variant-to-page assignment:",
    ...pageAnglePlan.map((item) => `- social_pack[${item.index}] -> ${item.page_label} -> ${item.angle_key}: ${item.instruction}`),
    "Legacy compatibility rules:",
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

function buildSocialCandidatePrompt(job, settings, article, selectedPages, preferredAngle = "", repairNote = "") {
  const preset = resolveContentPreset(settings, job.content_type);
  const socialGuidance = resolveTypedGuidance(settings, "facebook_caption", job.content_type, "");
  const anglePlan = buildPageAnglePlan(selectedPages, job.content_type || "recipe", preferredAngle);
  const candidateCount = Math.max(8, Math.min(12, Math.max(selectedPages.length * 2, 8)));
  const socialBrief = buildSocialCreativeBrief(article, job.content_type || "recipe");

  return [
    ...buildPublicationInvariantLines(settings),
    `Content type: ${job.content_type}`,
    `Content standard: ${preset.guidance || ""}`,
    `Facebook caption guidance: ${socialGuidance}`,
    `Article title: ${article.title}`,
    `Excerpt: ${article.excerpt}`,
    ...socialBrief,
    `Generate ${candidateCount} social_candidates and make them meaningfully distinct.`,
    "Use a mix of creativity modes across the pool: direct payoff, sharp correction, practical shortcut, and emotionally clear curiosity.",
    "Use a wider mix of hook shapes across the pool too: direct statement, clean correction, contrast, useful question, and numbered form only when it genuinely fits.",
    "Include some candidates that use clean contrast such as expectation versus reality, mistake versus fix, or effort versus payoff without sounding gimmicky.",
    "Use concrete article details so the strongest candidates do not read like title-only hooks.",
    "Prefer hooks that name a real payoff, mistake, shortcut, timing, texture, ingredient, or outcome instead of vague emotional filler.",
    "Name the actual dish, ingredient, method, mistake, or topic often enough that the pool does not lean on vague 'this' or 'it' framing.",
    "Make sure some candidates feel instantly self-recognizable, like a real kitchen moment, shopping moment, weeknight problem, or home-cook decision the reader has actually lived.",
    "Make some candidates feel naturally discussable or tag-worthy because they touch a real household habit, shopping split, or recognizable choice, not because they ask for comments or tags.",
    "Let some candidates carry a small believable proof or clue early, like timing, texture, ingredient job, shopping detail, or a before-versus-after result.",
    "Make some candidates feel immediately usable, with a clear next move, kitchen decision, or practical thing the reader can do on the next cook or shop.",
    "Make some candidates feel relevant right now, tied to tonight, this week, the next grocery run, or the reader's next cook, shop, order, or storage decision.",
    "Make some candidates show consequence honestly: what gets wasted, repeated, ruined, overcomplicated, or missed if the reader keeps following the usual habit.",
    "Keep each candidate built around one dominant promise. Do not cram too many separate benefits, mistakes, textures, and outcomes into one hook-caption pair.",
    "Make some candidates line up tightly with the article title and page-one promise, but do it by cashing the same core problem or payoff, not by repeating the headline.",
    "Keep the caption visually easy to scan. Favor short distinct lines over dense lines that all say nearly the same thing.",
    "Make caption line 1 and line 2 do different jobs. Let line 1 give the clue, problem, proof, or sharp correction, and let line 2 sharpen the payoff, use, or result instead of repeating line 1 in softer words.",
    "Make several candidates front-load a pain point, mistake, or misunderstanding, and make several others front-load a clear payoff or result.",
    "In the first 4 to 6 words of the hook, try to surface the concrete problem, payoff, shortcut, or surprise instead of warming up with vague filler.",
    "Use the page-flow signals when useful so stronger candidates can hint at later-page payoff without mentioning pagination or page numbers.",
    "Make some candidates create a real habit-shift snap: name the usual move, assumption, or kitchen habit and make the better result feel concrete without sounding preachy or dramatic.",
    "Make some candidates create instant self-recognition by naming the repeated flat result, mistake, or kitchen symptom the reader already knows from experience.",
    "Make some candidates feel like the reader is about to make a smarter kitchen or shopping move, but keep it grounded, practical, and never smug or superior.",
    "Make some candidates feel like the reader is leaving behind the old default move for a cleaner better one, but keep it honest and never shame the reader.",
    "If a hook opens a question, contradiction, or curiosity gap, the caption must resolve part of it by line 1 or 2.",
    "Never withhold the core fact for pure suspense.",
    "Angle library for this content type:",
    ...angleDefinitionsForType(job.content_type || "recipe").map((angle) => `- ${angle.key}: ${angle.instruction}`),
    anglePlan.length ? "Selected page plan:" : "",
    ...anglePlan.map((item) => `- ${item.page_label}: prefer ${item.angle_key}`),
    repairNote ? `Previous social pool failed local review: ${repairNote}` : "",
    "Return only this JSON contract:",
    "{",
    '  "social_candidates": [{"angle_key":"string","hook":"string","caption":"string","cta_hint":"string","post_message":"string"}]',
    "}",
    "Each candidate must be honest, specific, short, and hook-led.",
    "At least half the pool should feel sharper than a generic social post while still staying honest and specific.",
    "At least two thirds of the pool should carry a tangible reason to care such as payoff, shortcut, texture, timing, mistake, or outcome.",
    "No links, hashtags, or title-echo hooks.",
    "Do not mention pagination, page numbers, 'next page', or 'keep reading' inside the social copy.",
    "Avoid generic hooks like 'you need to try this', 'you should', 'this is', 'this one', 'so good', 'best ever', or 'game changer'.",
    "Avoid cheap suspense like 'what happens next', 'nobody tells you', 'the secret', or 'you'll never guess'.",
    "Do not repeat the hook as the first caption line.",
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

function normalizeSocialPack(value, contentType = "") {
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
      const angleKey = normalizeAngleKey(item.angle_key || item.angleKey || "", contentType);

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

function extractGeneratedPageLabel(pageHtml, fallback = "") {
  const html = String(pageHtml || "");
  const heading = cleanHeadingTopic(extractFirstHeading(html));
  if (heading) {
    return trimWords(heading, 8);
  }

  const paragraphMatch = html.match(/<p\b[^>]*>(.*?)<\/p>/i);
  if (paragraphMatch?.[1]) {
    const lead = firstSentence(String(paragraphMatch[1]).replace(/<[^>]+>/g, " "), 90);
    if (lead) {
      return trimWords(lead, 8);
    }
  }

  const plaintext = trimText(cleanText(html.replace(/<[^>]+>/g, " ")), 90);
  return trimWords(plaintext || cleanText(fallback), 8);
}

function extractGeneratedPageSummary(pageHtml, fallback = "") {
  const html = String(pageHtml || "");
  const paragraphMatch = html.match(/<p\b[^>]*>(.*?)<\/p>/i);
  if (paragraphMatch?.[1]) {
    const paragraph = cleanText(String(paragraphMatch[1]).replace(/<[^>]+>/g, " "));
    if (paragraph) {
      const parts = paragraph.split(/(?<=[.!?])\s+/).filter(Boolean);
      const summary = trimText(cleanText(parts[1] || parts[0] || paragraph), 150);
      if (summary) {
        return summary;
      }
    }
  }

  return firstSentence(html.replace(/<[^>]+>/g, " "), 150) || cleanText(fallback);
}

function normalizePageFlowLabelFingerprint(value) {
  return cleanText(
    String(value || "")
      .replace(/^(page|part|section|step)\s+\d+\s*[:.)-]?\s*/i, "")
      .replace(/[^a-z0-9\s]/gi, " "),
  )
    .toLowerCase()
    .replace(/\s+/g, " ")
    .trim();
}

function pageFlowLabelLooksStrong(label, index = 0) {
  const text = cleanText(label || "");
  const fallbackLabel = `Page ${index + 1}`;
  const fingerprint = normalizePageFlowLabelFingerprint(text || fallbackLabel);
  if (!fingerprint) {
    return false;
  }

  const wordCount = fingerprint.split(/\s+/).filter(Boolean).length;
  if (wordCount < 2 || fingerprint.length < 8) {
    return false;
  }

  return !/^(page|part|section|continue|next page|keep reading|read more)\b/i.test(text);
}

function pageFlowSummaryLooksStrong(summary, label = "") {
  const text = cleanText(summary || "");
  if (!text) {
    return false;
  }

  const summaryFingerprint = normalizePageFlowLabelFingerprint(text);
  const labelFingerprint = normalizePageFlowLabelFingerprint(label);
  const wordCount = summaryFingerprint.split(/\s+/).filter(Boolean).length;
  if (wordCount < 6) {
    return false;
  }
  if (labelFingerprint && summaryFingerprint === labelFingerprint) {
    return false;
  }

  return !/^(page|part)\s+\d+\b|^(keep reading|continue reading|read more|next up)\b/i.test(text);
}

function derivePageFlowLabelFromSummary(summary, fallback = "") {
  const source = cleanText(summary || fallback || "");
  if (!source) {
    return "";
  }

  return trimWords(firstSentence(source, 90), 8);
}

function buildGeneratedPageFlow(contentPages) {
  return (Array.isArray(contentPages) ? contentPages : [])
    .map((page, index) => {
      const html = String(page || "");
      if (!cleanText(html.replace(/<[^>]+>/g, " "))) {
        return null;
      }

      return {
        index: index + 1,
        label: extractGeneratedPageLabel(html, `Page ${index + 1}`),
        summary: extractGeneratedPageSummary(html, ""),
      };
    })
    .filter(Boolean);
}

function normalizeGeneratedPageFlow(value, contentPages) {
  const fallback = buildGeneratedPageFlow(contentPages);
  if (!Array.isArray(value) || !value.length) {
    return fallback;
  }

  const usedLabels = new Set();

  return fallback.map((page, index) => {
    const raw = value[index];
    const fallbackLabel = cleanText(page?.label || `Page ${index + 1}`);
    const fallbackSummary = cleanText(page?.summary || "");

    if (isPlainObject(raw)) {
      let label = trimWords(cleanText(raw.label || raw.title || raw.page_label || raw.pageLabel || ""), 8);
      let summary = trimText(cleanText(raw.summary || raw.page_summary || raw.pageSummary || raw.description || ""), 150);
      if (!pageFlowLabelLooksStrong(label, index)) {
        label = fallbackLabel;
      }
      if (!pageFlowSummaryLooksStrong(summary, label)) {
        summary = fallbackSummary;
      }

      let fingerprint = normalizePageFlowLabelFingerprint(label);
      const fallbackFingerprint = normalizePageFlowLabelFingerprint(fallbackLabel);
      if ((!fingerprint || usedLabels.has(fingerprint)) && fallbackFingerprint && !usedLabels.has(fallbackFingerprint)) {
        label = fallbackLabel;
        fingerprint = fallbackFingerprint;
      }

      if (!fingerprint || usedLabels.has(fingerprint)) {
        const derivedLabel = derivePageFlowLabelFromSummary(summary, fallbackSummary || fallbackLabel);
        const derivedFingerprint = normalizePageFlowLabelFingerprint(derivedLabel);
        if (derivedFingerprint && !usedLabels.has(derivedFingerprint) && pageFlowLabelLooksStrong(derivedLabel, index)) {
          label = derivedLabel;
          fingerprint = derivedFingerprint;
        }
      }

      if (!pageFlowSummaryLooksStrong(summary, label)) {
        summary = fallbackSummary;
      }
      if (!summary) {
        summary = fallbackSummary;
      }
      if (fingerprint) {
        usedLabels.add(fingerprint);
      }

      return {
        index: page.index,
        label: label || fallbackLabel,
        summary: summary || fallbackSummary,
      };
    }

    if (typeof raw === "string") {
      let label = trimWords(cleanText(raw), 8) || fallbackLabel;
      let fingerprint = normalizePageFlowLabelFingerprint(label);
      if ((!fingerprint || usedLabels.has(fingerprint)) && fallbackLabel) {
        label = fallbackLabel;
        fingerprint = normalizePageFlowLabelFingerprint(label);
      }
      if (!fingerprint || usedLabels.has(fingerprint) || !pageFlowLabelLooksStrong(label, index)) {
        const derivedLabel = derivePageFlowLabelFromSummary(fallbackSummary, fallbackLabel);
        const derivedFingerprint = normalizePageFlowLabelFingerprint(derivedLabel);
        if (derivedFingerprint && !usedLabels.has(derivedFingerprint) && pageFlowLabelLooksStrong(derivedLabel, index)) {
          label = derivedLabel;
          fingerprint = derivedFingerprint;
        }
      }
      if (fingerprint) {
        usedLabels.add(fingerprint);
      }

      return {
        index: page.index,
        label: label || fallbackLabel,
        summary: fallbackSummary,
      };
    }

    const fallbackFingerprint = normalizePageFlowLabelFingerprint(fallbackLabel);
    if (fallbackFingerprint) {
      usedLabels.add(fallbackFingerprint);
    }
    return page;
  });
}

function buildFallbackSocialPack(article, pages, settings, contentType = "recipe", preferredAngle = "") {
  const count = Math.max(1, pages.length || 1);
  const angles = buildAngleSequence(count, contentType, preferredAngle);
  const definitions = angleDefinitionsForType(contentType);
  const signals = buildArticleSocialSignals(article, contentType);
  const closers = {
    recipe: {
      quick_dinner: ["Would you make this tonight?", "Save this for a busy evening.", "This one is built for the weeknight rotation."],
      comfort_food: ["Save this for a comfort-food night.", "This is the kind of dinner you come back to.", "Would this hit the spot for you tonight?"],
      budget_friendly: ["Would you try it for a family meal?", "This is a strong one to keep in the low-stress rotation.", "Save this for a practical dinner that still feels good."],
      beginner_friendly: ["Would you cook this as a starter dinner?", "This is a good recipe to build confidence with.", "Save this if you want an easy kitchen win."],
      crowd_pleaser: ["Who would you make this for?", "This one is built for repeat requests.", "Save this for the next easy family dinner."],
      better_than_takeout: ["Would you skip takeout for this?", "This is the kind of fakeout takeaway people repeat.", "Save this for the night you want the payoff without delivery."],
    },
    food_fact: {
      myth_busting: ["Would this change how you think about it?", "Save this if you want the cleaner answer.", "This is the kind of kitchen truth worth keeping."],
      surprising_truth: ["Did you expect that?", "Save this for the next time it comes up in the kitchen.", "This changes the way the topic lands."],
      kitchen_mistake: ["Have you been doing this too?", "Save this so the mistake stops repeating.", "This one catches more cooks than it should."],
      smarter_shortcut: ["Would you use the simpler move instead?", "Save this for the next low-friction kitchen fix.", "This is a better shortcut than most people use."],
      what_most_people_get_wrong: ["Most people miss this part.", "Save this if you want the clearer version.", "This is the mistake worth fixing first."],
      ingredient_truth: ["This changes how the ingredient makes sense.", "Save this if you want the useful version, not the fuzzy one.", "This one explains more than the label ever does."],
      changes_how_you_cook_it: ["This changes the next time you cook it.", "Save this before your next kitchen round.", "This one earns a place in the mental file."],
      restaurant_vs_home: ["Home cooking works differently here.", "Save this if you want the realistic home-kitchen answer.", "This is where restaurant logic throws people off."],
    },
  };
  const recipeTemplates = {
    quick_dinner: (title, index, detail) => ({
      hook: `Busy nights need ${detail.hook_topic || title} instead of more drag.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "Fast enough for a real weeknight.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "Big payoff, clear steps, and no unnecessary drag.",
        closers.recipe.quick_dinner[index % closers.recipe.quick_dinner.length],
      ),
    }),
    comfort_food: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} brings comfort-food payoff instead of an all-night project.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "Cozy, rich, and built for the kind of dinner you actually want.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It feels indulgent without making the method harder.",
        closers.recipe.comfort_food[index % closers.recipe.comfort_food.length],
      ),
    }),
    budget_friendly: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} keeps dinner practical rather than feeling cheap.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "Simple ingredients, strong flavor, and no unnecessary extras.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "This one feels generous without making the grocery list harder.",
        closers.recipe.budget_friendly[index % closers.recipe.budget_friendly.length],
      ),
    }),
    beginner_friendly: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} is easier than it looks and still worth repeating.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "Approachable steps, clear detail, and a result that still feels impressive.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "This is the kind of recipe that builds confidence fast.",
        closers.recipe.beginner_friendly[index % closers.recipe.beginner_friendly.length],
      ),
    }),
    crowd_pleaser: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} is the kind of meal people ask for again.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "Easy to serve, easy to repeat, and hard to complain about.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It works when you want a meal that lands with everyone.",
        closers.recipe.crowd_pleaser[index % closers.recipe.crowd_pleaser.length],
      ),
    }),
    better_than_takeout: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} gives takeout payoff instead of the delivery wait.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "Big payoff, better control, and a home-kitchen method that actually works.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It gives you the restaurant-style hit without the delivery wait.",
        closers.recipe.better_than_takeout[index % closers.recipe.better_than_takeout.length],
      ),
    }),
  };
  const factTemplates = {
    myth_busting: (title, index, detail) => ({
      hook: `Most advice on ${detail.hook_topic || title} misses the useful detail.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "The common version of this advice is off.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "The useful answer is simpler and more practical in a real kitchen.",
        closers.food_fact.myth_busting[index % closers.food_fact.myth_busting.length],
      ),
    }),
    surprising_truth: (title, index, detail) => ({
      hook: `One kitchen detail changes how ${detail.hook_topic || title} lands.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "There is one detail that changes the whole takeaway.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "Once you see it clearly, the kitchen decision gets easier.",
        closers.food_fact.surprising_truth[index % closers.food_fact.surprising_truth.length],
      ),
    }),
    kitchen_mistake: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} hides a mistake people repeat all the time.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "The problem is common because the bad advice sounds reasonable.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "The fix is easier once you know what is actually happening.",
        closers.food_fact.kitchen_mistake[index % closers.food_fact.kitchen_mistake.length],
      ),
    }),
    smarter_shortcut: (title, index, detail) => ({
      hook: `The simpler move with ${detail.hook_topic || title} is better than the usual advice.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "There is a cleaner shortcut here.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It saves effort without watering down the result.",
        closers.food_fact.smarter_shortcut[index % closers.food_fact.smarter_shortcut.length],
      ),
    }),
    what_most_people_get_wrong: (title, index, detail) => ({
      hook: `Most people start ${detail.hook_topic || title} from the wrong assumption.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "The confusion usually begins with one bad assumption.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "Once that gets corrected, the rest of the topic makes more sense.",
        closers.food_fact.what_most_people_get_wrong[index % closers.food_fact.what_most_people_get_wrong.length],
      ),
    }),
    ingredient_truth: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} makes more sense when function matters more than hype.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "This is less about hype and more about function.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "The ingredient works a certain way, and that changes the result.",
        closers.food_fact.ingredient_truth[index % closers.food_fact.ingredient_truth.length],
      ),
    }),
    changes_how_you_cook_it: (title, index, detail) => ({
      hook: `One practical detail in ${detail.hook_topic || title} changes your next move instead of just the theory.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "The useful part is not just knowing the fact.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It is knowing how that fact changes your next cooking move.",
        closers.food_fact.changes_how_you_cook_it[index % closers.food_fact.changes_how_you_cook_it.length],
      ),
    }),
    restaurant_vs_home: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} works differently at home than people expect.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "A lot of the confusion comes from borrowing restaurant logic.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "Home kitchens need a more realistic answer.",
        closers.food_fact.restaurant_vs_home[index % closers.food_fact.restaurant_vs_home.length],
      ),
    }),
  };
  const templates = contentType === "recipe" ? recipeTemplates : factTemplates;

  return Array.from({ length: count }, (_, index) => {
    const page = pages[index] || null;
    const angleKey = angles[index] || definitions[index % definitions.length].key;
    const variant = (templates[angleKey] || Object.values(templates)[0])(article.title, index, signals);
    const angleLabel = angleDefinition(angleKey, contentType)?.label || angleKey.replace(/_/g, " ");
    const builtVariant = {
      id: `variant-${index + 1}`,
      angle_key: angleKey,
      hook: variant.hook,
      caption: variant.caption,
      cta_hint: page?.label ? `${angleLabel} angle on ${page.label}` : `General ${contentType} post`,
    };
    if (
      signals.detail_line &&
      socialVariantSpecificityScore(builtVariant, signals) < 2 &&
      sharedWordsRatio(`${builtVariant.hook} ${builtVariant.caption}`, signals.detail_line) < 0.1
    ) {
      const closerLine = cleanMultilineText(builtVariant.caption).split(/\r?\n/).filter(Boolean).slice(-1)[0] || "";
      builtVariant.caption = buildFallbackCaption(
        signals.detail_line,
        builtVariant.caption,
        signals.proof_line || signals.page_signal_line || signals.final_reward_line,
        closerLine,
      );
    }
    builtVariant.post_message = buildFacebookPostMessage(builtVariant, "");
    return builtVariant;
  });
}

function ensureSocialPackCoverage(value, pages, article, settings, contentType = "recipe", preferredAngle = "") {
  const desiredCount = Math.max(1, Array.isArray(pages) ? pages.length : 0);
  const normalized = normalizeSocialPack(value, contentType);
  const fallback = buildFallbackSocialPack(article, pages, settings, contentType, preferredAngle);
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const angleSequence = buildAngleSequence(desiredCount, contentType, preferredAngle);
  const signalTargets = desiredSocialSignalTargets(desiredCount);
  const unusedCandidates = [...normalized];
  const usedFingerprints = new Set();
  const usedHookFingerprints = new Set();
  const usedCaptionOpenings = new Set();
  const usedHookForms = new Set();
  let frontLoadedCount = 0;
  let noveltyCount = 0;
  let relatableCount = 0;
  let recognitionCount = 0;
  let conversationCount = 0;
  let savvyCount = 0;
  let identityShiftCount = 0;
  let proofCount = 0;
  let actionableCount = 0;
  let immediacyCount = 0;
  let consequenceCount = 0;
  let habitShiftCount = 0;
  let focusedCount = 0;
  let promiseSyncCount = 0;
  let scannableCount = 0;
  let twoStepCount = 0;
  let curiosityCount = 0;
  let resolutionCount = 0;
  let contrastCount = 0;
  let painPointCount = 0;
  let payoffCount = 0;

  return Array.from({ length: desiredCount }, (_, index) => {
    const desiredAngle = angleSequence[index];
    const bestCandidateIndex = findBestSocialCandidateIndex(
      unusedCandidates,
      article,
      contentType,
      desiredAngle,
      usedFingerprints,
      usedHookFingerprints,
      usedCaptionOpenings,
      {
        frontLoadedCount,
        noveltyCount,
        relatableCount,
        recognitionCount,
        conversationCount,
        savvyCount,
        identityShiftCount,
        proofCount,
        actionableCount,
        immediacyCount,
        consequenceCount,
        habitShiftCount,
        focusedCount,
        promiseSyncCount,
        scannableCount,
        twoStepCount,
        curiosityCount,
        resolutionCount,
        contrastCount,
        painPointCount,
        payoffCount,
        usedHookForms,
        targets: signalTargets,
        remainingSlots: desiredCount - index,
        slotIndex: index,
      },
    );
    const selectedCandidate = bestCandidateIndex >= 0 ? unusedCandidates.splice(bestCandidateIndex, 1)[0] : null;
    const base = selectedCandidate || fallback[index] || fallback[fallback.length - 1];
    let variant = {
      ...base,
      angle_key: desiredAngle,
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
      socialVariantLooksWeak(variant, article.title || "", contentType, articleSignals)
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
    if (classifySocialHookForm(variant)) {
      usedHookForms.add(classifySocialHookForm(variant));
    }
    if (socialVariantPainPointSignal(variant, articleSignals)) {
      painPointCount += 1;
    }
    if (socialVariantPayoffSignal(variant, articleSignals)) {
      payoffCount += 1;
    }
    if (socialVariantCuriositySignal(variant, articleSignals)) {
      curiosityCount += 1;
    }
    if (socialVariantResolvesEarly(variant, articleSignals, contentType)) {
      resolutionCount += 1;
    }
    if (socialVariantNoveltyScore(variant, article.title || "", articleSignals) >= 2) {
      noveltyCount += 1;
    }
    if (socialVariantRelatabilitySignal(variant, articleSignals, contentType)) {
      relatableCount += 1;
    }
    if (socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)) {
      recognitionCount += 1;
    }
    if (socialVariantConversationSignal(variant, articleSignals, contentType)) {
      conversationCount += 1;
    }
    if (socialVariantSavvySignal(variant, articleSignals, contentType)) {
      savvyCount += 1;
    }
    if (socialVariantIdentityShiftSignal(variant, articleSignals, contentType)) {
      identityShiftCount += 1;
    }
    if (socialVariantProofSignal(variant, articleSignals, contentType)) {
      proofCount += 1;
    }
    if (socialVariantActionabilitySignal(variant, articleSignals, contentType)) {
      actionableCount += 1;
    }
    if (socialVariantImmediacySignal(variant, articleSignals, contentType)) {
      immediacyCount += 1;
    }
    if (socialVariantConsequenceSignal(variant, articleSignals, contentType)) {
      consequenceCount += 1;
    }
    if (socialVariantHabitShiftSignal(variant, articleSignals, contentType)) {
      habitShiftCount += 1;
    }
    if (socialVariantPromiseFocusSignal(variant, articleSignals, contentType)) {
      focusedCount += 1;
    }
    if (socialVariantPromiseSyncSignal(variant, article.title || "", articleSignals, contentType)) {
      promiseSyncCount += 1;
    }
    if (socialVariantScannabilitySignal(variant, contentType)) {
      scannableCount += 1;
    }
    if (socialVariantTwoStepSignal(variant, articleSignals, contentType)) {
      twoStepCount += 1;
    }
    if (socialVariantContrastSignal(variant, articleSignals)) {
      contrastCount += 1;
    }
    if (frontLoadedClickSignalScore(variant?.hook || "", contentType) > 0) {
      frontLoadedCount += 1;
    }
    return variant;
  }).filter(Boolean);
}

function normalizeFacebookDistribution(value, contentType = "") {
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
              angle_key: normalizeAngleKey(page.angle_key || page.angleKey || "", contentType),
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
  const normalized = normalizeFacebookDistribution(distribution, job?.content_type || "recipe");
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

function summarizeArticleStage(generated, job) {
  const contentPackage = resolveCanonicalContentPackage(generated, job);
  const contentType = contentPackage.content_type || job.content_type || "recipe";
  const contentHtml = String(contentPackage.content_html || "");
  const contentPages = Array.isArray(contentPackage.content_pages) && contentPackage.content_pages.length
    ? contentPackage.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "")
    : splitHtmlIntoPages(contentHtml, contentType).slice(0, 3);
  const pageFlow = normalizeGeneratedPageFlow(Array.isArray(contentPackage.page_flow) ? contentPackage.page_flow : [], contentPages);
  const pageWordCounts = contentPages.map((page) => countWords(page.replace(/<[^>]+>/g, " ")));
  const pageCount = contentPages.length || 1;
  const shortestPageWords = pageWordCounts.length ? Math.min(...pageWordCounts) : 0;
  const strongPageOpenings = contentPages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;
  const uniquePageLabels = new Set(pageFlow.map((page) => normalizePageFlowLabelFingerprint(page?.label || "")).filter(Boolean));
  const strongPageLabels = pageFlow.filter((page, index) => pageFlowLabelLooksStrong(page?.label || "", index)).length;
  const strongPageSummaries = pageFlow.filter((page) => pageFlowSummaryLooksStrong(page?.summary || "", page?.label || "")).length;
  const recipe = isPlainObject(contentPackage.recipe) ? contentPackage.recipe : {};
  const wordCount = countWords(contentHtml.replace(/<[^>]+>/g, " "));
  const minimumWords = Number(contentType === "recipe" ? 1200 : 1100);
  const h2Count = (contentHtml.match(/<h2\b/gi) || []).length;
  const internalLinks = countInternalLinks(contentHtml);
  const excerptWords = countWords(contentPackage.excerpt || "");
  const seoWords = countWords(contentPackage.seo_description || "");
  const openingParagraph = extractOpeningParagraphText(contentPackage);
  const titleScore = headlineSpecificityScore(contentPackage.title || "", contentType, job?.topic || "");
  const titleStrong = titleLooksStrong(contentPackage.title || "", job?.topic || "", contentType);
  const titleFrontLoadScore = frontLoadedClickSignalScore(contentPackage.title || "", contentType);
  const excerptFrontLoadScore = frontLoadedClickSignalScore(contentPackage.excerpt || "", contentType);
  const seoFrontLoadScore = frontLoadedClickSignalScore(contentPackage.seo_description || "", contentType);
  const openingFrontLoadScore = frontLoadedClickSignalScore(openingParagraph || "", contentType);
  const openingAlignmentScore = openingPromiseAlignmentScore(contentPackage.title || "", openingParagraph);
  const excerptAddsValue = excerptAddsNewValue(contentPackage.title || "", contentPackage.excerpt || "");
  const openingAddsValue = openingParagraphAddsNewValue(contentHtml, contentPackage.title || "", contentPackage.excerpt || "");
  const excerptSignalScore = excerptClickSignalScore(contentPackage.excerpt || "", contentPackage.title || "", openingParagraph);
  const seoSignalScore = seoDescriptionSignalScore(contentPackage.seo_description || "", contentPackage.title || "", contentPackage.excerpt || "");
  const recipeComplete = contentType !== "recipe" || (ensureStringArray(recipe.ingredients).length > 0 && ensureStringArray(recipe.instructions).length > 0);
  const checks = [];

  if (!cleanText(contentPackage.title || "") || !cleanText(contentPackage.slug || "") || !cleanText(contentHtml.replace(/<[^>]+>/g, " "))) {
    checks.push("missing_core_fields");
  }
  if (contentType === "recipe" && !recipeComplete) {
    checks.push("missing_recipe");
  }
  if (wordCount < minimumWords) {
    checks.push("thin_content");
  }
  if (!titleStrong || titleScore < 3) {
    checks.push("weak_title");
  }
  if (excerptWords < 12 || !excerptAddsValue || excerptSignalScore < 3) {
    checks.push("weak_excerpt");
  }
  if (seoWords < 12 || seoSignalScore < 3) {
    checks.push("weak_seo");
  }
  if (openingAlignmentScore < 2 || !openingAddsValue) {
    checks.push("weak_title_alignment");
  }
  if (pageCount < 2 || pageCount > 3) {
    checks.push("weak_pagination");
  }
  if (pageCount > 1 && shortestPageWords > 0 && shortestPageWords < 140) {
    checks.push("weak_page_balance");
  }
  if (pageCount > 1 && strongPageOpenings < pageCount) {
    checks.push("weak_page_openings");
  }
  if (pageCount > 1 && pageFlow.length < pageCount) {
    checks.push("weak_page_flow");
  }
  if (pageCount > 1 && strongPageLabels < pageCount) {
    checks.push("weak_page_labels");
  }
  if (pageCount > 1 && uniquePageLabels.size < pageCount) {
    checks.push("repetitive_page_labels");
  }
  if (pageCount > 1 && strongPageSummaries < pageCount) {
    checks.push("weak_page_summaries");
  }
  if (h2Count < 2) {
    checks.push("weak_structure");
  }
  if (internalLinks < 3) {
    checks.push("missing_internal_links");
  }

  return {
    checks: Array.from(new Set(checks)),
    metrics: {
      word_count: wordCount,
      minimum_words: minimumWords,
      excerpt_words: excerptWords,
      seo_words: seoWords,
      title_score: titleScore,
      title_strong: titleStrong,
      title_front_load_score: titleFrontLoadScore,
      opening_alignment_score: openingAlignmentScore,
      excerpt_adds_value: excerptAddsValue,
      opening_adds_value: openingAddsValue,
      opening_front_load_score: openingFrontLoadScore,
      excerpt_signal_score: excerptSignalScore,
      excerpt_front_load_score: excerptFrontLoadScore,
      seo_signal_score: seoSignalScore,
      seo_front_load_score: seoFrontLoadScore,
      page_count: pageCount,
      shortest_page_words: shortestPageWords,
      strong_page_openings: strongPageOpenings,
      unique_page_labels: uniquePageLabels.size,
      strong_page_labels: strongPageLabels,
      strong_page_summaries: strongPageSummaries,
      h2_count: h2Count,
      internal_links: internalLinks,
      recipe_complete: recipeComplete,
    },
  };
}

function buildArticleStageRepairNote(summary, job) {
  const checks = Array.isArray(summary?.checks) ? summary.checks : [];
  const metrics = isPlainObject(summary?.metrics) ? summary.metrics : {};
  const fixes = [];

  if (checks.includes("missing_core_fields")) {
    fixes.push("Fill title, slug, excerpt, seo_description, content_pages, page_flow, image fields, and recipe data correctly.");
  }
  if (checks.includes("missing_recipe")) {
    fixes.push("For recipe output, fill recipe.ingredients[] and recipe.instructions[] completely.");
  }
  if (checks.includes("thin_content")) {
    fixes.push(`Increase article depth so the body clears roughly ${Number(metrics.minimum_words || 0)} words without filler.`);
  }
  if (checks.includes("weak_excerpt")) {
    fixes.push("Write a stronger, more specific excerpt that adds new value beyond the title and front-loads one concrete detail, pain point, or payoff.");
  }
  if (checks.includes("weak_seo")) {
    fixes.push("Write a fuller natural SEO description that front-loads one concrete reason to click instead of repeating the title or excerpt.");
  }
  if (checks.includes("weak_title")) {
    fixes.push("Write a clearer, more specific title that signals a real payoff, mistake, shortcut, or useful outcome instead of sounding generic. Honest contrast can help when it stays concrete.");
  }
  if (checks.includes("weak_title_alignment")) {
    fixes.push("Make page 1 cash the promise of the title immediately with a stronger opening paragraph whose first sentence front-loads the answer, payoff, or problem being solved.");
  }
  if (checks.includes("weak_pagination")) {
    fixes.push("Return 2 or 3 strong article pages with clean same-post flow.");
  }
  if (checks.includes("weak_page_balance")) {
    fixes.push("Rebalance the pages so no page feels thin or leftover.");
  }
  if (checks.includes("weak_page_openings")) {
    fixes.push("Make every page open with a stronger section lead or H2-led start.");
  }
  if (checks.includes("weak_page_flow")) {
    fixes.push("Fill page_flow completely with one strong label and summary per page.");
  }
  if (checks.includes("weak_page_labels")) {
    fixes.push("Use stronger editorial page labels instead of generic navigation-like labels.");
  }
  if (checks.includes("repetitive_page_labels")) {
    fixes.push("Make the page labels more distinct from one another.");
  }
  if (checks.includes("weak_page_summaries")) {
    fixes.push("Make page summaries preview concrete payoff instead of thin restatements.");
  }
  if (checks.includes("weak_structure")) {
    fixes.push("Add clearer H2 structure so the article scans naturally.");
  }
  if (checks.includes("missing_internal_links")) {
    fixes.push("Include at least three natural internal Kuchnia Twist links across the article pages.");
  }
  if ((job?.content_type || "") === "food_fact") {
    fixes.push("Stay in editorial explainer territory and avoid recipe-style metadata.");
  }

  return [
    `Previous article attempt was too weak for ${job?.content_type || "article"} output.`,
    "Fix these constraints:",
    ...Array.from(new Set(fixes)).map((fix) => `- ${fix}`),
  ].join("\n");
}

function validateGeneratedPayload(generated, job) {
  const contentPackage = resolveCanonicalContentPackage(generated, job);
  const contentHtml = String(contentPackage.content_html || "");
  const text = cleanText(contentHtml.replace(/<[^>]+>/g, " "));
  const opening = cleanText((contentHtml.match(/<p>(.*?)<\/p>/i)?.[1] || contentHtml).replace(/<[^>]+>/g, " ")).toLowerCase();
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

  if ((contentPackage.content_type || job.content_type) === "food_story" && /\b(i|my|me|mine)\b/i.test(text)) {
    throw new Error("Food story output used first-person voice, which is blocked for the publication-voice essay format.");
  }

  return generated;
}

function normalizeGeneratedPayload(raw, job) {
  const source = coerceGeneratedPayload(raw);
  const channelsSource = readGeneratedObject(source, ["channels", "channel_outputs", "channelOutputs"]) || {};
  const facebookSource = isPlainObject(channelsSource?.facebook) ? channelsSource.facebook : {};
  const titleOverride = cleanText(job?.title_override || "");
  const title =
    titleOverride ||
    cleanText(readGeneratedString(source, ["title", "headline", "post_title", "postTitle", "name"])) ||
    cleanText(job?.topic) ||
    "Fresh from Kuchnia Twist";
  const slug = normalizeSlug(readGeneratedString(source, ["slug", "post_slug", "postSlug"]) || title);
  let contentPages = resolveGeneratedContentPages(source, job);
  const sourceContentHtml = resolveGeneratedContentHtml(source, job);
  if (!contentPages.length) {
    contentPages = sourceContentHtml ? splitHtmlIntoPages(sourceContentHtml, job?.content_type || "recipe").slice(0, 3) : [];
  }
  contentPages = stabilizeGeneratedContentPages(contentPages, sourceContentHtml, job?.content_type || "recipe");
  contentPages = ensureInternalLinksOnPages(contentPages, job);
  const pageFlow = normalizeGeneratedPageFlow(
    readGeneratedArray(source, ["page_flow", "pageFlow", "content_page_flow", "contentPageFlow"]),
    contentPages,
  );
  const contentHtml = contentPages.length ? mergeContentPagesIntoHtml(contentPages) : ensureInternalLinks(sourceContentHtml, job);
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
    buildFallbackFacebookCaption({ title, excerpt }, "Read the full article on the blog.");
  const groupShareKit = cleanMultilineText(readGeneratedString(source, ["group_share_kit", "groupShareKit"]));
  const imagePrompt =
    cleanMultilineText(readGeneratedString(source, ["image_prompt", "imagePrompt", "hero_image_prompt", "heroImagePrompt"])) ||
    `Editorial food photography of ${title}, premium magazine lighting, appetizing detail, natural styling, no text overlay.`;
  const imageAlt = cleanText(readGeneratedString(source, ["image_alt", "imageAlt", "hero_image_alt", "heroImageAlt", "alt_text", "altText"])) || title;
  const recipe = normalizeRecipe(readGeneratedObject(source, ["recipe", "recipe_card", "recipeCard"]) || {}, job?.content_type || "recipe");
  const socialPack = normalizeSocialPack(
    Array.isArray(facebookSource.selected) ? facebookSource.selected
      : (Array.isArray(facebookSource.social_pack) ? facebookSource.social_pack : readGeneratedArray(source, ["social_pack", "socialPack", "facebook_variants", "facebookVariants"])),
    job?.content_type || "recipe",
  );
  const socialCandidates = normalizeSocialPack(
    Array.isArray(facebookSource.candidates) ? facebookSource.candidates
      : (Array.isArray(facebookSource.social_candidates) ? facebookSource.social_candidates : readGeneratedArray(source, ["social_candidates", "socialCandidates"])),
    job?.content_type || "recipe",
  );
  const facebookDistribution = normalizeFacebookDistribution(
    isPlainObject(facebookSource.distribution) ? facebookSource.distribution
      : (isPlainObject(facebookSource.facebook_distribution) ? facebookSource.facebook_distribution : (readGeneratedObject(source, ["facebook_distribution", "facebookDistribution"]) || {})),
    job?.content_type || "recipe",
  );

  if (!contentHtml) {
    throw new Error(
      `The generated article body was empty. Parsed type: ${describeGeneratedType(raw)}. Parsed keys: ${describeGeneratedShape(source)}. Raw preview: ${previewGeneratedValue(raw)}.`,
    );
  }

  if ((job?.content_type || "") === "recipe" && (!recipe.ingredients.length || !recipe.instructions.length)) {
    throw new Error("The generated recipe is missing ingredients or instructions.");
  }

  return syncGeneratedContractContainers({
    content_package: readGeneratedObject(source, ["content_package", "contentPackage"]) || {},
    channels: isPlainObject(channelsSource) ? channelsSource : {},
    title,
    slug,
    excerpt,
    seo_description: seoDescription,
    content_pages: contentPages.length ? contentPages : ensureInternalLinksOnPages(splitHtmlIntoPages(contentHtml, job?.content_type || "recipe").slice(0, 3), job),
    page_flow: pageFlow,
    content_html: contentHtml,
    facebook_caption: facebookCaption,
    group_share_kit: groupShareKit,
    image_prompt: imagePrompt,
    image_alt: imageAlt,
    recipe,
    social_pack: socialPack,
    social_candidates: socialCandidates,
    facebook_distribution: facebookDistribution,
    assets: readGeneratedObject(source, ["assets"]) || {},
    facebook_urls: readGeneratedObject(source, ["facebook_urls", "facebookUrls"]) || {},
    content_machine: readGeneratedObject(source, ["content_machine", "contentMachine"]) || {},
  }, job);
}

function hydrateStoredGeneratedPayload(raw, job) {
  const source = coerceGeneratedPayload(raw);
  if (!Object.keys(source).length) {
    return emptyGeneratedPayload(job);
  }

  try {
    return normalizeGeneratedPayload(source, job);
  } catch (error) {
    const message = formatError(error);
    if (/generated article body was empty|generated recipe is missing ingredients or instructions/i.test(message)) {
      return emptyGeneratedPayload(job);
    }
    throw error;
  }
}

function emptyGeneratedPayload(job) {
  const title = cleanText(job?.title_override || job?.topic || "");

  return syncGeneratedContractContainers({
    title,
    slug: title ? normalizeSlug(title) : "",
    excerpt: "",
    seo_description: "",
    content_pages: [],
    page_flow: [],
    content_html: "",
    facebook_caption: "",
    group_share_kit: "",
    image_prompt: "",
    image_alt: title,
    recipe: normalizeRecipe({}, job?.content_type || "recipe"),
    social_pack: [],
    social_candidates: [],
    facebook_distribution: normalizeFacebookDistribution({}, job?.content_type || "recipe"),
    assets: {},
    facebook_urls: {},
    content_machine: {},
  }, job);
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
  const nestedKeys = [
    "article",
    "post",
    "content",
    "data",
    "result",
    "output",
    "payload",
    "article_package",
    "articlePackage",
    "recipe_package",
    "recipePackage",
    "content_package",
    "contentPackage",
    "blog_post",
    "blogPost",
    "channels",
    "facebook",
    "pinterest",
  ];

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

function normalizeContentPageItem(item) {
  if (typeof item === "string") {
    return normalizeHtml(item);
  }

  if (isPlainObject(item)) {
    return normalizeHtml(item.html || item.content_html || item.contentHtml || item.content || item.body || item.text || "");
  }

  return "";
}

function mergeContentPagesIntoHtml(pages) {
  return pages
    .map((page) => normalizeHtml(page))
    .filter(Boolean)
    .join("\n<!--nextpage-->\n");
}

function countHtmlWords(html) {
  return countWords(String(html || "").replace(/<[^>]+>/g, " "));
}

function extractFirstHeading(html) {
  const match = String(html || "").match(/<h2\b[^>]*>(.*?)<\/h2>/i);
  return cleanText(String(match?.[1] || "").replace(/<[^>]+>/g, " "));
}

function pageWrapUpBonus(pageHtml, contentType = "recipe") {
  const heading = extractFirstHeading(pageHtml).toLowerCase();
  if (!heading) {
    return 0;
  }

  const recipePatterns = [
    /\bserv(?:e|ing)\b/,
    /\bstorage\b/,
    /\breheat/i,
    /\bnotes?\b/,
    /\btips?\b/,
    /\bvariations?\b/,
    /\bmake ahead\b/,
  ];
  const factPatterns = [
    /\bpractical takeaway\b/,
    /\bwhat to do\b/,
    /\bwhat this means\b/,
    /\bwhy it matters\b/,
    /\bbottom line\b/,
    /\bfinal takeaway\b/,
    /\bnext time\b/,
  ];
  const patterns = contentType === "recipe" ? recipePatterns : factPatterns;

  return patterns.some((pattern) => pattern.test(heading)) ? (contentType === "recipe" ? 8 : 7) : 0;
}

function enumeratePageLayouts(blocks, pageCount) {
  const count = Array.isArray(blocks) ? blocks.length : 0;
  if (count < pageCount || pageCount < 2 || pageCount > 3) {
    return [];
  }

  if (pageCount === 2) {
    return Array.from({ length: count - 1 }, (_, index) => [index + 1]);
  }

  const layouts = [];
  for (let first = 1; first <= count - 2; first += 1) {
    for (let second = first + 1; second <= count - 1; second += 1) {
      layouts.push([first, second]);
    }
  }
  return layouts;
}

function buildPagesFromBreakpoints(blocks, breakpoints, intro = "") {
  const normalizedBreakpoints = Array.isArray(breakpoints) ? breakpoints : [];
  const pages = [];
  let start = 0;

  for (const stop of [...normalizedBreakpoints, blocks.length]) {
    const chunk = blocks.slice(start, stop);
    start = stop;
    pages.push(chunk.join("\n").trim());
  }

  if (intro) {
    pages[0] = `${intro}\n${pages[0] || ""}`.trim();
  }

  return pages.map((page) => page.trim()).filter(Boolean);
}

function scorePageLayout(pages, contentType = "recipe") {
  const pageWordCounts = pages.map((page) => countHtmlWords(page));
  const totalWords = pageWordCounts.reduce((sum, count) => sum + count, 0);
  const targetWords = totalWords / Math.max(1, pages.length);
  const sectionCounts = pages.map((page) => (String(page).match(/<h2\b/gi) || []).length);
  let score = 0;

  pageWordCounts.forEach((count, index) => {
    const minWords = pages.length === 3
      ? (index === 0 ? 150 : 130)
      : (index === 0 ? 220 : 180);
    const deviation = targetWords > 0 ? Math.abs(count - targetWords) / targetWords : 0;

    score -= deviation * 24;
    if (count >= minWords) {
      score += 6;
    } else {
      score -= ((minWords - count) / Math.max(20, minWords)) * 20;
    }
    if (count < 100) {
      score -= 18;
    }
  });

  score += pages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length * 5;

  if (pages.length === 3) {
    if (pageWordCounts[1] >= pageWordCounts[0] * 0.85) {
      score += 4;
    }
    if (pageWordCounts[2] >= pageWordCounts[1] * 0.62) {
      score += 3;
    } else {
      score -= 8;
    }
    if (pageWordCounts[2] < 120) {
      score -= 8;
    }
  } else if (pages.length === 2 && pageWordCounts[1] < pageWordCounts[0] * 0.55) {
    score -= 6;
  }

  if (sectionCounts.slice(1).every((count) => count > 0)) {
    score += 4;
  }
  if (pages.length === 3 && sectionCounts[2] === 0) {
    score -= 6;
  }

  score += pageWrapUpBonus(pages[pages.length - 1], contentType);

  return score;
}

function selectBestPageLayout(blocks, intro, contentType = "recipe", allowedPageCounts = [2]) {
  let bestPages = [];
  let bestScore = -Infinity;

  for (const pageCount of allowedPageCounts) {
    for (const breakpoints of enumeratePageLayouts(blocks, pageCount)) {
      const pages = buildPagesFromBreakpoints(blocks, breakpoints, intro);
      if (pages.length !== pageCount) {
        continue;
      }

      let score = scorePageLayout(pages, contentType);
      if (pageCount === 3) {
        score += 2;
      }

      if (score > bestScore) {
        bestScore = score;
        bestPages = pages;
      }
    }
  }

  return bestPages;
}

function splitHtmlIntoPages(contentHtml, contentType = "recipe") {
  const normalized = normalizeHtml(contentHtml);
  if (!normalized) {
    return [];
  }

  const sections = normalized.split(/(?=<h2\b)/i).map((section) => section.trim()).filter(Boolean);
  const wordCount = countHtmlWords(normalized);

  if (sections.length >= 2) {
    const intro = sections[0] && !/^<h2\b/i.test(sections[0]) ? sections[0] : "";
    const remainingSections = intro ? sections.slice(1) : sections.slice();
    const allowThreePages = remainingSections.length >= 3
      && (
        contentType === "recipe"
          ? (remainingSections.length >= 5 || wordCount >= 1300)
          : (remainingSections.length >= 4 || wordCount >= 1150)
      );
    const pageCounts = allowThreePages ? [2, 3] : [2];
    const pages = selectBestPageLayout(remainingSections, intro, contentType, pageCounts);

    if (pages.length >= 2) {
      return pages.slice(0, 3);
    }
  }

  const paragraphs = normalized.match(/<(p|ul|ol|blockquote)\b[\s\S]*?<\/\1>/gi) || [];
  if (paragraphs.length >= 4) {
    const allowThreePages = paragraphs.length >= 8 && wordCount >= 1200;
    const pages = selectBestPageLayout(paragraphs, "", contentType, allowThreePages ? [2, 3] : [2]);

    if (pages.length >= 2) {
      return pages.slice(0, 3);
    }
  }

  return [normalized];
}

function stabilizeGeneratedContentPages(contentPages, fallbackHtml, contentType = "recipe") {
  const pages = Array.isArray(contentPages)
    ? contentPages.map((page) => normalizeHtml(page)).filter(Boolean)
    : [];
  const fallback = normalizeHtml(fallbackHtml || "");

  if (!pages.length) {
    return fallback ? splitHtmlIntoPages(fallback, contentType).slice(0, 3) : [];
  }

  const mergedCurrent = mergeContentPagesIntoHtml(pages);
  const currentScore = pages.length >= 2 && pages.length <= 3 ? scorePageLayout(pages, contentType) : -Infinity;
  const currentShortest = pages.length ? Math.min(...pages.map((page) => countHtmlWords(page))) : 0;
  const currentStrongOpens = pages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;

  const candidateSource = fallback || mergedCurrent;
  const candidatePages = candidateSource ? splitHtmlIntoPages(candidateSource, contentType).slice(0, 3) : [];
  const candidateScore = candidatePages.length >= 2 && candidatePages.length <= 3 ? scorePageLayout(candidatePages, contentType) : -Infinity;

  if (pages.length < 2 || pages.length > 3) {
    return candidatePages.length ? candidatePages : pages.slice(0, 3);
  }

  if (!candidatePages.length) {
    return pages;
  }

  const candidateShortest = Math.min(...candidatePages.map((page) => countHtmlWords(page)));
  const candidateStrongOpens = candidatePages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;
  const currentWeak = currentShortest < 110 || currentStrongOpens < pages.length;
  const candidateStronger =
    candidatePages.length >= 2 &&
    (candidateScore > currentScore + 4
      || (currentWeak && candidateScore >= currentScore)
      || (candidateShortest > currentShortest + 35)
      || (candidateStrongOpens > currentStrongOpens));

  return candidateStronger ? candidatePages : pages;
}

function resolveGeneratedContentPages(source, job) {
  const directPages = readGeneratedArray(source, [
    "content_pages",
    "contentPages",
    "article_pages",
    "articlePages",
    "pages",
    "page_chunks",
    "pageChunks",
  ])
    .map((item) => normalizeContentPageItem(item))
    .filter(Boolean);

  if (directPages.length) {
    if (directPages.length > 1) {
      return directPages;
    }

    return splitHtmlIntoPages(directPages[0], job?.content_type || "recipe").slice(0, 3);
  }

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
    return splitHtmlIntoPages(direct, job?.content_type || "recipe").slice(0, 3);
  }

  const sectionHtml = buildContentHtmlFromSections(source);
  if (sectionHtml) {
    return splitHtmlIntoPages(sectionHtml, job?.content_type || "recipe").slice(0, 3);
  }

  const plaintext = readGeneratedString(source, ["content", "article", "post"]);
  if (plaintext && !isPlainObject(plaintext)) {
    return splitHtmlIntoPages(plaintext, job?.content_type || "recipe").slice(0, 3);
  }

  return [];
}

function resolveGeneratedContentHtml(source, job) {
  const pages = resolveGeneratedContentPages(source, job);
  if (pages.length) {
    return mergeContentPagesIntoHtml(pages);
  }

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

function ensureInternalLinksOnPages(contentPages, job) {
  const pages = Array.isArray(contentPages)
    ? contentPages.map((page) => String(page || "").trim()).filter(Boolean)
    : [];

  if (!pages.length || countInternalLinks(mergeContentPagesIntoHtml(pages)) >= 3) {
    return pages;
  }

  const needed = Math.max(1, 3 - countInternalLinks(mergeContentPagesIntoHtml(pages)));
  const selections = internalLinkTargetsForJob(job).slice(0, needed);
  if (!selections.length) {
    return pages;
  }

  const buckets = pages.map(() => []);
  selections.forEach((item, index) => {
    buckets[index % pages.length].push(item);
  });

  return pages.map((page, index) => {
    const bucket = buckets[index] || [];
    if (!bucket.length) {
      return page;
    }

    const links = bucket
      .map((item) => `[kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
      .join(", ");

    return `${page}\n<p>Keep reading across the journal: ${links}.</p>`;
  });
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
  const cta = cleanText(defaultCta) || "Read the full article on the blog.";
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
    cleanText(defaultCta) || "Read the full article on the blog.",
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

function maskSecretSuffix(value) {
  const text = cleanText(value);
  if (!text) {
    return "none";
  }

  return text.length <= 4 ? text : text.slice(-4);
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

async function publishFacebookDistribution({ settings, generated, permalink, pages, socialPack, distribution, imageUrl, contentType = "recipe" }) {
  const nextDistribution = normalizeFacebookDistribution(distribution, contentType);
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
      angle_key: normalizeAngleKey(variant.angle_key || existing.angle_key || existing.angleKey || "", contentType),
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

function firstSuccessfulDistributionResult(distribution, contentType = "") {
  const normalized = normalizeFacebookDistribution(distribution, contentType);
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
    throw new Error("No active Facebook pages are configured for article distribution.");
  }
}

function assertRecipeDistributionTargets(job, pages) {
  if (Array.isArray(pages) && pages.length > 0) {
    return;
  }

  throw new Error(
    `Job #${toInt(job?.id)} no longer has any active Facebook pages attached. Reopen it in wp-admin, choose at least one page, and try again.`,
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

function extractOpenAiResponseText(payload) {
  if (typeof payload?.output_text === "string" && cleanMultilineText(payload.output_text)) {
    return cleanMultilineText(payload.output_text);
  }

  if (Array.isArray(payload?.output)) {
    const joined = cleanMultilineText(
      payload.output
        .flatMap((item) => {
          if (!isPlainObject(item)) {
            return [];
          }

          if (Array.isArray(item.content)) {
            return item.content.map((part) => {
              if (!isPlainObject(part)) {
                return "";
              }

              if (typeof part.text === "string") {
                return part.text;
              }

              if (isPlainObject(part.text) && typeof part.text.value === "string") {
                return part.text.value;
              }

              if (typeof part.output_text === "string") {
                return part.output_text;
              }

              return cleanMultilineText(part.content || part.value || "");
            });
          }

          return [cleanMultilineText(item.text || item.output_text || "")];
        })
        .filter(Boolean)
        .join("\n"),
    );

    if (joined) {
      return joined;
    }
  }

  return "";
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
