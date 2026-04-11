export function createFacebookAdapterHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    normalizeAngleKey,
    normalizeSocialLineFingerprint,
    resolveCanonicalContentPackage,
    stripHookEchoFromCaption,
  } = deps;

  function buildFacebookComment(defaultCta, trackedUrl) {
    const cta = cleanText(defaultCta) || "Read the full article on the blog.";
    return cleanMultilineText(`${cta} ${trackedUrl}`.trim());
  }

  function buildFacebookPostMessage(variant, fallbackCaption) {
    const hook = cleanText(variant?.hook || "");
    const caption = stripHookEchoFromCaption(hook, variant?.caption || fallbackCaption || "");

    if (hook && caption) {
      return `${hook}\n\n${caption}`.trim();
    }

    return hook || caption;
  }

  function buildFallbackFacebookCaption(generated, defaultCta) {
    const contentPackage = resolveCanonicalContentPackage(generated);
    return [
      cleanText(contentPackage.title),
      cleanText(contentPackage.excerpt),
      cleanText(defaultCta) || "Read the full article on the blog.",
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
        const caption = cleanMultilineText(item.caption || item.body || "");
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
          post_message: cleanMultilineText(item.post_message || item.postMessage || ""),
        };

        normalized.post_message = normalized.post_message || buildFacebookPostMessage(normalized, "");

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
                caption: cleanMultilineText(page.caption || ""),
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
              normalizedPage.post_message = normalizedPage.post_message || buildFacebookPostMessage(normalizedPage, "");
              return normalizedPage;
            })(),
          ];
        }),
      ),
    };
  }

  return {
    buildFacebookComment,
    buildFacebookCommentUrl,
    buildFacebookPostMessage,
    buildFacebookPostUrl,
    buildFallbackFacebookCaption,
    normalizeFacebookDistribution,
    normalizeSocialPack,
  };
}
