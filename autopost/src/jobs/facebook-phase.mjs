export function createFacebookPhaseHelpers(deps) {
  const {
    assertRecipeDistributionTargets,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    resolveFacebookDistributionContext,
    resolveFacebookChannelAdapter,
    resolvePreferredAngle,
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
    const {
      distribution,
      facebookTargets,
      selectedPages,
    } = resolveFacebookDistributionContext({
      job,
      settings,
      distribution: facebookChannel.distribution,
      facebookCaption,
    });
    assertRecipeDistributionTargets(job, selectedPages);
    const preferredAngle = resolvePreferredAngle(job);

    return {
      distribution,
      facebookCaption,
      facebookTargets,
      groupShareKit,
      preferredAngle,
      selectedPages,
      socialPack,
    };
  }

  return {
    refreshFacebookPhaseState,
  };
}
