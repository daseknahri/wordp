export function createJobUtils(deps) {
  const {
    cleanText,
    normalizeFacebookDistribution,
    toInt,
  } = deps;

  function firstAttachment(...items) {
    return items.find((item) => item && item.id && item.url) || null;
  }

  function assertFacebookConfigured(pages) {
    if (!Array.isArray(pages) || pages.length === 0) {
      throw new Error("No active Facebook pages are configured for article distribution.");
    }
  }

  function assertRecipeDistributionTargets(job, pages) {
    if (Array.isArray(pages) && pages.length > 0) {
      return;
    }

    throw new Error(
      `Job #${toInt(job?.id)} no longer has any active Facebook pages attached. Reopen it in wp-admin, choose at least one page, and try again.`,
    );
  }

  function firstSuccessfulDistributionResult(distribution, contentType = "") {
    const normalized = normalizeFacebookDistribution(distribution, contentType);
    for (const page of Object.values(normalized.pages)) {
      if (page?.post_id || page?.comment_id) {
        return page;
      }
    }

    return null;
  }

  return {
    assertFacebookConfigured,
    assertRecipeDistributionTargets,
    firstAttachment,
    firstSuccessfulDistributionResult,
  };
}
