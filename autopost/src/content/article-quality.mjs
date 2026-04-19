export function summarizeArticleStage({
  generated,
  job,
  deps,
}) {
  const {
    cleanText,
    countInternalLinks,
    countWords,
    ensureStringArray,
    excerptAddsNewValue,
    excerptClickSignalScore,
    extractOpeningParagraphText,
    frontLoadedClickSignalScore,
    headlineSpecificityScore,
    isPlainObject,
    normalizeGeneratedPageFlow,
    normalizePageFlowLabelFingerprint,
    openingParagraphAddsNewValue,
    openingPromiseAlignmentScore,
    pageFlowLabelLooksStrong,
    pageFlowSummaryLooksStrong,
    pageStartsWithExpectedLead,
    resolveCanonicalContentPackage,
    resolveContentSitePolicy,
    seoDescriptionSignalScore,
    splitHtmlIntoPages,
    titleLooksStrong,
  } = deps;
  const contentPackage = resolveCanonicalContentPackage(generated, job);
  const contentType = contentPackage.content_type || job.content_type || "recipe";
  const sitePolicy = typeof resolveContentSitePolicy === "function" ? resolveContentSitePolicy({}, job) : null;
  const internalLinkMinimum = Math.max(0, Number(sitePolicy?.internalLinks?.minimumCount || 0));
  const contentHtml = String(contentPackage.content_html || "");
  const contentPages = Array.isArray(contentPackage.content_pages) && contentPackage.content_pages.length
    ? contentPackage.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "")
    : splitHtmlIntoPages(contentHtml, contentType).slice(0, 3);
  const pageFlow = normalizeGeneratedPageFlow(Array.isArray(contentPackage.page_flow) ? contentPackage.page_flow : [], contentPages);
  const pageWordCounts = contentPages.map((page) => countWords(page.replace(/<[^>]+>/g, " ")));
  const pageCount = contentPages.length || 1;
  const pageOneInternalLinks = countInternalLinks(contentPages[0] || "", sitePolicy || {});
  const shortestPageWords = pageWordCounts.length ? Math.min(...pageWordCounts) : 0;
  const strongPageOpenings = contentPages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;
  const uniquePageLabels = new Set(pageFlow.map((page) => normalizePageFlowLabelFingerprint(page?.label || "")).filter(Boolean));
  const strongPageLabels = pageFlow.filter((page, index) => pageFlowLabelLooksStrong(page?.label || "", index)).length;
  const strongPageSummaries = pageFlow.filter((page) => pageFlowSummaryLooksStrong(page?.summary || "", page?.label || "")).length;
  const recipe = isPlainObject(contentPackage.recipe) ? contentPackage.recipe : {};
  const wordCount = countWords(contentHtml.replace(/<[^>]+>/g, " "));
  const minimumWords = Number(contentType === "recipe" ? 1200 : 1100);
  const h2Count = (contentHtml.match(/<h2\b/gi) || []).length;
  const internalLinks = countInternalLinks(contentHtml, sitePolicy || {});
  const excerptWords = countWords(contentPackage.excerpt || "");
  const seoWords = countWords(contentPackage.seo_description || "");
  const openingParagraph = extractOpeningParagraphText(contentPackage, deps);
  const titleScore = headlineSpecificityScore(contentPackage.title || "", contentType, job?.topic || "");
  const titleStrong = titleLooksStrong(contentPackage.title || "", job?.topic || "", contentType);
  const titleFrontLoadScore = frontLoadedClickSignalScore(contentPackage.title || "", contentType);
  const excerptFrontLoadScore = frontLoadedClickSignalScore(contentPackage.excerpt || "", contentType);
  const seoFrontLoadScore = frontLoadedClickSignalScore(contentPackage.seo_description || "", contentType);
  const openingFrontLoadScore = frontLoadedClickSignalScore(openingParagraph || "", contentType);
  const openingAlignmentScore = openingPromiseAlignmentScore(contentPackage.title || "", openingParagraph);
  const excerptAddsValue = excerptAddsNewValue(contentPackage.title || "", contentPackage.excerpt || "");
  const openingAddsValue = openingParagraphAddsNewValue(contentHtml, contentPackage.title || "", contentPackage.excerpt || "");
  const excerptSignalScore = excerptClickSignalScore(contentPackage.excerpt || "", contentPackage.title || "", openingParagraph);
  const seoSignalScore = seoDescriptionSignalScore(contentPackage.seo_description || "", contentPackage.title || "", contentPackage.excerpt || "");
  const recipeComplete = contentType !== "recipe" || (ensureStringArray(recipe.ingredients).length > 0 && ensureStringArray(recipe.instructions).length > 0);
  const checks = [];

  if (!cleanText(contentPackage.title || "") || !cleanText(contentPackage.slug || "") || !cleanText(contentHtml.replace(/<[^>]+>/g, " "))) checks.push("missing_core_fields");
  if (contentType === "recipe" && !recipeComplete) checks.push("missing_recipe");
  if (wordCount < minimumWords) checks.push("thin_content");
  if (!titleStrong || titleScore < 3) checks.push("weak_title");
  if (excerptWords < 12 || !excerptAddsValue || excerptSignalScore < 3) checks.push("weak_excerpt");
  if (seoWords < 12 || seoSignalScore < 3) checks.push("weak_seo");
  if (openingAlignmentScore < 2 || !openingAddsValue) checks.push("weak_title_alignment");
  if (pageCount < 2 || pageCount > 3) checks.push("weak_pagination");
  if (pageCount > 1 && shortestPageWords > 0 && shortestPageWords < 140) checks.push("weak_page_balance");
  if (pageCount > 1 && strongPageOpenings < pageCount) checks.push("weak_page_openings");
  if (pageCount > 1 && pageFlow.length < pageCount) checks.push("weak_page_flow");
  if (pageCount > 1 && strongPageLabels < pageCount) checks.push("weak_page_labels");
  if (pageCount > 1 && uniquePageLabels.size < pageCount) checks.push("repetitive_page_labels");
  if (pageCount > 1 && strongPageSummaries < pageCount) checks.push("weak_page_summaries");
  if (internalLinkMinimum > 0 && pageCount > 1 && pageOneInternalLinks < 1) checks.push("weak_reader_path");
  if (h2Count < 2) checks.push("weak_structure");
  if (internalLinkMinimum > 0 && internalLinks < internalLinkMinimum) checks.push("missing_internal_links");

  return {
    checks: Array.from(new Set(checks)),
    metrics: {
      word_count: wordCount,
      minimum_words: minimumWords,
      excerpt_words: excerptWords,
      seo_words: seoWords,
      title_score: titleScore,
      title_strong: titleStrong,
      title_front_load_score: titleFrontLoadScore,
      opening_alignment_score: openingAlignmentScore,
      excerpt_adds_value: excerptAddsValue,
      opening_adds_value: openingAddsValue,
      opening_front_load_score: openingFrontLoadScore,
      excerpt_signal_score: excerptSignalScore,
      excerpt_front_load_score: excerptFrontLoadScore,
      seo_signal_score: seoSignalScore,
      seo_front_load_score: seoFrontLoadScore,
      page_count: pageCount,
      shortest_page_words: shortestPageWords,
      strong_page_openings: strongPageOpenings,
      unique_page_labels: uniquePageLabels.size,
      strong_page_labels: strongPageLabels,
      strong_page_summaries: strongPageSummaries,
      page_one_internal_links: pageOneInternalLinks,
      h2_count: h2Count,
      internal_links: internalLinks,
      internal_link_minimum: internalLinkMinimum,
      recipe_complete: recipeComplete,
    },
  };
}
