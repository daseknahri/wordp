export function createJobOrchestrator(deps) {
  const {
    assertFacebookConfigured,
    assertQualityGate,
    assertRecipeDistributionTargets,
    buildQualitySummary,
    claimNextJob,
    completeJob,
    ensureJobImages,
    ensureOpenAiConfigured,
    ensureSocialPackCoverage,
    finalizeFacebookPhaseState,
    firstAttachment,
    formatError,
    generatePackage,
    hydrateStoredGeneratedPayload,
    idleHeartbeatState,
    log,
    mergeSettings,
    mergeValidatorSummary,
    normalizeGeneratedPayload,
    publishBlogPost,
    publishFacebookDistribution,
    refreshFacebookPhaseState,
    resolveCanonicalContentPackage,
    safeFailJob,
    summarizeFacebookFailures,
    toInt,
    updateJobProgress,
    isFutureUtcTimestamp,
  } = deps;

  function inferClaimMode(job) {
    return String(job?.status || "") === "scheduled" ? "publish" : "generate";
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

  async function processNextJob() {
    const claimed = await claimNextJob();
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
    const facebookPostTeaserCta = String(settings.facebookPostTeaserCta || "");
    let generated = hydrateStoredGeneratedPayload(job.generated_payload, job);
    let contentPackage = resolveCanonicalContentPackage(generated, job);
    let {
      distribution,
      facebookCaption,
      groupShareKit,
      preferredAngle,
      selectedPages,
      socialPack,
    } = refreshFacebookPhaseState({
      job,
      settings,
      generated,
      facebookPostTeaserCta,
      fallbackCaption: job.facebook_caption || generated.facebook_caption || "",
      fallbackGroupShareKit: job.group_share_kit || generated.group_share_kit || "",
    });
    let featuredImage = firstAttachment(job.featured_image, job.blog_image);
    let facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage);

    log(`processing ${jobLabel}`);

    try {
      assertRecipeDistributionTargets(job, selectedPages);

      if (claimMode === "generate") {
        ensureOpenAiConfigured(settings);
        generated = await generatePackage(job, settings);
        contentPackage = resolveCanonicalContentPackage(generated, job);
        ({ distribution, facebookCaption, groupShareKit, preferredAngle, selectedPages, socialPack } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
        }));

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
        ({ facebookCaption, groupShareKit } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
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
        if (isFutureUtcTimestamp(publishOn)) {
          log(`scheduled ${jobLabel} for ${publishOn} UTC`);
          return scheduledHeartbeatState(job.id);
        }

        job = scheduled?.job || job;
        generated = normalizeGeneratedPayload(job.generated_payload || generated, job);
        contentPackage = resolveCanonicalContentPackage(generated, job);
        ({ distribution, facebookCaption, groupShareKit, preferredAngle, selectedPages, socialPack } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
          fallbackCaption: job.facebook_caption || generated.facebook_caption || facebookCaption,
          fallbackGroupShareKit: job.group_share_kit || generated.group_share_kit || groupShareKit,
        }));
        featuredImage = firstAttachment(job.featured_image, job.blog_image, featuredImage);
        facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage, facebookImage);
        log(`generated ${jobLabel} and continuing directly to publish`);
      }

      if (!contentPackage.title || !contentPackage.content_html) {
        ensureOpenAiConfigured(settings);
        generated = await generatePackage(job, settings);
        contentPackage = resolveCanonicalContentPackage(generated, job);
        ({ distribution, facebookCaption, groupShareKit, preferredAngle, selectedPages, socialPack } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
        }));
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
      ({ distribution, facebookCaption, groupShareKit, preferredAngle, selectedPages, socialPack } = refreshFacebookPhaseState({
        job,
        settings,
        generated,
        facebookPostTeaserCta,
      }));
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
      assertFacebookConfigured(selectedPages);
      socialPack = ensureSocialPackCoverage(socialPack, selectedPages, generated, settings, job.content_type || "recipe", preferredAngle);
      distribution = seedLegacyFacebookDistribution(distribution, selectedPages, job, facebookCaption);

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
      ({
        facebookCaption,
        facebookCommentId,
        facebookPostId,
        generated,
      } = finalizeFacebookPhaseState({
        job,
        settings,
        generated,
        distribution,
        socialPack,
        facebookPostTeaserCta,
      }));

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

  return {
    completedHeartbeatState,
    inferClaimMode,
    processJob,
    processNextJob,
    scheduledHeartbeatState,
  };
}
