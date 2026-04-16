export function createFacebookAdapterHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    normalizeAngleKey,
    resolveCanonicalContentPackage,
    stripHookEchoFromCaption,
  } = deps;

  const DEFAULT_FACEBOOK_POST_TEASER_CTA = "\u{1F447} Full article in the first comment below.";
  const DEFAULT_FACEBOOK_COMMENT_LINK_CTA = "Read the full article on the blog.";

  function buildFacebookCommentTeaser(teaserCta = DEFAULT_FACEBOOK_POST_TEASER_CTA) {
    return cleanText(teaserCta) || DEFAULT_FACEBOOK_POST_TEASER_CTA;
  }

  function appendFacebookCommentTeaser(message, teaserCta = DEFAULT_FACEBOOK_POST_TEASER_CTA) {
    const normalized = cleanMultilineText(message || "");
    if (!normalized || /\bfirst comment\b/i.test(normalized)) {
      return normalized;
    }

    return cleanMultilineText(`${normalized}\n\n${buildFacebookCommentTeaser(teaserCta)}`);
  }

  function buildFacebookComment(commentLinkCta, trackedUrl) {
    const cta = cleanText(commentLinkCta) || DEFAULT_FACEBOOK_COMMENT_LINK_CTA;
    return cleanMultilineText(`\u{1F447} ${cta}\n${trackedUrl}`.trim());
  }

  function buildFacebookPostMessage(variant, fallbackCaption, teaserCta = DEFAULT_FACEBOOK_POST_TEASER_CTA) {
    const hook = cleanText(variant?.hook || "");
    const caption = stripHookEchoFromCaption(hook, variant?.caption || fallbackCaption || "");

    if (hook && caption) {
      return appendFacebookCommentTeaser(`${hook}\n\n${caption}`.trim(), teaserCta);
    }

    return appendFacebookCommentTeaser(hook || caption, teaserCta);
  }

  function buildFallbackFacebookCaption(generated) {
    const contentPackage = resolveCanonicalContentPackage(generated);
    return [
      cleanText(contentPackage.title),
      cleanText(contentPackage.excerpt),
    ]
      .filter(Boolean)
      .join("\n\n")
      .trim();
  }

  function buildFacebookPostUrl(postId) {
    if (!postId.includes("_")) {
      return "";
    }

    const [pageId, entryId] = postId.split("_");
    return pageId && entryId ? `https://www.facebook.com/${pageId}/posts/${entryId}` : "";
  }

  function buildFacebookCommentUrl(postId, commentId) {
    const postUrl = buildFacebookPostUrl(postId);
    if (!postUrl || !commentId) {
      return "";
    }

    const fragment = String(commentId).includes("_") ? String(commentId).split("_").pop() : String(commentId);
    return fragment ? `${postUrl}?comment_id=${fragment}` : postUrl;
  }

  function normalizeSocialPack(value, contentType = "") {
    if (!Array.isArray(value)) {
      return [];
    }

    return value
      .map((item, index) => {
        if (!item || typeof item !== "object") {
          return null;
        }

        const hook = cleanText(item.hook || item.headline || "");
        const persistedPostMessage = cleanMultilineText(item.post_message || item.postMessage || "");
        const caption = cleanMultilineText(item.caption || item.body || persistedPostMessage || "");
        const ctaHint = cleanText(item.cta_hint || item.ctaHint || "");
        const angleKey = normalizeAngleKey(item.angle_key || item.angleKey || "", contentType);

        if (!hook && !caption) {
          return null;
        }

        const normalized = {
          id: cleanText(item.id || `variant-${index + 1}`),
          angle_key: angleKey,
          hook,
          caption,
          cta_hint: ctaHint,
        };

        return normalized;
      })
      .filter(Boolean);
  }

  function normalizeFacebookDistribution(value, contentType = "") {
    if (!value || typeof value !== "object" || !value.pages || typeof value.pages !== "object") {
      return { pages: {} };
    }

    return {
      pages: Object.fromEntries(
        Object.entries(value.pages).map(([pageId, raw]) => {
          const page = raw && typeof raw === "object" ? raw : {};
          return [
            pageId,
            (() => {
              const normalizedPage = {
                page_id: cleanText(page.page_id || pageId),
                label: cleanText(page.label || ""),
                angle_key: normalizeAngleKey(page.angle_key || page.angleKey || "", contentType),
                hook: cleanText(page.hook || ""),
                caption: cleanMultilineText(page.caption || page.post_message || page.postMessage || ""),
                cta_hint: cleanText(page.cta_hint || page.ctaHint || ""),
                post_message: cleanMultilineText(page.post_message || page.postMessage || ""),
                post_id: cleanText(page.post_id || page.postId || ""),
                post_url: cleanText(page.post_url || page.postUrl || ""),
                comment_message: cleanMultilineText(page.comment_message || page.commentMessage || ""),
                comment_id: cleanText(page.comment_id || page.commentId || ""),
                comment_url: cleanText(page.comment_url || page.commentUrl || ""),
                status: cleanText(page.status || ""),
                error: cleanText(page.error || ""),
              };
              return normalizedPage;
            })(),
          ];
        }),
      ),
    };
  }

  return {
    buildFacebookComment,
    buildFacebookCommentTeaser,
    buildFacebookCommentUrl,
    buildFacebookPostMessage,
    buildFacebookPostUrl,
    buildFallbackFacebookCaption,
    normalizeFacebookDistribution,
    normalizeSocialPack,
  };
}
