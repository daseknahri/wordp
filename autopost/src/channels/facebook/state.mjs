export function createFacebookStateHelpers(deps) {
  const {
    buildFallbackFacebookCaption,
    buildFacebookCommentUrl,
    buildFacebookPostUrl,
    cleanMultilineText,
    cleanText,
    firstSuccessfulDistributionResult,
    formatError,
    normalizeAngleKey,
    syncGeneratedContractContainers,
  } = deps;

  function resolveFacebookPublishCtas(settings) {
    return {
      postTeaserCta: cleanText(settings.facebookPostTeaserCta) || "\u{1F447} Full article in the first comment below.",
      commentLinkCta: cleanText(settings.facebookCommentLinkCta) || "Read the full article on the blog.",
    };
  }

  function buildFacebookPublishPageState({
    existing,
    page,
    pageLabel,
    variant,
    message,
    commentMessage,
    contentType,
  }) {
    return {
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
  }

  function markFacebookPublishFailure(pageState, pageLabel, error) {
    pageState.status = pageState.post_id ? "comment_failed" : "post_failed";
    pageState.error = formatError(error);

    return {
      page_id: pageState.page_id,
      label: pageLabel,
      error: pageState.error,
      stage: pageState.status,
    };
  }

  function resolveFacebookDistributionResult({
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
    buildFacebookPublishPageState,
    markFacebookPublishFailure,
    resolveFacebookDistributionResult,
    resolveFacebookPublishCtas,
  };
}
