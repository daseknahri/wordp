export function createQualityEntryPoints(deps) {
  const {
    runBuildContentPackageQualitySummary,
    runBuildDormantChannelQualitySummary,
    runBuildEditorialReadinessSummary,
    runBuildFacebookChannelQualitySummary,
    runBuildQualitySummary,
    runBuildFallbackSocialPack,
    runCollectCanonicalContractChecks,
    runDesiredSocialSignalTargets,
    runEnsureSocialPackCoverage,
    runExtractGeneratedContractVersions,
    runExtractValidatorSummary,
    runFilterChannelWarningChecks,
    runFindBestSocialCandidateIndex,
    runIsCanonicalContractVersion,
    runQualityCheckReasonMessage,
    runSummarizeArticleStage,
    runSummarizeSelectedSocialPack,
    runSummarizeSocialCandidatePool,
    buildQualityModuleDeps,
    buildFacebookQualityModuleDeps,
    buildArticleQualityModuleDeps,
  } = deps;

  const qualityDeps = buildQualityModuleDeps();
  const facebookDeps = buildFacebookQualityModuleDeps();
  const articleDeps = buildArticleQualityModuleDeps();

  function summarizeArticleStage(generated, job) {
    return runSummarizeArticleStage({
      generated,
      job,
      deps: articleDeps,
    });
  }

  function summarizeSocialCandidatePool(candidates, article, desiredCount, contentType = "recipe") {
    return runSummarizeSocialCandidatePool({
      candidates,
      article,
      desiredCount,
      contentType,
      deps: facebookDeps,
    });
  }

  function summarizeSelectedSocialPack(socialPack, article, contentType = "recipe") {
    return runSummarizeSelectedSocialPack({
      socialPack,
      article,
      contentType,
      deps: facebookDeps,
    });
  }

  function desiredSocialSignalTargets(totalCount) {
    return runDesiredSocialSignalTargets(totalCount);
  }

  function findBestSocialCandidateIndex(candidates, article, contentType, desiredAngle, usedFingerprints, usedHookFingerprints, usedCaptionOpenings, selectionState = null) {
    return runFindBestSocialCandidateIndex({
      candidates,
      article,
      contentType,
      desiredAngle,
      usedFingerprints,
      usedHookFingerprints,
      usedCaptionOpenings,
      selectionState,
      deps: facebookDeps,
    });
  }

  function buildFallbackSocialPack(article, pages, settings, contentType = "recipe", preferredAngle = "") {
    return runBuildFallbackSocialPack({
      article,
      pages,
      settings,
      contentType,
      preferredAngle,
      deps: facebookDeps,
    });
  }

  function ensureSocialPackCoverage(value, pages, article, settings, contentType = "recipe", preferredAngle = "") {
    return runEnsureSocialPackCoverage({
      value,
      pages,
      article,
      settings,
      contentType,
      preferredAngle,
      deps: facebookDeps,
    });
  }

  function buildQualitySummary(job, generated, settings, options = {}) {
    return runBuildQualitySummary({
      job,
      generated,
      settings,
      options,
      deps: qualityDeps,
    });
  }

  function qualityCheckReasonMessage(check) {
    return runQualityCheckReasonMessage(check);
  }

  function buildEditorialReadinessSummary(input = {}) {
    return runBuildEditorialReadinessSummary(input);
  }

  function extractValidatorSummary(generated) {
    return runExtractValidatorSummary(generated, qualityDeps);
  }

  function extractGeneratedContractVersions(generated) {
    return runExtractGeneratedContractVersions(generated, qualityDeps);
  }

  function isCanonicalContractVersion(value, prefix) {
    return runIsCanonicalContractVersion(value, prefix);
  }

  function collectCanonicalContractChecks(generated, job = null, targetPages = 0) {
    return runCollectCanonicalContractChecks({
      generated,
      job,
      targetPages,
      deps: qualityDeps,
    });
  }

  function filterChannelWarningChecks(checks, channel = "facebook") {
    return runFilterChannelWarningChecks(checks, channel);
  }

  function buildContentPackageQualitySummary(generated) {
    return runBuildContentPackageQualitySummary({
      generated,
      deps: qualityDeps,
    });
  }

  function buildFacebookChannelQualitySummary(generated) {
    return runBuildFacebookChannelQualitySummary({
      generated,
      deps: qualityDeps,
    });
  }

  function buildDormantChannelQualitySummary(generated, channel, warningChecks = null, blockingChecks = null) {
    return runBuildDormantChannelQualitySummary({
      generated,
      channel,
      warningChecks,
      blockingChecks,
      deps: qualityDeps,
    });
  }

  return {
    buildContentPackageQualitySummary,
    buildDormantChannelQualitySummary,
    buildEditorialReadinessSummary,
    buildFallbackSocialPack,
    buildFacebookChannelQualitySummary,
    buildQualitySummary,
    collectCanonicalContractChecks,
    desiredSocialSignalTargets,
    ensureSocialPackCoverage,
    extractGeneratedContractVersions,
    extractValidatorSummary,
    filterChannelWarningChecks,
    findBestSocialCandidateIndex,
    isCanonicalContractVersion,
    qualityCheckReasonMessage,
    summarizeArticleStage,
    summarizeSelectedSocialPack,
    summarizeSocialCandidatePool,
  };
}
