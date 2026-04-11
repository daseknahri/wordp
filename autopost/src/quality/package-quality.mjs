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
