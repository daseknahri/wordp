export function createFacebookStateHelpers(deps) {
  const {
    buildFacebookCommentUrl,
    buildFacebookPostUrl,
    cleanMultilineText,
    cleanText,
    formatError,
    normalizeAngleKey,
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

  return {
    buildFacebookPublishPageState,
    markFacebookPublishFailure,
    resolveFacebookPublishCtas,
  };
}
