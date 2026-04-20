export function createFacebookDistributionHelpers(deps) {
  const {
    buildFacebookCommentUrl,
    buildFacebookPostMessage,
    buildFacebookPostUrl,
    cleanMultilineText,
    cleanText,
    normalizeFacebookDistribution,
  } = deps;

  function resolveSelectedFacebookPages(job, settings) {
    const availablePages = Array.isArray(settings.facebookPages)
      ? settings.facebookPages.filter((page) => page?.page_id && page?.access_token && page?.active !== false)
      : [];
    const pageMap = new Map(availablePages.map((page) => [String(page.page_id), page]));
    const requestedTargets = job?.request_payload?.channel_targets?.facebook;
    const requestedPages = Array.isArray(requestedTargets?.pages)
      ? requestedTargets.pages
      : (Array.isArray(job?.request_payload?.selected_facebook_pages) ? job.request_payload.selected_facebook_pages : []);
    const requestedPageIds = Array.isArray(requestedTargets?.page_ids)
      ? requestedTargets.page_ids.map((pageId) => cleanText(pageId)).filter(Boolean)
      : [];
    const requested = requestedPages
      .filter((page) => page && typeof page === "object")
      .map((page) => ({
        page_id: cleanText(page.page_id || page.pageId),
        label: cleanText(page.label || ""),
      }))
      .filter((page) => page.page_id);
    const resolvedRequested = requested.length
      ? requested
      : requestedPageIds.map((pageId) => ({
        page_id: pageId,
        label: cleanText(pageMap.get(pageId)?.label || ""),
      }));

    if (!resolvedRequested.length) {
      return availablePages.length ? [availablePages[0]] : [];
    }

    return resolvedRequested
      .map((page) => {
        const found = pageMap.get(page.page_id);
        if (!found) {
          return null;
        }

        return {
          ...found,
          label: page.label || found.label,
        };
      })
      .filter(Boolean);
  }

  function resolveFacebookTargets(job, settings) {
    const pages = resolveSelectedFacebookPages(job, settings);

    return {
      pages,
      labels: Array.isArray(pages)
        ? pages.map((page) => cleanText(page?.label || "")).filter(Boolean)
        : [],
      count: Array.isArray(pages) ? pages.length : 0,
    };
  }

  function resolveFacebookDistributionContext({
    job,
    settings,
    distribution,
    facebookCaption = "",
  }) {
    const facebookTargets = resolveFacebookTargets(job, settings);
    const selectedPages = Array.isArray(facebookTargets?.pages) ? facebookTargets.pages : [];

    return {
      facebookTargets,
      selectedPages,
      distribution: seedLegacyFacebookDistribution(
        distribution,
        selectedPages,
        job,
        facebookCaption,
      ),
    };
  }

  function seedLegacyFacebookDistribution(distribution, pages, job, facebookCaption) {
    const normalized = normalizeFacebookDistribution(distribution, job?.content_type || "recipe");
    if (Object.keys(normalized.pages).length > 0) {
      return normalized;
    }

    const legacyPostId = cleanText(job?.facebook_post_id || "");
    const legacyCommentId = cleanText(job?.facebook_comment_id || "");
    if (!legacyPostId && !legacyCommentId) {
      return normalized;
    }

    const page = pages[0];
    if (!page?.page_id) {
      return normalized;
    }

    normalized.pages[page.page_id] = {
      page_id: page.page_id,
      label: cleanText(page.label || ""),
      angle_key: "",
      hook: "",
      caption: cleanMultilineText(facebookCaption || ""),
      cta_hint: "",
      post_message: buildFacebookPostMessage({ hook: "", caption: cleanMultilineText(facebookCaption || "") }, ""),
      post_id: legacyPostId,
      post_url: buildFacebookPostUrl(legacyPostId),
      comment_message: "",
      comment_id: legacyCommentId,
      comment_url: buildFacebookCommentUrl(legacyPostId, legacyCommentId),
      status: legacyPostId && legacyCommentId ? "completed" : (legacyPostId ? "comment_failed" : "post_failed"),
      error: "",
    };

    return normalized;
  }

  return {
    resolveFacebookDistributionContext,
    resolveFacebookTargets,
    resolveSelectedFacebookPages,
    seedLegacyFacebookDistribution,
  };
}
