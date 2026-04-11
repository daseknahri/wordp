export function createFacebookPublishHelpers(deps) {
  const {
    buildFacebookComment,
    buildFacebookCommentUrl,
    buildFacebookPostMessage,
    buildFacebookPostUrl,
    buildFallbackFacebookCaption,
    cleanText,
    cleanMultilineText,
    formatError,
    normalizeAngleKey,
    normalizeFacebookDistribution,
    normalizeSlug,
    publishFacebookComment,
    publishFacebookPost,
    resolveCanonicalContentPackage,
    trimText,
  } = deps;

  function buildTrackedUrl(permalink, settings, generated, contentLabel) {
    const contentPackage = resolveCanonicalContentPackage(generated);
    const url = new URL(permalink);
    url.searchParams.set("utm_source", settings.utmSource || "facebook");
    url.searchParams.set("utm_medium", "social");
    url.searchParams.set(
      "utm_campaign",
      trimText(`${settings.utmCampaignPrefix || "kuchnia-twist"}-${normalizeSlug(contentPackage.slug || contentPackage.title || "article")}`, 80),
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

  return {
    buildTrackedUrl,
    publishFacebookDistribution,
  };
}
