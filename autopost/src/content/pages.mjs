export function createPageContentHelpers(deps) {
  const {
    countInternalLinks,
    internalLinkTargetsForJob,
    normalizeHtml,
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

  function mergeContentPagesIntoHtml(pages) {
    return pages
      .map((page) => normalizeHtml(page))
      .filter(Boolean)
      .join("\n<!--nextpage-->\n");
  }

  function ensureInternalLinks(contentHtml, job) {
    if (!contentHtml || countInternalLinks(contentHtml) >= 3) {
      return contentHtml;
    }

    const selections = internalLinkTargetsForJob(job).slice(0, 3);
    if (!selections.length) {
      return contentHtml;
    }

    const links = selections
      .map((item) => `[kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
      .join(", ");

    return `${contentHtml}\n<p>Keep reading across the journal: ${links}.</p>`;
  }

  function ensureInternalLinksOnPages(contentPages, job) {
    const pages = Array.isArray(contentPages)
      ? contentPages.map((page) => String(page || "").trim()).filter(Boolean)
      : [];

    if (!pages.length || countInternalLinks(mergeContentPagesIntoHtml(pages)) >= 3) {
      return pages;
    }

    const needed = Math.max(1, 3 - countInternalLinks(mergeContentPagesIntoHtml(pages)));
    const selections = internalLinkTargetsForJob(job).slice(0, needed);
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
        .map((item) => `[kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
        .join(", ");

      return `${page}\n<p>Keep reading across the journal: ${links}.</p>`;
    });
  }

  return {
    ensureInternalLinks,
    ensureInternalLinksOnPages,
    mergeContentPagesIntoHtml,
    normalizeContentPageItem,
  };
}
