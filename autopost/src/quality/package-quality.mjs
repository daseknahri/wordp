export function qualityCheckReasonMessage(check) {
  const messages = {
    missing_core_fields: "Missing title, slug, or article body.",
    missing_recipe: "Recipe card is incomplete.",
    missing_manual_images: "Manual-only image slots are still missing.",
    duplicate_conflict: "Title or slug conflicts with an existing post.",
    missing_target_pages: "No Facebook pages are attached.",
    thin_content: "Article body is still too thin.",
    weak_title: "Headline promise is still soft.",
    weak_excerpt: "Excerpt is too generic or slow to earn the click.",
    weak_seo: "SEO line is too weak or buried.",
    weak_title_alignment: "Page-one opening does not cash the headline fast enough.",
    weak_pagination: "Article split is not a strong 2-3 page flow yet.",
    weak_page_balance: "One article page is too thin.",
    weak_page_openings: "One page opens weakly.",
    weak_page_flow: "Page labels or summaries still need work.",
    weak_page_labels: "Page labels are too generic.",
    repetitive_page_labels: "Page labels feel repetitive.",
    weak_page_summaries: "Page summaries are too thin.",
    weak_structure: "Article needs stronger H2 structure.",
    missing_internal_links: "Article needs more internal links.",
    social_pack_incomplete: "Social pack does not cover every selected page.",
    social_pack_repetitive: "Selected variants still feel repetitive.",
    social_hooks_repetitive: "Hooks still feel repetitive.",
    social_openings_repetitive: "Caption openings still feel repetitive.",
    social_angles_repetitive: "Angles are not varied enough.",
    social_hook_forms_thin: "Hook sentence patterns are too narrow.",
    weak_social_copy: "Some selected social copy is still weak.",
    weak_social_lead: "Lead Facebook variant is not strong enough yet.",
    social_specificity_thin: "Too few variants feel concrete and article-specific.",
    social_anchor_thin: "Too few variants are anchored in a real detail.",
    social_relatability_thin: "Too few variants feel recognizably real.",
    social_recognition_thin: "Too few variants create an immediate self-recognition moment.",
    social_conversation_thin: "Too few variants feel naturally discussable.",
    social_savvy_thin: "Too few variants feel like a smarter move.",
    social_identity_shift_thin: "Too few variants create a real old-default vs better-move snap.",
    social_novelty_thin: "Too few variants add a fresh detail beyond the title.",
    social_front_load_thin: "Too few variants front-load the useful thing fast enough.",
    social_curiosity_thin: "Too few variants create honest curiosity.",
    social_resolution_thin: "Too few variants resolve the hook early.",
    social_contrast_thin: "Too few variants use a clean contrast.",
    social_pain_points_thin: "Too few variants frame a clear problem.",
    social_payoffs_thin: "Too few variants frame a clear payoff.",
    social_proof_thin: "Too few variants carry a believable clue or proof.",
    social_actionability_thin: "Too few variants feel immediately usable.",
    social_immediacy_thin: "Too few variants feel relevant right now.",
    social_consequence_thin: "Too few variants make the cost of ignoring it feel real.",
    social_habit_shift_thin: "Too few variants break the old habit cleanly.",
    social_focus_thin: "Too few variants stay centered on one promise.",
    social_promise_sync_thin: "Too few variants line up cleanly with the article promise.",
    social_scannability_thin: "Too few variants are easy to scan fast.",
    social_two_step_thin: "Too few variants make line 1 and line 2 do different jobs.",
    image_not_ready: "Required images are not ready yet.",
  };
  return messages[check] || String(check || "").trim().replace(/_/g, " ");
}

export function buildEditorialReadinessSummary({
  qualityStatus = "warn",
  qualityScore = 0,
  titleStrong = false,
  openingAlignmentScore = 0,
  pageCount = 1,
  strongPageOpenings = 0,
  strongPageSummaries = 0,
  targetPages = 0,
  strongSocialVariants = 0,
  leadSocialScore = 0,
  leadSocialSpecific = false,
  leadSocialFrontLoaded = false,
  leadSocialPromiseSync = false,
  blockingChecks = [],
  warningChecks = [],
} = {}) {
  const normalizedStatus = String(qualityStatus || "").toLowerCase();
  const readiness = normalizedStatus === "block"
    ? "blocked"
    : (
      qualityScore >= 88
      && Boolean(titleStrong)
      && Number(openingAlignmentScore) >= 2
      && Number(pageCount) >= 2
      && Number(pageCount) <= 3
      && Number(strongPageOpenings) >= Number(pageCount)
      && Number(strongPageSummaries) >= Number(pageCount)
      && Number(strongSocialVariants) >= Math.max(1, Number(targetPages) || 1)
      && Number(leadSocialScore) >= 18
      && Boolean(leadSocialSpecific)
      && Boolean(leadSocialFrontLoaded)
      && Boolean(leadSocialPromiseSync)
    )
      ? "ready"
      : "review";

  const highlights = [];
  if (titleStrong && Number(openingAlignmentScore) >= 2) {
    highlights.push("Headline and page-one opening land the same promise.");
  }
  if (Number(pageCount) >= 2 && Number(pageCount) <= 3 && Number(strongPageSummaries) >= Number(pageCount)) {
    highlights.push(`Article flow feels intentional across ${pageCount} pages.`);
  }
  if (Number(strongSocialVariants) >= Math.max(1, Number(targetPages) || 1) && Number(leadSocialScore) >= 18) {
    highlights.push("Social pack has a strong lead and enough usable variants.");
  }
  if (!highlights.length && normalizedStatus !== "block" && Number(qualityScore) >= 75) {
    highlights.push("Core package is usable for live testing.");
  }

  const watchouts = [...blockingChecks, ...warningChecks]
    .slice(0, 3)
    .map((check) => qualityCheckReasonMessage(check));

  if (!watchouts.length && readiness === "ready") {
    watchouts.push("No major editorial warnings.");
  }

  return {
    editorial_readiness: readiness,
    editorial_highlights: highlights.slice(0, 3),
    editorial_watchouts: watchouts,
  };
}

