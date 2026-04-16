export function createFacebookPhaseHelpers(deps) {
  const {
    assertRecipeDistributionTargets,
    buildFallbackFacebookCaption,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    firstSuccessfulDistributionResult,
    resolveFacebookChannelAdapter,
    resolvePreferredAngle,
    resolveSelectedFacebookPages,
    seedLegacyFacebookDistribution,
    syncGeneratedContractContainers,
  } = deps;

  function refreshFacebookPhaseState({
    job,
    settings,
    generated,
    facebookPostTeaserCta = "",
    fallbackCaption = "",
    fallbackGroupShareKit = "",
  }) {
    const facebookChannel = resolveFacebookChannelAdapter(generated, job);
    const facebookCaption = deriveLegacyFacebookCaptionMirror(
      facebookChannel,
      generated.facebook_caption || fallbackCaption || "",
      facebookPostTeaserCta,
    );
    const groupShareKit = deriveLegacyGroupShareKitMirror(generated, job) || fallbackGroupShareKit || "";
    const socialPack = facebookChannel.selected;
    const selectedPages = resolveSelectedFacebookPages(job, settings);
    assertRecipeDistributionTargets(job, selectedPages);
    const preferredAngle = resolvePreferredAngle(job);
    const distribution = seedLegacyFacebookDistribution(
      facebookChannel.distribution,
      selectedPages,
      job,
      facebookCaption,
    );

    return {
      distribution,
      facebookCaption,
      groupShareKit,
      preferredAngle,
      selectedPages,
      socialPack,
    };
  }

  function finalizeFacebookPhaseState({
    job,
    settings,
    generated,
    distribution,
    socialPack,
    facebookPostTeaserCta = "",
  }) {
    const firstSuccess = firstSuccessfulDistributionResult(distribution, job.content_type || "recipe");
    const facebookPostId = firstSuccess?.post_id || "";
    const facebookCommentId = firstSuccess?.comment_id || "";
    const facebookCaption = firstSuccess?.caption || socialPack[0]?.caption || buildFallbackFacebookCaption(generated);
    const syncedGenerated = syncGeneratedContractContainers({
      ...generated,
      facebook_post_teaser_cta: facebookPostTeaserCta,
      facebook_comment_link_cta: settings.facebookCommentLinkCta,
      social_pack: socialPack,
      facebook_distribution: distribution,
      facebook_urls: {
        ...(generated.facebook_urls || {}),
        facebook_comment: firstSuccess?.comment_url || "",
        facebook_post: firstSuccess?.post_url || "",
      },
    }, job);

    return {
      facebookCaption,
      facebookCommentId,
      facebookPostId,
      firstSuccess,
      generated: syncedGenerated,
    };
  }

  return {
    finalizeFacebookPhaseState,
    refreshFacebookPhaseState,
  };
}
