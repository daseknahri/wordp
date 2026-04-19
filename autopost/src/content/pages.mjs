export function createPageContentHelpers(deps) {
  const {
    buildInternalLinkMarkup,
    countInternalLinks,
    internalLinkTargetsForJob,
    normalizeHtml,
    resolveContentSitePolicy,
  } = deps;

  function normalizeContentPageItem(item) {
    if (typeof item === "string") {
      return normalizeHtml(item);
    }

    if (item && typeof item === "object" && !Array.isArray(item)) {
      return normalizeHtml(item.html || item.content_html || item.contentHtml || item.content || item.body || item.text || "");
    }

    return "";
  }

  function mergeContentPagesIntoHtml(pages, sitePolicy = null, job = null) {
    const policy = resolveContentSitePolicy(sitePolicy, job);
    return pages
      .map((page) => normalizeHtml(page))
      .filter(Boolean)
      .join(`\n${policy.pageBreakMarker}\n`);
  }

  function ensureInternalLinks(contentHtml, job, sitePolicy = null) {
    const policy = resolveContentSitePolicy(sitePolicy, job);
    const minimumCount = Math.max(0, Number(policy.internalLinks.minimumCount || 0));
    if (!contentHtml || countInternalLinks(contentHtml, policy) >= minimumCount) {
      return contentHtml;
    }

    const needed = Math.max(1, minimumCount - countInternalLinks(contentHtml, policy));
    const selections = internalLinkTargetsForJob(job, policy).slice(0, needed);
    if (!selections.length) {
      return contentHtml;
    }

    const links = selections
      .map((item) => buildInternalLinkMarkup(item, policy))
      .join(", ");

    return `${contentHtml}\n<p>${policy.journalLabel}: ${links}.</p>`;
  }

  function ensureInternalLinksOnPages(contentPages, job, sitePolicy = null) {
    const policy = resolveContentSitePolicy(sitePolicy, job);
    const minimumCount = Math.max(0, Number(policy.internalLinks.minimumCount || 0));
    const pages = Array.isArray(contentPages)
      ? contentPages.map((page) => String(page || "").trim()).filter(Boolean)
      : [];

    if (!pages.length || countInternalLinks(mergeContentPagesIntoHtml(pages, policy, job), policy) >= minimumCount) {
      return pages;
    }

    const needed = Math.max(1, minimumCount - countInternalLinks(mergeContentPagesIntoHtml(pages, policy, job), policy));
    const selections = internalLinkTargetsForJob(job, policy).slice(0, needed);
    if (!selections.length) {
      return pages;
    }

    const buckets = pages.map(() => []);
    selections.forEach((item, index) => {
      buckets[index % pages.length].push(item);
    });

    return pages.map((page, index) => {
      const bucket = buckets[index] || [];
      if (!bucket.length) {
        return page;
      }

      const links = bucket
        .map((item) => buildInternalLinkMarkup(item, policy))
        .join(", ");

      return `${page}\n<p>${policy.journalLabel}: ${links}.</p>`;
    });
  }

  return {
    ensureInternalLinks,
    ensureInternalLinksOnPages,
    mergeContentPagesIntoHtml,
    normalizeContentPageItem,
  };
}
