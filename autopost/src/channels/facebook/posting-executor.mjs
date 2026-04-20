export function createFacebookPostingExecutor(deps) {
  const {
    assertFacebookConfigured,
    publishFacebookDistribution,
    resolveFacebookDistributionContext,
    resolveFacebookDistributionResult,
  } = deps;

  return async function publishFacebookStep({
    job,
    settings,
    state,
    socialPack,
    featuredImage,
    facebookImage,
    retryTarget = "full",
    facebookPostTeaserCta = "",
  }) {
    const publicationPermalink = String(state?.publication?.permalink || state?.permalink || "");
    const facebookCaption = String(state?.deliveries?.facebook?.caption || state?.facebookCaption || "");
    const {
      distribution: seededDistribution,
      selectedPages,
    } = resolveFacebookDistributionContext({
      job,
      settings,
      distribution: state.distribution,
      facebookCaption,
    });
    assertFacebookConfigured(selectedPages);

    const facebookResult = await publishFacebookDistribution({
      settings,
      generated: state.generated,
      permalink: publicationPermalink,
      pages: selectedPages,
      socialPack,
      distribution: seededDistribution,
      imageUrl: facebookImage?.url || featuredImage?.url || "",
      contentType: job.content_type || "recipe",
      retryTarget,
    });

    const finalized = resolveFacebookDistributionResult({
      job,
      settings,
      generated: state.generated,
      distribution: facebookResult.distribution,
      socialPack,
      facebookPostTeaserCta,
    });

    const nextState = {
      ...state,
      distribution: facebookResult.distribution,
      generated: finalized.generated,
      facebookCaption: finalized.facebookCaption,
      facebookCommentId: finalized.facebookCommentId,
      facebookPostId: finalized.facebookPostId,
      deliveries: {
        ...(state?.deliveries && typeof state.deliveries === "object" ? state.deliveries : {}),
        facebook: {
          ...((state?.deliveries && state.deliveries.facebook && typeof state.deliveries.facebook === "object")
            ? state.deliveries.facebook
            : {}),
          distribution: facebookResult.distribution,
          caption: finalized.facebookCaption,
          commentId: finalized.facebookCommentId,
          postId: finalized.facebookPostId,
        },
      },
      targets: {
        ...(state?.targets && typeof state.targets === "object" ? state.targets : {}),
        facebook: selectedPages,
      },
    };

    return {
      state: nextState,
      selectedPages,
      partialFailureMessage: String(facebookResult.partialFailureMessage || ""),
    };
  };
}
