export function createJobOrchestrator(deps) {
  const {
    assertQualityGate,
    buildQualitySummary,
    claimNextJob,
    ensureJobImages,
    ensureOpenAiConfigured,
    ensureSocialPackCoverage,
    firstAttachment,
    formatError,
    generatePackage,
    hydrateStoredGeneratedPayload,
    idleHeartbeatState,
    log,
    mergeSettings,
    mergeValidatorSummary,
    normalizeGeneratedPayload,
    postingMachine,
    refreshFacebookPhaseState,
    resolveCanonicalContentPackage,
    safeFailJob,
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

  function resolveFacebookTargetCount(facebookTargets) {
    return Math.max(0, Number(facebookTargets?.count || 0));
  }

  function isPlainObject(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  function resolvePublishResultPublication(result, toIntValue, fallbackPermalink = "") {
    const publication = isPlainObject(result?.publication) ? result.publication : {};

    return {
      postId: toInt(publication.id ?? publication.postId ?? toIntValue),
      permalink: String(publication.permalink || result?.permalink || fallbackPermalink || ""),
    };
  }

  function resolvePublishResultFacebookDelivery(
    result,
    fallbackDistribution,
    fallbackPostId = "",
    fallbackCommentId = "",
    fallbackCaption = "",
  ) {
    const deliveries = isPlainObject(result?.deliveries) ? result.deliveries : {};
    const facebook = isPlainObject(deliveries.facebook) ? deliveries.facebook : {};

    return {
      distribution: facebook.distribution || result?.distribution || fallbackDistribution,
      postId: String(facebook.postId || result?.facebookPostId || fallbackPostId || ""),
      commentId: String(facebook.commentId || result?.facebookCommentId || fallbackCommentId || ""),
      caption: String(facebook.caption || result?.facebookCaption || fallbackCaption || ""),
    };
  }

  function resolvePublishResultFacebookGroupsDelivery(result, fallbackDraft = "") {
    const deliveries = isPlainObject(result?.deliveries) ? result.deliveries : {};
    const facebookGroups = isPlainObject(deliveries.facebook_groups) ? deliveries.facebook_groups : {};

    return {
      draft: String(
        facebookGroups.draft
        || facebookGroups.shareKit
        || result?.groupShareKit
        || fallbackDraft
        || "",
      ),
    };
  }

  function buildPublicationState(postId, permalink) {
    return {
      id: toInt(postId),
      permalink: String(permalink || ""),
    };
  }

  function buildDeliveriesState(distribution, facebookPostId, facebookCommentId, facebookCaption, groupShareKit) {
    return {
      facebook: {
        distribution,
        postId: String(facebookPostId || ""),
        commentId: String(facebookCommentId || ""),
        caption: String(facebookCaption || ""),
      },
      facebook_groups: {
        draft: String(groupShareKit || ""),
      },
    };
  }

  function buildTargetsState(facebookTargets) {
    return {
      facebook: Array.isArray(facebookTargets?.pages) ? facebookTargets.pages : [],
    };
  }

  function resolvePublishResultTargets(result, fallbackTargets = {}) {
    const targets = isPlainObject(result?.targets) ? result.targets : {};

    return {
      ...fallbackTargets,
      ...targets,
      facebook: Array.isArray(targets.facebook)
        ? targets.facebook
        : (Array.isArray(result?.selectedPages) ? result.selectedPages : (Array.isArray(fallbackTargets.facebook) ? fallbackTargets.facebook : [])),
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
      facebookTargets,
      groupShareKit,
      preferredAngle,
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
    let publicationState = buildPublicationState(postId, permalink);
    let deliveriesState = buildDeliveriesState(
      distribution,
      facebookPostId,
      facebookCommentId,
      facebookCaption,
      groupShareKit,
    );
    let targetsState = buildTargetsState(facebookTargets);

    log(`processing ${jobLabel}`);

    try {
      if (claimMode === "generate") {
        ensureOpenAiConfigured(settings);
        generated = await generatePackage(job, settings);
        contentPackage = resolveCanonicalContentPackage(generated, job);
        ({ distribution, facebookCaption, facebookTargets, groupShareKit, preferredAngle, socialPack } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
        }));

        ({ featuredImage, facebookImage, generated } = await ensureJobImages(job, settings, generated, {
          featuredImage,
          facebookImage,
        }));

        const facebookTargetCount = resolveFacebookTargetCount(facebookTargets);
        socialPack = ensureSocialPackCoverage(socialPack, facebookTargets, generated, settings, job.content_type || "recipe", preferredAngle);
        const qualitySummary = buildQualitySummary(job, { ...generated, social_pack: socialPack }, settings, {
          featuredImage,
          facebookImage,
          targetPages: facebookTargetCount,
        });
        generated = mergeValidatorSummary(
          {
            ...generated,
            social_pack: socialPack,
          },
          {
            ...qualitySummary,
            target_pages: qualitySummary.quality_checks?.target_pages || facebookTargetCount,
            social_variants: qualitySummary.quality_checks?.social_variants || socialPack.length,
          },
        );
        ({ facebookCaption, groupShareKit } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
        }));
        deliveriesState = buildDeliveriesState(
          distribution,
          facebookPostId,
          facebookCommentId,
          facebookCaption,
          groupShareKit,
        );
        targetsState = buildTargetsState(facebookTargets);

        const scheduled = await updateJobProgress(job.id, {
          status: "scheduled",
          stage: "scheduled",
          publication: publicationState,
          deliveries: deliveriesState,
          targets: targetsState,
          generated_payload: generated,
          featured_image_id: featuredImage?.id || 0,
          facebook_image_result_id: facebookImage?.id || featuredImage?.id || 0,
        }, job);

        const publishOn = String(scheduled?.job?.publish_on || "");
        if (isFutureUtcTimestamp(publishOn)) {
          log(`scheduled ${jobLabel} for ${publishOn} UTC`);
          return scheduledHeartbeatState(job.id);
        }

        job = scheduled?.job || job;
        generated = normalizeGeneratedPayload(job.generated_payload || generated, job);
        contentPackage = resolveCanonicalContentPackage(generated, job);
        ({ distribution, facebookCaption, facebookTargets, groupShareKit, preferredAngle, socialPack } = refreshFacebookPhaseState({
          job,
          settings,
          generated,
          facebookPostTeaserCta,
          fallbackCaption: job.facebook_caption || generated.facebook_caption || facebookCaption,
          fallbackGroupShareKit: job.group_share_kit || generated.group_share_kit || groupShareKit,
        }));
        featuredImage = firstAttachment(job.featured_image, job.blog_image, featuredImage);
        facebookImage = firstAttachment(job.facebook_image_result, job.facebook_image, featuredImage, facebookImage);
        deliveriesState = buildDeliveriesState(
          distribution,
          facebookPostId,
          facebookCommentId,
          facebookCaption,
          groupShareKit,
        );
        targetsState = buildTargetsState(facebookTargets);
        log(`generated ${jobLabel} and continuing directly to publish`);
      }

      if (!contentPackage.title || !contentPackage.content_html) {
        ensureOpenAiConfigured(settings);
        generated = await generatePackage(job, settings);
        contentPackage = resolveCanonicalContentPackage(generated, job);
        ({ distribution, facebookCaption, facebookTargets, groupShareKit, preferredAngle, socialPack } = refreshFacebookPhaseState({
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

      const facebookTargetCount = resolveFacebookTargetCount(facebookTargets);
      socialPack = ensureSocialPackCoverage(socialPack, facebookTargets, generated, settings, job.content_type || "recipe", preferredAngle);
      const qualitySummary = buildQualitySummary(job, { ...generated, social_pack: socialPack }, settings, {
        featuredImage,
        facebookImage,
        targetPages: facebookTargetCount,
      });
      generated = mergeValidatorSummary(
        {
          ...generated,
          social_pack: socialPack,
        },
        {
          ...qualitySummary,
          target_pages: qualitySummary.quality_checks?.target_pages || facebookTargetCount,
          social_variants: qualitySummary.quality_checks?.social_variants || socialPack.length,
        },
      );
      ({ distribution, facebookCaption, facebookTargets, groupShareKit, preferredAngle, socialPack } = refreshFacebookPhaseState({
        job,
        settings,
        generated,
        facebookPostTeaserCta,
      }));
      contentPackage = resolveCanonicalContentPackage(generated, job);
      publicationState = buildPublicationState(postId, permalink);
      deliveriesState = buildDeliveriesState(
        distribution,
        facebookPostId,
        facebookCommentId,
        facebookCaption,
        groupShareKit,
      );
      targetsState = buildTargetsState(facebookTargets);
      assertQualityGate(qualitySummary);

      const publishResult = await postingMachine.runPublishingFlow({
        job,
        settings,
        generated,
        publication: publicationState,
        deliveries: deliveriesState,
        targets: targetsState,
        distribution,
        facebookCaption,
        groupShareKit,
        socialPack,
        featuredImage,
        facebookImage,
        retryTarget,
        postId,
        permalink,
        facebookPostId,
        facebookCommentId,
        jobLabel,
        facebookPostTeaserCta,
      });

      stage = publishResult.stage || stage;
      generated = publishResult.generated || generated;
      groupShareKit = publishResult.groupShareKit || groupShareKit;
      const publicationResult = resolvePublishResultPublication(publishResult, postId, permalink);
      const facebookDeliveryResult = resolvePublishResultFacebookDelivery(
        publishResult,
        distribution,
        facebookPostId,
        facebookCommentId,
        facebookCaption,
      );
      const facebookGroupsDeliveryResult = resolvePublishResultFacebookGroupsDelivery(
        publishResult,
        groupShareKit,
      );
      distribution = facebookDeliveryResult.distribution;
      facebookCaption = facebookDeliveryResult.caption;
      facebookPostId = facebookDeliveryResult.postId;
      facebookCommentId = facebookDeliveryResult.commentId;
      groupShareKit = facebookGroupsDeliveryResult.draft;
      postId = publicationResult.postId;
      permalink = publicationResult.permalink;
      publicationState = buildPublicationState(postId, permalink);
      deliveriesState = buildDeliveriesState(
        distribution,
        facebookPostId,
        facebookCommentId,
        facebookCaption,
        groupShareKit,
      );
      targetsState = resolvePublishResultTargets(publishResult, targetsState);

      if (!publishResult.ok) {
        return {
          foundJob: true,
          last_loop_result: "job_failed",
          last_job_id: toInt(job.id),
          last_job_status: publishResult.status || "failed",
          last_error: publishResult.error || "",
        };
      }
      return completedHeartbeatState(job.id, "completed");
    } catch (error) {
      const message = formatError(error);
      const status = postId ? "partial_failure" : "failed";

      await safeFailJob(job.id, {
        status,
        stage,
        publication: publicationState,
        deliveries: deliveriesState,
        targets: targetsState,
        generated_payload: generated,
        error_message: message,
      }, job);

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
