import { createFacebookPhaseHelpers } from "./facebook-phase.mjs";
import { createJobOrchestrator } from "./orchestrator.mjs";
import { createPackageGenerator } from "./package-generator.mjs";
import { createPostingExecutors } from "./posting-executors.mjs";
import { createPostingMachine } from "./posting-machine.mjs";
import { createPostingTargetResolver } from "./posting-targets.mjs";
import { createWordPressJobClient } from "./wordpress-jobs.mjs";

export function createJobsHelpers(deps) {
  const {
    WORKER_VERSION,
    config,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    formatError,
    log,
    resolveCanonicalContentPackage,
    resolveFacebookDefaultCtas,
    resolveFacebookChannelAdapter,
    toInt,
    wpRequest,
    buildChannelProfile,
    buildFacebookGroupsDraft,
    buildPinterestDraft,
    CHANNEL_ADAPTER_CONTRACT_VERSION,
    CONTENT_PACKAGE_CONTRACT_VERSION,
    PROMPT_VERSION,
    cleanText,
    ensureSocialPackCoverage,
    generateCoreArticlePackage,
    generateSocialCandidatePool,
    isPlainObject,
    normalizeFacebookDistribution,
    normalizeGeneratedPayload,
    resolveFacebookDistributionContext,
    resolveFacebookDistributionResult,
    resolveFacebookTargets,
    resolvePreferredAngle,
    resolvePublicationProfile,
    resolveTypedGuidance,
    summarizeSelectedSocialPack,
    syncGeneratedContractContainers,
    assertFacebookConfigured,
    assertQualityGate,
    assertRecipeDistributionTargets,
    buildQualitySummary,
    ensureJobImages,
    ensureOpenAiConfigured,
    firstAttachment,
    hydrateStoredGeneratedPayload,
    isFutureUtcTimestamp,
    mergeSettings,
    mergeValidatorSummary,
    publishFacebookDistribution,
  } = deps;

  const facebookPhaseHelpers = createFacebookPhaseHelpers({
    assertRecipeDistributionTargets,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    resolveFacebookDistributionContext,
    resolveFacebookChannelAdapter,
    resolvePreferredAngle,
  });

  const wordPressJobClient = createWordPressJobClient({
    WORKER_VERSION,
    config,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    formatError,
    log,
    resolveCanonicalContentPackage,
    resolveFacebookDefaultCtas,
    resolveFacebookChannelAdapter,
    toInt,
    wpRequest,
  });

  const packageGenerator = createPackageGenerator({
    buildChannelProfile,
    buildFacebookGroupsDraft,
    buildPinterestDraft,
    CHANNEL_ADAPTER_CONTRACT_VERSION,
    CONTENT_PACKAGE_CONTRACT_VERSION,
    PROMPT_VERSION,
    cleanText,
    ensureSocialPackCoverage,
    formatError,
    generateCoreArticlePackage,
    generateSocialCandidatePool,
    isPlainObject,
    log,
    normalizeFacebookDistribution,
    normalizeGeneratedPayload,
    resolveCanonicalContentPackage,
    resolveFacebookTargets,
    resolvePreferredAngle,
    resolvePublicationProfile,
    resolveTypedGuidance,
    summarizeSelectedSocialPack,
    syncGeneratedContractContainers,
  });

  const postingTargetResolver = createPostingTargetResolver({
    resolveFacebookTargets,
  });

  const postingMachine = createPostingMachine({
    completeJob: (...args) => wordPressJobClient.completeJob(...args),
    executors: createPostingExecutors({
      assertFacebookConfigured,
      log,
      publishBlogPost: (...args) => wordPressJobClient.publishBlogPost(...args),
      publishFacebookDistribution,
      resolveFacebookDistributionContext,
      resolveFacebookDistributionResult,
      toInt,
    }),
    formatError,
    log,
    resolvePostingTargetCounts: (...args) => postingTargetResolver.resolvePostingTargetCounts(...args),
    safeFailJob: (...args) => wordPressJobClient.safeFailJob(...args),
    updateJobProgress: (...args) => wordPressJobClient.updateJobProgress(...args),
  });

  const jobOrchestrator = createJobOrchestrator({
    assertQualityGate,
    buildQualitySummary,
    claimNextJob: (...args) => wordPressJobClient.claimNextJob(...args),
    ensureJobImages,
    ensureOpenAiConfigured,
    ensureSocialPackCoverage,
    firstAttachment,
    formatError,
    generatePackage: (...args) => packageGenerator.generatePackage(...args),
    hydrateStoredGeneratedPayload,
    idleHeartbeatState: (...args) => wordPressJobClient.idleHeartbeatState(...args),
    isFutureUtcTimestamp,
    log,
    mergeSettings,
    mergeValidatorSummary,
    normalizeGeneratedPayload,
    postingMachine,
    refreshFacebookPhaseState: (...args) => facebookPhaseHelpers.refreshFacebookPhaseState(...args),
    resolveCanonicalContentPackage,
    safeFailJob: (...args) => wordPressJobClient.safeFailJob(...args),
    toInt,
    updateJobProgress: (...args) => wordPressJobClient.updateJobProgress(...args),
  });

  return {
    jobOrchestrator,
    packageGenerator,
    postingMachine,
    wordPressJobClient,
  };
}
