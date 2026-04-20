import { buildPostingPlan } from "./posting-policy.mjs";

export function createPostingMachine(deps) {
  const {
    completeJob,
    executors,
    formatError,
    log,
    resolvePostingTargetCounts,
    safeFailJob,
    toInt,
    updateJobProgress,
  } = deps;

  function isPlainObject(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  function resolvePublicationState(state, fallbackId = 0, fallbackPermalink = "") {
    const publication = isPlainObject(state?.publication) ? state.publication : {};

    return {
      id: toInt(publication.id ?? publication.postId ?? state?.postId ?? fallbackId),
      permalink: String(publication.permalink || state?.permalink || fallbackPermalink || ""),
    };
  }

  function resolveFacebookDeliveryState(
    state,
    fallbackDistribution,
    fallbackPostId = "",
    fallbackCommentId = "",
    fallbackCaption = "",
  ) {
    const deliveries = isPlainObject(state?.deliveries) ? state.deliveries : {};
    const facebook = isPlainObject(deliveries.facebook) ? deliveries.facebook : {};

    return {
      distribution: facebook.distribution || state?.distribution || fallbackDistribution,
      postId: String(facebook.postId || state?.facebookPostId || fallbackPostId || ""),
      commentId: String(facebook.commentId || state?.facebookCommentId || fallbackCommentId || ""),
      caption: String(facebook.caption || state?.facebookCaption || fallbackCaption || ""),
    };
  }

  function resolveFacebookGroupsDeliveryState(state, fallbackDraft = "") {
    const deliveries = isPlainObject(state?.deliveries) ? state.deliveries : {};
    const facebookGroups = isPlainObject(deliveries.facebook_groups) ? deliveries.facebook_groups : {};

    return {
      draft: String(
        facebookGroups.draft
        || facebookGroups.shareKit
        || state?.groupShareKit
        || fallbackDraft
        || "",
      ),
    };
  }

  function resolvePostingTargetsState(state, fallbackTargets = {}) {
    const targets = isPlainObject(state?.targets) ? state.targets : {};
    const normalizedTargets = { ...fallbackTargets, ...targets };
    if (!Array.isArray(normalizedTargets.facebook)) {
      normalizedTargets.facebook = Array.isArray(state?.selectedPages)
        ? state.selectedPages
        : (Array.isArray(fallbackTargets.facebook) ? fallbackTargets.facebook : []);
    }

    return normalizedTargets;
  }

  function buildPostingCallbackPayload(generated, publication, deliveries, targets) {
    const normalizedDeliveries = isPlainObject(deliveries) ? deliveries : {};
    const normalizedPublication = isPlainObject(publication) ? publication : {};
    const normalizedTargets = isPlainObject(targets) ? targets : {};
    const facebook = isPlainObject(normalizedDeliveries.facebook) ? normalizedDeliveries.facebook : {};
    const facebookGroups = isPlainObject(normalizedDeliveries.facebook_groups) ? normalizedDeliveries.facebook_groups : {};

    return {
      publication: normalizedPublication,
      deliveries: normalizedDeliveries,
      targets: normalizedTargets,
      facebook_post_id: String(facebook.postId || ""),
      facebook_comment_id: String(facebook.commentId || ""),
      facebook_caption: String(facebook.caption || ""),
      group_share_kit: String(facebookGroups.draft || facebookGroups.shareKit || ""),
      generated_payload: generated,
    };
  }

  async function runPublishingFlow({
    job,
    settings,
    generated,
    publication = null,
    deliveries = null,
    targets = null,
    distribution,
    facebookCaption,
    groupShareKit,
    socialPack,
    featuredImage,
    facebookImage,
    retryTarget = "full",
    postId = 0,
    permalink = "",
    facebookPostId = "",
    facebookCommentId = "",
    jobLabel = "",
    facebookPostTeaserCta = "",
  }) {
    const targetCounts = resolvePostingTargetCounts(job, settings);
    const postingPlan = buildPostingPlan({
      settings,
      job,
      retryTarget,
      hasPrimaryPublication: Boolean(postId),
      targetCounts,
    });
    let stage = postingPlan.steps[0]?.stage || "publishing_blog";
    let nextDistribution = distribution;
    let nextGenerated = generated;
    let nextPostId = toInt(postId);
    let nextPermalink = String(permalink || "");
    let nextFacebookPostId = String(facebookPostId || "");
    let nextFacebookCommentId = String(facebookCommentId || "");
    let nextFacebookCaption = String(facebookCaption || "");
    let nextGroupShareKit = String(groupShareKit || "");
    const initialState = {
      publication,
      deliveries,
      targets,
      postId: nextPostId,
      permalink: nextPermalink,
      distribution: nextDistribution,
      facebookPostId: nextFacebookPostId,
      facebookCommentId: nextFacebookCommentId,
      facebookCaption: nextFacebookCaption,
      groupShareKit: nextGroupShareKit,
      selectedPages: Array.isArray(targets?.facebook) ? targets.facebook : [],
    };
    let nextPublication = resolvePublicationState(initialState, nextPostId, nextPermalink);
    let nextDeliveries = {
      ...(isPlainObject(deliveries) ? deliveries : {}),
      facebook: resolveFacebookDeliveryState(
        initialState,
        nextDistribution,
        nextFacebookPostId,
        nextFacebookCommentId,
        nextFacebookCaption,
      ),
      facebook_groups: resolveFacebookGroupsDeliveryState(initialState, nextGroupShareKit),
    };
    let nextTargets = resolvePostingTargetsState(initialState, {
      facebook: Array.isArray(targets?.facebook) ? targets.facebook : [],
    });
    let finalSelectedPages = Array.isArray(nextTargets.facebook) ? nextTargets.facebook : [];
    nextPostId = nextPublication.id;
    nextPermalink = nextPublication.permalink;
    nextDistribution = nextDeliveries.facebook.distribution;
    nextFacebookPostId = nextDeliveries.facebook.postId;
    nextFacebookCommentId = nextDeliveries.facebook.commentId;
    nextFacebookCaption = nextDeliveries.facebook.caption;
    nextGroupShareKit = nextDeliveries.facebook_groups.draft;
    let state = {
      distribution: nextDistribution,
      generated: nextGenerated,
      postId: nextPostId,
      permalink: nextPermalink,
      facebookPostId: nextFacebookPostId,
      facebookCommentId: nextFacebookCommentId,
      facebookCaption: nextFacebookCaption,
      groupShareKit: nextGroupShareKit,
      selectedPages: finalSelectedPages,
      publication: nextPublication,
      deliveries: nextDeliveries,
      targets: nextTargets,
    };

    try {
      for (const step of postingPlan.steps) {
        stage = step.stage;
        await updateJobProgress(job.id, {
          status: step.stage,
          stage,
        }, job);

        const executorKey = String(step.executorKey || step.key || "");
        const runStep = executors?.[executorKey];
        if (typeof runStep !== "function") {
          throw new Error(`No posting executor is configured for step "${executorKey || step.key}".`);
        }

        const stepResult = await runStep({
          job,
          settings,
          state,
          socialPack,
          featuredImage,
          facebookImage,
          retryTarget,
          facebookPostTeaserCta,
          jobLabel,
        });

        state = stepResult?.state || state;
        nextPublication = resolvePublicationState(state, nextPostId, nextPermalink);
        nextDeliveries = {
          ...(isPlainObject(state?.deliveries) ? state.deliveries : nextDeliveries),
          facebook: resolveFacebookDeliveryState(
            state,
            nextDistribution,
            nextFacebookPostId,
            nextFacebookCommentId,
            nextFacebookCaption,
          ),
          facebook_groups: resolveFacebookGroupsDeliveryState(state, nextGroupShareKit),
        };
        nextTargets = resolvePostingTargetsState(state, nextTargets);
        finalSelectedPages = Array.isArray(nextTargets.facebook) ? nextTargets.facebook : finalSelectedPages;
        nextDistribution = nextDeliveries.facebook.distribution;
        nextGenerated = state.generated;
        nextPostId = nextPublication.id;
        nextPermalink = nextPublication.permalink;
        nextFacebookPostId = nextDeliveries.facebook.postId;
        nextFacebookCommentId = nextDeliveries.facebook.commentId;
        nextFacebookCaption = nextDeliveries.facebook.caption;
        nextGroupShareKit = nextDeliveries.facebook_groups.draft;

        state = {
          ...state,
          distribution: nextDistribution,
          generated: nextGenerated,
          postId: nextPostId,
          permalink: nextPermalink,
          facebookPostId: nextFacebookPostId,
          facebookCommentId: nextFacebookCommentId,
          facebookCaption: nextFacebookCaption,
          groupShareKit: nextGroupShareKit,
          selectedPages: finalSelectedPages,
          publication: nextPublication,
          deliveries: nextDeliveries,
          targets: nextTargets,
        };

        if (stepResult?.partialFailureMessage) {
          const message = String(stepResult.partialFailureMessage);
          await safeFailJob(job.id, {
            status: "partial_failure",
            stage,
            ...buildPostingCallbackPayload(nextGenerated, nextPublication, nextDeliveries, nextTargets),
            error_message: message,
          }, job);

          log(`${jobLabel} partially failed across posting step ${executorKey || step.key}: ${message}`);
          return {
            ok: false,
            terminal: true,
            stage,
            status: "partial_failure",
            error: message,
            distribution: nextDistribution,
            generated: nextGenerated,
            facebookCaption: nextFacebookCaption,
            groupShareKit: nextGroupShareKit,
            facebookPostId: nextFacebookPostId,
            facebookCommentId: nextFacebookCommentId,
            postId: nextPostId,
            permalink: nextPermalink,
            selectedPages: finalSelectedPages,
            publication: nextPublication,
            deliveries: nextDeliveries,
            targets: nextTargets,
          };
        }
      }

      await completeJob(job.id, {
        status: "completed",
        ...buildPostingCallbackPayload(nextGenerated, nextPublication, nextDeliveries, nextTargets),
      }, job);

      log(`completed ${jobLabel}; distributed to ${finalSelectedPages.length} Facebook page(s)`);
      return {
        ok: true,
        terminal: true,
        stage: "completed",
        status: "completed",
        error: "",
        distribution: nextDistribution,
        generated: nextGenerated,
        facebookCaption: nextFacebookCaption,
        groupShareKit: nextGroupShareKit,
        facebookPostId: nextFacebookPostId,
        facebookCommentId: nextFacebookCommentId,
        postId: nextPostId,
        permalink: nextPermalink,
        selectedPages: finalSelectedPages,
        publication: nextPublication,
        deliveries: nextDeliveries,
        targets: nextTargets,
      };
    } catch (error) {
      const message = formatError(error);
      const status = nextPostId ? "partial_failure" : "failed";

      await safeFailJob(job.id, {
        status,
        stage,
        ...buildPostingCallbackPayload(nextGenerated, nextPublication, nextDeliveries, nextTargets),
        error_message: message,
      }, job);

      log(`${jobLabel} failed: ${message}`);
      return {
        ok: false,
        terminal: true,
        stage,
        status,
        error: message,
        distribution: nextDistribution,
        generated: nextGenerated,
        facebookCaption: nextFacebookCaption,
        groupShareKit: nextGroupShareKit,
        facebookPostId: nextFacebookPostId,
        facebookCommentId: nextFacebookCommentId,
        postId: nextPostId,
        permalink: nextPermalink,
        selectedPages: [],
        publication: nextPublication,
        deliveries: nextDeliveries,
        targets: nextTargets,
      };
    }
  }

  return {
    runPublishingFlow,
  };
}
