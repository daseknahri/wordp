const DEFAULT_PAGE_BREAK_MARKER = "<!--nextpage-->";
const DEFAULT_SHORTCODE_TAG = "internal_link";
const DEFAULT_MIN_INTERNAL_LINKS = 3;
const DEFAULT_JOURNAL_LABEL = "Keep reading across the site";
const DEFAULT_PUBLICATION_NAME = "this publication";

const DEFAULT_INTERNAL_LINK_LIBRARY = {
  shared: [],
  recipe: [],
  food_fact: [],
  food_story: [],
};

function isPlainObject(value) {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function safeObject(value) {
  return isPlainObject(value) ? value : {};
}

function cleanString(value, fallback = "") {
  const text = String(value ?? "").trim();
  return text || fallback;
}

function normalizeLinkTarget(target) {
  if (!isPlainObject(target)) {
    return null;
  }

  const slug = cleanString(target.slug);
  const label = cleanString(target.label || target.title || target.name);
  if (!slug || !label) {
    return null;
  }

  return { slug, label };
}

function normalizeLinkTargetList(value, fallback = []) {
  const list = Array.isArray(value) ? value : fallback;
  const seen = new Set();

  return list
    .map((target) => normalizeLinkTarget(target))
    .filter((target) => {
      const slug = String(target?.slug || "");
      if (!slug || seen.has(slug)) {
        return false;
      }

      seen.add(slug);
      return true;
    });
}

function normalizeInternalLinkLibrary(library) {
  const source = safeObject(library);

  return {
    shared: normalizeLinkTargetList(source.shared, DEFAULT_INTERNAL_LINK_LIBRARY.shared),
    recipe: normalizeLinkTargetList(source.recipe, DEFAULT_INTERNAL_LINK_LIBRARY.recipe),
    food_fact: normalizeLinkTargetList(source.food_fact, DEFAULT_INTERNAL_LINK_LIBRARY.food_fact),
    food_story: normalizeLinkTargetList(source.food_story, DEFAULT_INTERNAL_LINK_LIBRARY.food_story),
  };
}

function hasLinkTargets(library) {
  return ["shared", "recipe", "food_fact", "food_story"].some((key) => Array.isArray(library?.[key]) && library[key].length > 0);
}

function resolveJobRequestPayload(job) {
  if (!job || typeof job !== "object") {
    return {};
  }

  return safeObject(job.request_payload);
}

function coerceSitePolicy(candidate, job = {}) {
  if (candidate?.internalLinks && candidate?.pageBreakMarker) {
    return candidate;
  }

  return resolveContentSitePolicy(candidate, job);
}

export function resolveContentSitePolicy(settings = {}, job = {}) {
  const contentMachine = safeObject(settings.contentMachine || settings.content_machine);
  const publicationProfile = safeObject(contentMachine.publicationProfile || contentMachine.publication_profile);
  const requestPayload = resolveJobRequestPayload(job);
  const jobMachine = safeObject(requestPayload.content_machine);
  const rawSitePolicy = safeObject(
    contentMachine.sitePolicy ||
      contentMachine.site_policy ||
      jobMachine.sitePolicy ||
      jobMachine.site_policy,
  );
  const rawInternalLinks = safeObject(rawSitePolicy.internalLinks || rawSitePolicy.internal_links);
  const rawPagination = safeObject(rawSitePolicy.pagination);
  const library = normalizeInternalLinkLibrary(rawInternalLinks.library);
  const requestedMinimumCount = Math.max(
    0,
    Number(rawInternalLinks.minimumCount ?? rawInternalLinks.minimum_count ?? DEFAULT_MIN_INTERNAL_LINKS) || DEFAULT_MIN_INTERNAL_LINKS,
  );
  const minimumCount = hasLinkTargets(library) ? requestedMinimumCount : 0;

  return {
    publicationName: cleanString(
      rawSitePolicy.publicationName ||
        rawSitePolicy.publication_name ||
        publicationProfile.name ||
        requestPayload.site_name ||
        settings.siteName,
      DEFAULT_PUBLICATION_NAME,
    ),
    journalLabel: cleanString(
      rawInternalLinks.journalLabel ||
        rawInternalLinks.journal_label ||
        rawSitePolicy.journalLabel ||
        rawSitePolicy.journal_label,
      DEFAULT_JOURNAL_LABEL,
    ),
    pageBreakMarker: cleanString(
      rawPagination.marker ||
        rawSitePolicy.pageBreakMarker ||
        rawSitePolicy.page_break_marker,
      DEFAULT_PAGE_BREAK_MARKER,
    ),
    internalLinks: {
      minimumCount,
      shortcodeTag: cleanString(
        rawInternalLinks.shortcodeTag ||
          rawInternalLinks.shortcode_tag,
        DEFAULT_SHORTCODE_TAG,
      ),
      library,
    },
  };
}

export function countInternalLinks(contentHtml, sitePolicy = {}) {
  const policy = coerceSitePolicy(sitePolicy);
  const shortcodeTag = String(policy.internalLinks.shortcodeTag || DEFAULT_SHORTCODE_TAG).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const shortcodePattern = new RegExp(`\\[${shortcodeTag}\\s+slug=`, "gi");
  const shortcodeCount = (String(contentHtml || "").match(shortcodePattern) || []).length;
  const anchorCount = (String(contentHtml || "").match(/<a\s+[^>]*href=/gi) || []).length;
  return shortcodeCount + anchorCount;
}

export function internalLinkTargetsForJob(job, sitePolicy = {}) {
  const policy = coerceSitePolicy(sitePolicy, job);
  const library = policy.internalLinks.library || DEFAULT_INTERNAL_LINK_LIBRARY;
  const contentType = cleanString(job?.content_type, "recipe");
  const targets = [...(library[contentType] || library.recipe || []), ...(library.shared || [])];
  const seen = new Set();

  return targets.filter((target) => {
    const slug = String(target?.slug || "").trim();
    if (!slug || seen.has(slug)) {
      return false;
    }

    seen.add(slug);
    return true;
  });
}

export function buildInternalLinkMarkup(target, sitePolicy = {}) {
  const policy = coerceSitePolicy(sitePolicy);
  const normalized = normalizeLinkTarget(target);
  if (!normalized) {
    return "";
  }

  const shortcodeTag = cleanString(policy.internalLinks.shortcodeTag, DEFAULT_SHORTCODE_TAG);
  return `[${shortcodeTag} slug="${normalized.slug}"]${normalized.label}[/${shortcodeTag}]`;
}
