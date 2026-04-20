export function createFacebookPublishHelpers(deps) {
  const {
    buildFacebookComment,
    buildFacebookCommentUrl,
    buildFacebookPostMessage,
    buildFallbackFacebookCaption,
    buildFacebookPublishPageState,
    markFacebookPublishFailure,
    normalizeFacebookDistribution,
    normalizeSlug,
    publishFacebookComment,
    publishFacebookPost,
    resolveFacebookPublishCtas,
    resolveCanonicalContentPackage,
    trimText,
  } = deps;

  function summarizeFacebookFailures(failedPages) {
    if (!Array.isArray(failedPages) || failedPages.length === 0) {
      return "";
    }

    return failedPages
      .map((page) => `${page.label || page.page_id}: ${page.error}`)
      .join(" | ");
  }

  function buildTrackedUrl(permalink, settings, generated, contentLabel) {
    const contentPackage = resolveCanonicalContentPackage(generated);
    const url = new URL(permalink);
    url.searchParams.set("utm_source", settings.utmSource || "facebook");
    url.searchParams.set("utm_medium", "social");
    url.searchParams.set(
      "utm_campaign",
      trimText(`${settings.utmCampaignPrefix || "publication"}-${normalizeSlug(contentPackage.slug || contentPackage.title || "article")}`, 80),
    );
    url.searchParams.set("utm_content", contentLabel);
    return url.toString();
  }

  async function publishFacebookDistribution({
    settings,
    generated,
    permalink,
    pages,
    socialPack,
    distribution,
    imageUrl,
    contentType = "recipe",
  }) {
    const nextDistribution = normalizeFacebookDistribution(distribution, contentType);
    const failedPages = [];
    const publishCtas = resolveFacebookPublishCtas(settings);

    for (let index = 0; index < pages.length; index += 1) {
      const page = pages[index];
      const pageLabel = page.label || page.page_id;
      const existing = nextDistribution.pages[page.page_id] || {};
      const variant = socialPack[index] || socialPack[index % socialPack.length] || {};
      const message = buildFacebookPostMessage(
        variant,
        buildFallbackFacebookCaption(generated),
        publishCtas.postTeaserCta,
      );
      const commentUrl = buildTrackedUrl(permalink, settings, generated, `facebook_comment_${normalizeSlug(pageLabel)}`);
      const commentMessage = buildFacebookComment(publishCtas.commentLinkCta, commentUrl);
      const pageState = buildFacebookPublishPageState({
        existing,
        page,
        pageLabel,
        variant,
        message,
        commentMessage,
        contentType,
      });

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
        const failure = markFacebookPublishFailure(pageState, pageLabel, error);
        nextDistribution.pages[page.page_id] = pageState;
        failedPages.push(failure);
      }
    }

    return {
      distribution: nextDistribution,
      failedPages,
      partialFailureMessage: summarizeFacebookFailures(failedPages),
    };
  }

  return {
    buildTrackedUrl,
    publishFacebookDistribution,
  };
}