export function extractValidatorSummary(generated, deps) {
  const contentMachine = deps.isPlainObject(generated?.content_machine) ? generated.content_machine : {};
  return deps.isPlainObject(contentMachine.validator_summary) ? contentMachine.validator_summary : {};
}

export function extractGeneratedContractVersions(generated, deps) {
  const contentMachine = deps.isPlainObject(generated?.content_machine) ? generated.content_machine : {};
  const versions = deps.isPlainObject(contentMachine.contract_versions) ? contentMachine.contract_versions : {};
  const channels = deps.isPlainObject(generated?.channels) ? generated.channels : {};
  const facebookChannel = deps.isPlainObject(channels.facebook) ? channels.facebook : {};
  const facebookGroupsChannel = deps.isPlainObject(channels.facebook_groups) ? channels.facebook_groups : {};
  const pinterestChannel = deps.isPlainObject(channels.pinterest) ? channels.pinterest : {};
  const contentPackage = deps.isPlainObject(generated?.content_package) ? generated.content_package : {};

  return {
    content_package: deps.cleanText(versions.content_package || contentPackage.contract_version || ""),
    channel_adapters: deps.cleanText(
      versions.channel_adapters
      || facebookChannel.contract_version
      || facebookGroupsChannel.contract_version
      || pinterestChannel.contract_version
      || "",
    ),
  };
}

export function resolveContractMeta(generated, deps) {
  const contentMachine = deps.isPlainObject(generated?.content_machine) ? generated.content_machine : {};
  const contracts = deps.isPlainObject(contentMachine.contracts) ? contentMachine.contracts : {};
  const hasLegacyFlag = typeof contracts.legacy_job === "boolean";
  const hasTypedFlag = typeof contracts.typed_contract_job === "boolean";
  let legacyJob = hasLegacyFlag ? Boolean(contracts.legacy_job) : null;
  let typedJob = hasTypedFlag ? Boolean(contracts.typed_contract_job) : null;
  if (!hasLegacyFlag && !hasTypedFlag) {
    legacyJob = true;
    typedJob = false;
  } else if (legacyJob === null) {
    legacyJob = !typedJob;
  } else if (typedJob === null) {
    typedJob = !legacyJob;
  }

  return {
    legacy_job: legacyJob,
    typed_contract_job: typedJob,
    fallbacks: deps.isPlainObject(contracts.fallbacks) ? contracts.fallbacks : {},
  };
}

export function isCanonicalContractVersion(value, prefix) {
  return String(value || "").trim().startsWith(prefix);
}

export function collectCanonicalContractChecks({ generated, job = null, targetPages = 0, deps }) {
  const contractMeta = resolveContractMeta(generated, deps);
  if (!contractMeta.typed_contract_job) {
    return {
      package_contract_enforced: false,
      channel_contract_enforced: false,
      warning_checks: [],
    };
  }

  const versions = extractGeneratedContractVersions(generated, deps);
  const packageContractEnforced = isCanonicalContractVersion(versions.content_package, "content-package-v");
  const channelContractEnforced = isCanonicalContractVersion(versions.channel_adapters, "channel-adapters-v");
  const rawPackage = deps.isPlainObject(generated?.content_package) ? generated.content_package : null;
  const rawChannels = deps.isPlainObject(generated?.channels) ? generated.channels : {};
  const rawFacebook = deps.isPlainObject(rawChannels.facebook) ? rawChannels.facebook : null;
  const rawFacebookGroups = deps.isPlainObject(rawChannels.facebook_groups) ? rawChannels.facebook_groups : null;
  const rawPinterest = deps.isPlainObject(rawChannels.pinterest) ? rawChannels.pinterest : null;
  const contentType = deps.cleanText(rawPackage?.content_type || generated?.content_type || job?.content_type || "recipe") || "recipe";
  const warningChecks = [];
  const fallbacks = contractMeta.fallbacks || {};
  const packageFallback = Boolean(fallbacks.content_package);
  const facebookFallback = Boolean(fallbacks.facebook);
  const facebookGroupsFallback = Boolean(fallbacks.facebook_groups);
  const pinterestFallback = Boolean(fallbacks.pinterest);

  if (packageContractEnforced) {
    const hasPackagePages = Array.isArray(rawPackage?.content_pages)
      && rawPackage.content_pages.some((page) => deps.cleanText(String(page || "").replace(/<[^>]+>/g, " ")) !== "");
    const hasPackageBody = deps.cleanText(String(rawPackage?.content_html || "").replace(/<[^>]+>/g, " ")) !== "" || hasPackagePages;
    const packageLooksCanonical = Boolean(
      rawPackage
      && deps.cleanText(rawPackage.contract_version || "")
      && deps.cleanText(rawPackage.package_shape || "") === "canonical_content_package"
      && deps.cleanText(rawPackage.source_layer || "") === "article_engine"
      && deps.cleanText(rawPackage.title || "")
      && deps.cleanText(rawPackage.slug || "")
      && hasPackageBody
      && Array.isArray(rawPackage.page_flow)
      && rawPackage.page_flow.length > 0
      && deps.isPlainObject(rawPackage.profile)
      && (!deps.buildContentTypeProfile(contentType).recipe_required || deps.isPlainObject(rawPackage.recipe))
    );

    if (!packageLooksCanonical || packageFallback) {
      warningChecks.push("package_contract_drift");
    }
  }

  if (channelContractEnforced) {
    const facebookRequired = targetPages > 0
      || Boolean(rawFacebook)
      || Array.isArray(generated?.social_pack)
      || deps.isPlainObject(generated?.facebook_distribution);
    if (facebookRequired) {
      const facebookLooksCanonical = Boolean(
        rawFacebook
        && deps.cleanText(rawFacebook.contract_version || "")
        && deps.cleanText(rawFacebook.input_package || "") === "content_package"
        && deps.isPlainObject(rawFacebook.profile)
        && Array.isArray(rawFacebook.selected)
        && deps.isPlainObject(rawFacebook.distribution)
      );
      if (!facebookLooksCanonical || facebookFallback) {
        warningChecks.push("facebook_adapter_contract_drift");
      }
    }

    const facebookGroupsLooksCanonical = Boolean(
      rawFacebookGroups
      && deps.cleanText(rawFacebookGroups.contract_version || "")
      && deps.cleanText(rawFacebookGroups.input_package || "") === "content_package"
      && deps.isPlainObject(rawFacebookGroups.profile)
      && deps.isPlainObject(rawFacebookGroups.draft)
    );
    if (!facebookGroupsLooksCanonical || facebookGroupsFallback) {
      warningChecks.push("facebook_groups_adapter_contract_drift");
    }

    const pinterestLooksCanonical = Boolean(
      rawPinterest
      && deps.cleanText(rawPinterest.contract_version || "")
      && deps.cleanText(rawPinterest.input_package || "") === "content_package"
      && deps.isPlainObject(rawPinterest.profile)
      && deps.isPlainObject(rawPinterest.draft)
    );
    if (!pinterestLooksCanonical || pinterestFallback) {
      warningChecks.push("pinterest_adapter_contract_drift");
    }
  }

  return {
    package_contract_enforced: packageContractEnforced,
    channel_contract_enforced: channelContractEnforced,
    warning_checks: Array.from(new Set(warningChecks)),
  };
}

export function filterChannelWarningChecks(checks, channel = "facebook") {
  if (!Array.isArray(checks)) {
    return [];
  }

  if (channel === "facebook") {
    return checks.filter((check) => /^social_/.test(String(check || "")) || check === "missing_target_pages" || check === "facebook_adapter_contract_drift");
  }

  if (channel === "facebook_groups") {
    return checks.filter((check) => check === "facebook_groups_adapter_contract_drift");
  }

  if (channel === "pinterest") {
    return checks.filter((check) => check === "pinterest_adapter_contract_drift");
  }

  return [];
}

export function buildQualitySummary({
  job,
  generated,
  settings = {},
  options = {},
  deps,
}) {
  const contentPackage = deps.resolveCanonicalContentPackage(generated, job);
  const facebookChannel = deps.resolveFacebookChannelAdapter(generated, job);
  const contentType = deps.cleanText(contentPackage.content_type || job?.content_type || "recipe") || "recipe";
  const articleSignals = deps.isPlainObject(contentPackage.article_signals)
    ? contentPackage.article_signals
    : deps.buildArticleSocialSignals(contentPackage, contentType);
  const contentHtml = String(contentPackage.content_html || "");
  const contentPages = Array.isArray(contentPackage.content_pages) && contentPackage.content_pages.length
    ? contentPackage.content_pages
      .map((page) => String(page || ""))
      .filter((page) => deps.cleanText(page.replace(/<[^>]+>/g, " ")) !== "")
    : deps.splitHtmlIntoPages(contentHtml, contentType).slice(0, 3);
  const pageFlow = deps.normalizeGeneratedPageFlow(
    Array.isArray(contentPackage.page_flow) ? contentPackage.page_flow : [],
    contentPages,
  );
  const pageWordCounts = contentPages.map((page) => deps.cleanText(page.replace(/<[^>]+>/g, " ")).split(/\s+/).filter(Boolean).length);
  const pageCount = contentPages.length || 1;
  const shortestPageWords = pageWordCounts.length ? Math.min(...pageWordCounts) : 0;
  const strongPageOpenings = contentPages.filter((page, index) => deps.pageStartsWithExpectedLead(page, index)).length;
  const uniquePageLabels = new Set(pageFlow.map((page) => deps.normalizePageFlowLabelFingerprint(page?.label || "")).filter(Boolean));
  const strongPageLabels = pageFlow.filter((page, index) => deps.pageFlowLabelLooksStrong(page?.label || "", index)).length;
  const strongPageSummaries = pageFlow.filter((page) => deps.pageFlowSummaryLooksStrong(page?.summary || "", page?.label || "")).length;
  const optionSelectedPages = Array.isArray(options.selectedPages) ? options.selectedPages : [];
  const facebookTargets = typeof deps.resolveFacebookTargets === "function"
    ? deps.resolveFacebookTargets(job, settings)
    : { count: optionSelectedPages.length, pages: optionSelectedPages };
  const targetCount = Math.max(
    0,
    Number(
      options.targetPages
      ?? options.targetCount
      ?? facebookTargets?.count
      ?? optionSelectedPages.length
      ?? 0,
    ),
  );
  const socialPack = Array.isArray(facebookChannel.selected) ? facebookChannel.selected : [];
  const fingerprints = socialPack.map((variant) => deps.normalizeSocialFingerprint(variant)).filter(Boolean);
  const uniqueFingerprints = new Set(fingerprints);
  const uniqueHookFingerprints = new Set(socialPack.map((variant) => deps.normalizeHookFingerprint(variant)).filter(Boolean));
  const uniqueCaptionOpenings = new Set(socialPack.map((variant) => deps.normalizeCaptionOpeningFingerprint(variant)).filter(Boolean));
  const uniqueAngleKeys = new Set(socialPack.map((variant) => deps.normalizeAngleKey(variant?.angle_key || "", contentType)).filter(Boolean));
  const uniqueHookForms = new Set(socialPack.map((variant) => deps.classifySocialHookForm(variant)).filter(Boolean));
  const strongSocialVariants = socialPack.filter((variant) => !deps.socialVariantLooksWeak(variant, contentPackage.title || "", contentType, articleSignals)).length;
  const selectedSocialSummary = deps.summarizeSelectedSocialPack(socialPack, contentPackage, contentType);
  const signalTargets = deps.desiredSocialSignalTargets(targetCount);
  const recipe = deps.isPlainObject(contentPackage.recipe) ? contentPackage.recipe : {};
  const sitePolicy = typeof deps.resolveContentSitePolicy === "function" ? deps.resolveContentSitePolicy(settings, job) : null;
  const internalLinkMinimum = Math.max(0, Number(sitePolicy?.internalLinks?.minimumCount || 0));
  const wordCount = deps.cleanText(contentHtml.replace(/<[^>]+>/g, " ")).split(/\s+/).filter(Boolean).length;
  const minimumWords = Number(contentType === "recipe" ? 1200 : 1100);
  const h2Count = (contentHtml.match(/<h2\b/gi) || []).length;
  const internalLinks = deps.countInternalLinks(contentHtml, sitePolicy || {});
  const excerptWords = deps.cleanText(contentPackage.excerpt || "").split(/\s+/).filter(Boolean).length;
  const seoWords = deps.cleanText(contentPackage.seo_description || "").split(/\s+/).filter(Boolean).length;
  const openingParagraph = deps.extractOpeningParagraphText(contentPackage, deps);
  const titleScore = deps.headlineSpecificityScore(contentPackage.title || "", contentType, job?.topic || "");
  const titleStrong = deps.titleLooksStrong(contentPackage.title || "", job?.topic || "", contentType);
  const titleFrontLoadScore = deps.frontLoadedClickSignalScore(contentPackage.title || "", contentType);
  const excerptFrontLoadScore = deps.frontLoadedClickSignalScore(contentPackage.excerpt || "", contentType);
  const seoFrontLoadScore = deps.frontLoadedClickSignalScore(contentPackage.seo_description || "", contentType);
  const openingFrontLoadScore = deps.frontLoadedClickSignalScore(openingParagraph || "", contentType);
  const openingAlignmentScore = deps.openingPromiseAlignmentScore(contentPackage.title || "", openingParagraph);
  const excerptAddsValue = deps.excerptAddsNewValue(contentPackage.title || "", contentPackage.excerpt || "");
  const openingAddsValue = deps.openingParagraphAddsNewValue(contentHtml, contentPackage.title || "", contentPackage.excerpt || "");
  const excerptSignalScore = deps.excerptClickSignalScore(contentPackage.excerpt || "", contentPackage.title || "", openingParagraph);
  const seoSignalScore = deps.seoDescriptionSignalScore(contentPackage.seo_description || "", contentPackage.title || "", contentPackage.excerpt || "");
  const recipeComplete = contentType !== "recipe" || (
    deps.ensureStringArray(recipe.ingredients).length > 0
    && deps.ensureStringArray(recipe.instructions).length > 0
  );
  const featuredImageReady = Boolean(options.featuredImage?.id || job?.featured_image?.id || job?.blog_image?.id);
  const facebookImageReady = Boolean(
    options.facebookImage?.id
    || job?.facebook_image_result?.id
    || job?.facebook_image?.id
    || options.featuredImage?.id
    || job?.featured_image?.id
    || job?.blog_image?.id
  );
  const imageReady = featuredImageReady && facebookImageReady;
  const duplicateRisk = Boolean(options.duplicateRisk);
  const validatorSummary = extractValidatorSummary(generated, deps);
  const contractMeta = resolveContractMeta(generated, deps);
  const strictContractMode = Boolean(
    settings?.content_machine?.contracts?.strict_contract_mode
    || settings?.contentMachine?.contracts?.strict_contract_mode
    || settings?.strict_contract_mode
    || settings?.strictContractMode
  );
  const contractChecks = collectCanonicalContractChecks({
    generated,
    job,
    targetPages: targetCount,
    deps,
  });
  const contractBlockingChecks = (strictContractMode && contractMeta.typed_contract_job)
    ? contractChecks.warning_checks.filter((check) => String(check || "").endsWith("_contract_drift"))
    : [];

  const blockingChecks = [...contractBlockingChecks];
  const warningChecks = contractChecks.warning_checks.filter((check) => !contractBlockingChecks.includes(check));

  if (!deps.cleanText(contentPackage.title || "") || !deps.cleanText(contentPackage.slug || "") || !deps.cleanText(contentHtml.replace(/<[^>]+>/g, " "))) {
    blockingChecks.push("missing_core_fields");
  }
  if (contentType === "recipe" && !recipeComplete) {
    blockingChecks.push("missing_recipe");
  }
  if (settings.imageGenerationMode === "manual_only" && (!featuredImageReady || !facebookImageReady)) {
    blockingChecks.push("missing_manual_images");
  }
  if (duplicateRisk) {
    blockingChecks.push("duplicate_conflict");
  }
  if (targetCount < 1) {
    blockingChecks.push("missing_target_pages");
  }
  if (wordCount < minimumWords) {
    warningChecks.push("thin_content");
  }
  if (!titleStrong || titleScore < 3) {
    warningChecks.push("weak_title");
  }
  if (excerptWords < 12 || !excerptAddsValue || excerptSignalScore < 3) {
    warningChecks.push("weak_excerpt");
  }
  if (seoWords < 12 || seoSignalScore < 3) {
    warningChecks.push("weak_seo");
  }
  if (openingAlignmentScore < 2 || !openingAddsValue) {
    warningChecks.push("weak_title_alignment");
  }
  if (pageCount < 2 || pageCount > 3) {
    warningChecks.push("weak_pagination");
  }
  if (pageCount > 1 && shortestPageWords > 0 && shortestPageWords < 140) {
    warningChecks.push("weak_page_balance");
  }
  if (pageCount > 1 && strongPageOpenings < pageCount) {
    warningChecks.push("weak_page_openings");
  }
  if (pageCount > 1 && pageFlow.length < pageCount) {
    warningChecks.push("weak_page_flow");
  }
  if (pageCount > 1 && strongPageLabels < pageCount) {
    warningChecks.push("weak_page_labels");
  }
  if (pageCount > 1 && uniquePageLabels.size < pageCount) {
    warningChecks.push("repetitive_page_labels");
  }
  if (pageCount > 1 && strongPageSummaries < pageCount) {
    warningChecks.push("weak_page_summaries");
  }
  if (h2Count < 2) {
    warningChecks.push("weak_structure");
  }
  if (internalLinkMinimum > 0 && internalLinks < internalLinkMinimum) {
    warningChecks.push("missing_internal_links");
  }
  if (socialPack.length < Math.max(1, targetCount)) {
    warningChecks.push("social_pack_incomplete");
  }
  if (socialPack.length > 0 && uniqueFingerprints.size < Math.min(socialPack.length, Math.max(1, targetCount))) {
    warningChecks.push("social_pack_repetitive");
  }
  if (socialPack.length > 1 && uniqueHookFingerprints.size < Math.min(socialPack.length, Math.max(1, targetCount))) {
    warningChecks.push("social_hooks_repetitive");
  }
  if (socialPack.length > 1 && uniqueCaptionOpenings.size < Math.min(socialPack.length, Math.max(1, targetCount))) {
    warningChecks.push("social_openings_repetitive");
  }
  if (targetCount > 1 && uniqueAngleKeys.size < Math.min(targetCount, deps.angleDefinitionsForType(contentType).length)) {
    warningChecks.push("social_angles_repetitive");
  }
  if (targetCount > 1 && uniqueHookForms.size < Math.max(2, Math.min(3, targetCount))) {
    warningChecks.push("social_hook_forms_thin");
  }
  if (strongSocialVariants < Math.max(1, targetCount)) {
    warningChecks.push("weak_social_copy");
  }
  if (
    targetCount > 0
    && (
      selectedSocialSummary.lead_social_score < 16
      || !selectedSocialSummary.lead_social_specific
      || !selectedSocialSummary.lead_social_novelty
      || !selectedSocialSummary.lead_social_anchored
      || !selectedSocialSummary.lead_social_relatable
      || !selectedSocialSummary.lead_social_recognition
      || !selectedSocialSummary.lead_social_front_loaded
      || !selectedSocialSummary.lead_social_focused
      || !selectedSocialSummary.lead_social_promise_sync
      || !selectedSocialSummary.lead_social_scannable
      || !selectedSocialSummary.lead_social_two_step
      || ((selectedSocialSummary.lead_social_curiosity || selectedSocialSummary.lead_social_contrast) && !selectedSocialSummary.lead_social_resolved)
      || (
        !selectedSocialSummary.lead_social_pain_point
        && !selectedSocialSummary.lead_social_payoff
        && !selectedSocialSummary.lead_social_consequence
        && !selectedSocialSummary.lead_social_habit_shift
        && !selectedSocialSummary.lead_social_savvy
        && !selectedSocialSummary.lead_social_identity_shift
      )
    )
  ) {
    warningChecks.push("weak_social_lead");
  }
  if (selectedSocialSummary.specific_social_variants < Math.max(1, Math.min(targetCount || 1, 2))) {
    warningChecks.push("social_specificity_thin");
  }
  if (targetCount > 0 && selectedSocialSummary.anchored_variants < Math.max(1, Math.min(targetCount || 1, 2))) {
    warningChecks.push("social_anchor_thin");
  }
  if (targetCount > 0 && selectedSocialSummary.novelty_variants < Math.max(1, Math.min(targetCount || 1, 2))) {
    warningChecks.push("social_novelty_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.relatable_variants < 1) {
    warningChecks.push("social_relatability_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.recognition_variants < 1) {
    warningChecks.push("social_recognition_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.conversation_variants < 1) {
    warningChecks.push("social_conversation_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.savvy_variants < 1) {
    warningChecks.push("social_savvy_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.identity_shift_variants < 1) {
    warningChecks.push("social_identity_shift_thin");
  }
  if (targetCount > 0 && selectedSocialSummary.front_loaded_social_variants < Math.max(1, Math.min(targetCount || 1, 2))) {
    warningChecks.push("social_front_load_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.curiosity_variants < 1) {
    warningChecks.push("social_curiosity_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.resolution_variants < 1) {
    warningChecks.push("social_resolution_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.contrast_variants < 1) {
    warningChecks.push("social_contrast_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.pain_point_variants < signalTargets.painPointMin) {
    warningChecks.push("social_pain_points_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.payoff_variants < signalTargets.payoffMin) {
    warningChecks.push("social_payoffs_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.proof_variants < 1) {
    warningChecks.push("social_proof_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.actionable_variants < 1) {
    warningChecks.push("social_actionability_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.immediacy_variants < 1) {
    warningChecks.push("social_immediacy_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.consequence_variants < 1) {
    warningChecks.push("social_consequence_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.habit_shift_variants < 1) {
    warningChecks.push("social_habit_shift_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.focused_variants < 1) {
    warningChecks.push("social_focus_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.promise_sync_variants < 1) {
    warningChecks.push("social_promise_sync_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.scannable_variants < 1) {
    warningChecks.push("social_scannability_thin");
  }
  if (targetCount > 1 && selectedSocialSummary.two_step_variants < 1) {
    warningChecks.push("social_two_step_thin");
  }
  if (!imageReady) {
    warningChecks.push("image_not_ready");
  }

  const penalties = {
    missing_core_fields: 35,
    missing_recipe: 25,
    missing_manual_images: 20,
    duplicate_conflict: 30,
    missing_target_pages: 25,
    thin_content: 15,
    weak_title: 8,
    weak_excerpt: 8,
    weak_seo: 8,
    weak_title_alignment: 7,
    weak_pagination: 8,
    weak_page_balance: 7,
    weak_page_openings: 6,
    weak_page_flow: 6,
    weak_page_labels: 5,
    repetitive_page_labels: 5,
    weak_page_summaries: 5,
    weak_structure: 10,
    missing_internal_links: 9,
    social_pack_incomplete: 12,
    social_pack_repetitive: 10,
    social_hooks_repetitive: 8,
    social_openings_repetitive: 8,
    social_angles_repetitive: 8,
    social_hook_forms_thin: 5,
    weak_social_copy: 10,
    weak_social_lead: 8,
    social_specificity_thin: 8,
    social_anchor_thin: 7,
    social_relatability_thin: 6,
    social_recognition_thin: 6,
    social_conversation_thin: 6,
    social_savvy_thin: 6,
    social_identity_shift_thin: 6,
    social_novelty_thin: 7,
    social_front_load_thin: 7,
    social_curiosity_thin: 6,
    social_resolution_thin: 6,
    social_contrast_thin: 6,
    social_pain_points_thin: 6,
    social_payoffs_thin: 6,
    social_proof_thin: 6,
    social_actionability_thin: 6,
    social_immediacy_thin: 6,
    social_consequence_thin: 6,
    social_habit_shift_thin: 6,
    social_focus_thin: 6,
    social_promise_sync_thin: 6,
    social_scannability_thin: 6,
    social_two_step_thin: 6,
    image_not_ready: 8,
    package_contract_drift: 6,
    facebook_adapter_contract_drift: 5,
    facebook_groups_adapter_contract_drift: 3,
    pinterest_adapter_contract_drift: 3,
  };
  let qualityScore = 100;
  for (const failedCheck of [...blockingChecks, ...warningChecks]) {
    qualityScore -= Number(penalties[failedCheck] || 0);
  }
  qualityScore = Math.max(0, qualityScore);
  const dedupedBlockingChecks = Array.from(new Set(blockingChecks));
  const dedupedWarningChecks = Array.from(new Set(warningChecks));
  const failedChecks = [...dedupedBlockingChecks, ...dedupedWarningChecks];
  const qualityScoreThreshold = Number(settings?.quality_score_threshold || 75);
  const qualityStatus = dedupedBlockingChecks.length > 0
    ? "block"
    : ((dedupedWarningChecks.length > 0 || qualityScore < qualityScoreThreshold) ? "warn" : "pass");
  const editorialSummary = buildEditorialReadinessSummary({
    qualityStatus,
    qualityScore,
    titleStrong,
    openingAlignmentScore,
    pageCount,
    strongPageOpenings,
    strongPageSummaries,
    targetPages: targetCount,
    strongSocialVariants,
    leadSocialScore: selectedSocialSummary.lead_social_score,
    leadSocialSpecific: selectedSocialSummary.lead_social_specific,
    leadSocialFrontLoaded: selectedSocialSummary.lead_social_front_loaded,
    leadSocialPromiseSync: selectedSocialSummary.lead_social_promise_sync,
    blockingChecks: dedupedBlockingChecks,
    warningChecks: dedupedWarningChecks,
  });

  return {
    quality_score: qualityScore,
    quality_status: qualityStatus,
    blocking_checks: dedupedBlockingChecks,
    warning_checks: dedupedWarningChecks,
    failed_checks: failedChecks,
    package_quality: buildContentPackageQualitySummary({
      generated: {
        ...generated,
        content_type: contentType,
        content_package: contentPackage,
      },
      warningChecks: dedupedWarningChecks,
      blockingChecks: dedupedBlockingChecks,
      deps,
    }),
    channel_quality: {
      facebook: buildFacebookChannelQualitySummary({
        generated: {
          ...generated,
          content_type: contentType,
          social_pack: socialPack,
        },
        warningChecks: dedupedWarningChecks,
        blockingChecks: dedupedBlockingChecks,
        deps,
      }),
      facebook_groups: buildDormantChannelQualitySummary({
        generated,
        channel: "facebook_groups",
        warningChecks: dedupedWarningChecks,
        blockingChecks: dedupedBlockingChecks,
        deps,
      }),
      pinterest: buildDormantChannelQualitySummary({
        generated,
        channel: "pinterest",
        warningChecks: dedupedWarningChecks,
        blockingChecks: dedupedBlockingChecks,
        deps,
      }),
    },
    editorial_readiness: editorialSummary.editorial_readiness,
    editorial_highlights: editorialSummary.editorial_highlights,
    editorial_watchouts: editorialSummary.editorial_watchouts,
    quality_checks: {
      word_count: wordCount,
      minimum_words: minimumWords,
      title_score: titleScore,
      title_strong: titleStrong,
      title_front_load_score: titleFrontLoadScore,
      opening_alignment_score: openingAlignmentScore,
      excerpt_adds_value: excerptAddsValue,
      opening_adds_value: openingAddsValue,
      opening_front_load_score: openingFrontLoadScore,
      h2_count: h2Count,
      internal_links: internalLinks,
      internal_link_minimum: internalLinkMinimum,
      excerpt_words: excerptWords,
      excerpt_signal_score: excerptSignalScore,
      excerpt_front_load_score: excerptFrontLoadScore,
      seo_words: seoWords,
      seo_signal_score: seoSignalScore,
      seo_front_load_score: seoFrontLoadScore,
      page_count: pageCount,
      shortest_page_words: shortestPageWords,
      strong_page_openings: strongPageOpenings,
      unique_page_labels: uniquePageLabels.size,
      strong_page_labels: strongPageLabels,
      strong_page_summaries: strongPageSummaries,
      recipe_complete: recipeComplete,
      image_ready: imageReady,
      package_contract_enforced: contractChecks.package_contract_enforced,
      channel_contract_enforced: contractChecks.channel_contract_enforced,
      typed_contract_job: contractMeta.typed_contract_job,
      legacy_contract_job: contractMeta.legacy_job,
      strict_contract_mode: strictContractMode,
      target_pages: targetCount,
      social_variants: socialPack.length,
      unique_social_variants: uniqueFingerprints.size,
      unique_social_hooks: uniqueHookFingerprints.size,
      unique_social_openings: uniqueCaptionOpenings.size,
      unique_social_angles: uniqueAngleKeys.size,
      unique_hook_form_candidates: deps.toInt(validatorSummary.unique_hook_form_candidates),
      unique_social_hook_forms: uniqueHookForms.size,
      social_pool_size: deps.toInt(validatorSummary.social_pool_size),
      strong_social_candidates: deps.toInt(validatorSummary.strong_social_candidates),
      specific_social_candidates: deps.toInt(validatorSummary.specific_social_candidates),
      anchored_social_candidates: deps.toInt(validatorSummary.anchored_social_candidates),
      novelty_social_candidates: deps.toInt(validatorSummary.novelty_social_candidates),
      relatable_social_candidates: deps.toInt(validatorSummary.relatable_social_candidates),
      recognition_social_candidates: deps.toInt(validatorSummary.recognition_social_candidates),
      conversation_social_candidates: deps.toInt(validatorSummary.conversation_social_candidates),
      savvy_social_candidates: deps.toInt(validatorSummary.savvy_social_candidates),
      identity_shift_social_candidates: deps.toInt(validatorSummary.identity_shift_social_candidates),
      proof_social_candidates: deps.toInt(validatorSummary.proof_social_candidates),
      actionable_social_candidates: deps.toInt(validatorSummary.actionable_social_candidates),
      immediacy_social_candidates: deps.toInt(validatorSummary.immediacy_social_candidates),
      consequence_social_candidates: deps.toInt(validatorSummary.consequence_social_candidates),
      habit_shift_social_candidates: deps.toInt(validatorSummary.habit_shift_social_candidates),
      focused_social_candidates: deps.toInt(validatorSummary.focused_social_candidates),
      promise_sync_candidates: deps.toInt(validatorSummary.promise_sync_candidates),
      scannable_social_candidates: deps.toInt(validatorSummary.scannable_social_candidates),
      two_step_social_candidates: deps.toInt(validatorSummary.two_step_social_candidates),
      front_loaded_social_candidates: deps.toInt(validatorSummary.front_loaded_social_candidates),
      curiosity_social_candidates: deps.toInt(validatorSummary.curiosity_social_candidates),
      resolution_social_candidates: deps.toInt(validatorSummary.resolution_social_candidates),
      contrast_social_candidates: deps.toInt(validatorSummary.contrast_social_candidates),
      pain_point_social_candidates: deps.toInt(validatorSummary.pain_point_social_candidates),
      payoff_social_candidates: deps.toInt(validatorSummary.payoff_social_candidates),
      high_scoring_social_candidates: deps.toInt(validatorSummary.high_scoring_social_candidates),
      strong_social_variants: strongSocialVariants,
      specific_social_variants: selectedSocialSummary.specific_social_variants,
      anchored_variants: selectedSocialSummary.anchored_variants,
      novelty_variants: selectedSocialSummary.novelty_variants,
      relatable_variants: selectedSocialSummary.relatable_variants,
      recognition_variants: selectedSocialSummary.recognition_variants,
      conversation_variants: selectedSocialSummary.conversation_variants,
      savvy_variants: selectedSocialSummary.savvy_variants,
      identity_shift_variants: selectedSocialSummary.identity_shift_variants,
      proof_variants: selectedSocialSummary.proof_variants,
      actionable_variants: selectedSocialSummary.actionable_variants,
      immediacy_variants: selectedSocialSummary.immediacy_variants,
      consequence_variants: selectedSocialSummary.consequence_variants,
      habit_shift_variants: selectedSocialSummary.habit_shift_variants,
      focused_variants: selectedSocialSummary.focused_variants,
      promise_sync_variants: selectedSocialSummary.promise_sync_variants,
      scannable_variants: selectedSocialSummary.scannable_variants,
      two_step_variants: selectedSocialSummary.two_step_variants,
      curiosity_variants: selectedSocialSummary.curiosity_variants,
      resolution_variants: selectedSocialSummary.resolution_variants,
      contrast_variants: selectedSocialSummary.contrast_variants,
      front_loaded_social_variants: selectedSocialSummary.front_loaded_social_variants,
      pain_point_variants: selectedSocialSummary.pain_point_variants,
      payoff_variants: selectedSocialSummary.payoff_variants,
      selected_social_average_score: selectedSocialSummary.selected_social_average_score,
      lead_social_score: selectedSocialSummary.lead_social_score,
      lead_social_hook_form: selectedSocialSummary.lead_social_hook_form,
      lead_social_specific: selectedSocialSummary.lead_social_specific,
      lead_social_anchored: selectedSocialSummary.lead_social_anchored,
      lead_social_relatable: selectedSocialSummary.lead_social_relatable,
      lead_social_recognition: selectedSocialSummary.lead_social_recognition,
      lead_social_conversation: selectedSocialSummary.lead_social_conversation,
      lead_social_savvy: selectedSocialSummary.lead_social_savvy,
      lead_social_identity_shift: selectedSocialSummary.lead_social_identity_shift,
      lead_social_novelty: selectedSocialSummary.lead_social_novelty,
      lead_social_curiosity: selectedSocialSummary.lead_social_curiosity,
      lead_social_resolved: selectedSocialSummary.lead_social_resolved,
      lead_social_contrast: selectedSocialSummary.lead_social_contrast,
      lead_social_front_loaded: selectedSocialSummary.lead_social_front_loaded,
      lead_social_pain_point: selectedSocialSummary.lead_social_pain_point,
      lead_social_payoff: selectedSocialSummary.lead_social_payoff,
      lead_social_proof: selectedSocialSummary.lead_social_proof,
      lead_social_actionable: selectedSocialSummary.lead_social_actionable,
      lead_social_immediacy: selectedSocialSummary.lead_social_immediacy,
      lead_social_consequence: selectedSocialSummary.lead_social_consequence,
      lead_social_habit_shift: selectedSocialSummary.lead_social_habit_shift,
      lead_social_focused: selectedSocialSummary.lead_social_focused,
      lead_social_promise_sync: selectedSocialSummary.lead_social_promise_sync,
      lead_social_scannable: selectedSocialSummary.lead_social_scannable,
      lead_social_two_step: selectedSocialSummary.lead_social_two_step,
      duplicate_risk: duplicateRisk,
    },
  };
}

export function buildContentPackageQualitySummary({
  generated,
  warningChecks = null,
  blockingChecks = null,
  deps,
}) {
  const validatorSummary = extractValidatorSummary(generated, deps);
  const stageChecks = Array.isArray(validatorSummary.article_stage_checks) ? validatorSummary.article_stage_checks : [];
  const contractSignals = collectCanonicalContractChecks({ generated, deps });
  const contractVersions = extractGeneratedContractVersions(generated, deps);
  const resolvedWarningChecks = Array.isArray(warningChecks) ? warningChecks : (Array.isArray(validatorSummary.warning_checks) ? validatorSummary.warning_checks : []);
  const resolvedBlockingChecks = Array.isArray(blockingChecks) ? blockingChecks : (Array.isArray(validatorSummary.blocking_checks) ? validatorSummary.blocking_checks : []);

  return {
    layer: "article",
    contract_version: deps.cleanText(validatorSummary.content_package_contract_version || contractVersions.content_package || ""),
    contract_enforced: contractSignals.package_contract_enforced,
    contract_warning: contractSignals.warning_checks.includes("package_contract_drift"),
    stage_status: deps.cleanText(validatorSummary.article_stage_quality_status || ""),
    stage_checks: stageChecks,
    title_score: deps.toInt(validatorSummary.article_title_score),
    title_front_load_score: deps.toInt(validatorSummary.article_title_front_load_score),
    opening_alignment_score: deps.toInt(validatorSummary.article_opening_alignment_score),
    opening_front_load_score: deps.toInt(validatorSummary.article_opening_front_load_score),
    excerpt_signal_score: deps.toInt(validatorSummary.article_excerpt_signal_score),
    excerpt_front_load_score: deps.toInt(validatorSummary.article_excerpt_front_load_score),
    seo_signal_score: deps.toInt(validatorSummary.article_seo_signal_score),
    seo_front_load_score: deps.toInt(validatorSummary.article_seo_front_load_score),
    excerpt_adds_value: Boolean(validatorSummary.article_excerpt_adds_value),
    opening_adds_value: Boolean(validatorSummary.article_opening_adds_value),
    editorial_readiness: deps.cleanText(validatorSummary.editorial_readiness || ""),
    warning_checks: resolvedWarningChecks.filter((check) => !/^social_/.test(String(check || "")) && !/_adapter_contract_drift$/.test(check)),
    blocking_checks: resolvedBlockingChecks.filter((check) => check === "package_contract_drift"),
  };
}

export function buildFacebookChannelQualitySummary({
  generated,
  warningChecks = null,
  blockingChecks = null,
  deps,
}) {
  const validatorSummary = extractValidatorSummary(generated, deps);
  const contractSignals = collectCanonicalContractChecks({ generated, deps });
  const contractVersions = extractGeneratedContractVersions(generated, deps);
  const resolvedWarningChecks = Array.isArray(warningChecks) ? warningChecks : (Array.isArray(validatorSummary.warning_checks) ? validatorSummary.warning_checks : []);
  const resolvedBlockingChecks = Array.isArray(blockingChecks) ? blockingChecks : (Array.isArray(validatorSummary.blocking_checks) ? validatorSummary.blocking_checks : []);

  return {
    layer: "facebook",
    contract_version: deps.cleanText(validatorSummary.channel_adapter_contract_version || contractVersions.channel_adapters || ""),
    contract_enforced: contractSignals.channel_contract_enforced,
    contract_warning: contractSignals.warning_checks.includes("facebook_adapter_contract_drift"),
    pool_quality_status: deps.cleanText(validatorSummary.social_pool_quality_status || ""),
    distribution_source: deps.cleanText(validatorSummary.distribution_source || ""),
    quality_status: deps.cleanText(validatorSummary.quality_status || ""),
    quality_score: Number(validatorSummary.quality_score || 0),
    target_pages: deps.toInt(validatorSummary.target_pages),
    social_variants: deps.toInt(validatorSummary.social_variants),
    social_pool_size: deps.toInt(validatorSummary.social_pool_size),
    strong_candidates: deps.toInt(validatorSummary.strong_social_candidates),
    specific_candidates: deps.toInt(validatorSummary.specific_social_candidates),
    unique_hooks: deps.toInt(validatorSummary.unique_social_hooks),
    unique_openings: deps.toInt(validatorSummary.unique_social_openings),
    unique_angles: deps.toInt(validatorSummary.unique_social_angles),
    strong_variants: deps.toInt(validatorSummary.strong_social_variants),
    selected_average_score: Number(validatorSummary.selected_social_average_score || 0),
    lead_score: deps.toInt(validatorSummary.lead_social_score),
    lead_specific: Boolean(validatorSummary.lead_social_specific),
    lead_front_loaded: Boolean(validatorSummary.lead_social_front_loaded),
    lead_pain_point: Boolean(validatorSummary.lead_social_pain_point),
    lead_payoff: Boolean(validatorSummary.lead_social_payoff),
    warning_checks: filterChannelWarningChecks(resolvedWarningChecks, "facebook"),
    blocking_checks: filterChannelWarningChecks(resolvedBlockingChecks, "facebook"),
  };
}

export function buildDormantChannelQualitySummary({
  generated,
  channel,
  warningChecks = null,
  blockingChecks = null,
  deps,
}) {
  const validatorSummary = extractValidatorSummary(generated, deps);
  const contractSignals = collectCanonicalContractChecks({ generated, deps });
  const contractVersions = extractGeneratedContractVersions(generated, deps);
  const resolvedWarningChecks = Array.isArray(warningChecks) ? warningChecks : (Array.isArray(validatorSummary.warning_checks) ? validatorSummary.warning_checks : []);
  const resolvedBlockingChecks = Array.isArray(blockingChecks) ? blockingChecks : (Array.isArray(validatorSummary.blocking_checks) ? validatorSummary.blocking_checks : []);
  const warningKey = channel === "facebook_groups"
    ? "facebook_groups_adapter_contract_drift"
    : "pinterest_adapter_contract_drift";

  return {
    layer: channel,
    contract_version: contractVersions.channel_adapters,
    contract_enforced: contractSignals.channel_contract_enforced,
    contract_warning: contractSignals.warning_checks.includes(warningKey),
    warning_checks: filterChannelWarningChecks(resolvedWarningChecks, channel),
    blocking_checks: filterChannelWarningChecks(resolvedBlockingChecks, channel),
  };
}
