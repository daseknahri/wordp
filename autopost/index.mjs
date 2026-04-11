import { setTimeout as sleep } from "node:timers/promises";
import {
  CHANNEL_ADAPTER_CONTRACT_VERSION,
  config,
  CONTENT_PACKAGE_CONTRACT_VERSION,
  IDLE_MS,
  PROMPT_VERSION,
  SECOND_MS,
  WORKER_VERSION,
} from "./src/runtime/config.mjs";
import { formPostJson } from "./src/clients/facebook.mjs";
import { generateSocialCandidatePool as runGenerateSocialCandidatePool } from "./src/channels/facebook/social-stage.mjs";
import {
  angleDefinition,
  angleDefinitionsForType,
  buildAngleSequence,
  buildPageAnglePlan,
  normalizeAngleKey,
  resolvePreferredAngle,
} from "./src/channels/facebook/angles.mjs";
import {
  buildFallbackSocialPack as runBuildFallbackSocialPack,
  desiredSocialSignalTargets as runDesiredSocialSignalTargets,
  ensureSocialPackCoverage as runEnsureSocialPackCoverage,
  findBestSocialCandidateIndex as runFindBestSocialCandidateIndex,
  summarizeSelectedSocialPack as runSummarizeSelectedSocialPack,
  summarizeSocialCandidatePool as runSummarizeSocialCandidatePool,
} from "./src/channels/facebook/quality.mjs";
import { createFacebookHelpers } from "./src/channels/facebook/helpers.mjs";
import { createSocialVariantHelpers } from "./src/channels/facebook/social-variants.mjs";
import {
  buildContentPackageQualitySummary as runBuildContentPackageQualitySummary,
  buildDormantChannelQualitySummary as runBuildDormantChannelQualitySummary,
  buildEditorialReadinessSummary as runBuildEditorialReadinessSummary,
  buildFacebookChannelQualitySummary as runBuildFacebookChannelQualitySummary,
  buildQualitySummary as runBuildQualitySummary,
  collectCanonicalContractChecks as runCollectCanonicalContractChecks,
  extractGeneratedContractVersions as runExtractGeneratedContractVersions,
  extractValidatorSummary as runExtractValidatorSummary,
  filterChannelWarningChecks as runFilterChannelWarningChecks,
  isCanonicalContractVersion as runIsCanonicalContractVersion,
  qualityCheckReasonMessage as runQualityCheckReasonMessage,
} from "./src/quality/package-quality.mjs";
import {
  ensureOpenAiConfigured,
  generateImageBase64,
  parseJsonObject,
  requestOpenAiChat,
} from "./src/clients/openai.mjs";
import { wpRequest } from "./src/clients/wordpress.mjs";
import { generateCoreArticlePackage as runGenerateCoreArticlePackage } from "./src/content/article-stage.mjs";
import { summarizeArticleStage as runSummarizeArticleStage } from "./src/content/article-quality.mjs";
import { extractOpeningParagraphText } from "./src/content/article-text.mjs";
import { createContentHelpers } from "./src/content/helpers.mjs";
import { resolvePublicationProfile } from "./src/content/prompts.mjs";
import { createJobsHelpers } from "./src/jobs/helpers.mjs";
import {
  countInternalLinks,
  internalLinkTargetsForJob,
} from "./src/content/internal-links.mjs";
import {
  buildChannelProfile,
  buildContentTypeProfile,
} from "./src/contracts/profiles.mjs";
import { createContractsHelpers } from "./src/contracts/helpers.mjs";
import {
  formatError,
  log,
  toInt,
  trimTrailingSlash,
} from "./src/runtime/utils.mjs";
import { createSettingsHelpers } from "./src/runtime/settings.mjs";
import { createRuntimeNormalizers } from "./src/runtime/normalizers.mjs";
import { createTextHelpers } from "./src/runtime/text.mjs";
import { createListHelpers } from "./src/runtime/lists.mjs";
import { createHtmlHelpers } from "./src/runtime/html.mjs";
import { createJobUtils } from "./src/runtime/job-utils.mjs";
import { createTimeHelpers } from "./src/runtime/time.mjs";
import { createRuntimeCore } from "./src/runtime/core.mjs";
import { createWorkerRunner } from "./src/runtime/worker-runner.mjs";

const textHelpers = createTextHelpers();
const cleanMultilineText = (...args) => textHelpers.cleanMultilineText(...args);
const cleanText = (...args) => textHelpers.cleanText(...args);
const countLines = (...args) => textHelpers.countLines(...args);
const countWords = (...args) => textHelpers.countWords(...args);
const escapeHtml = (...args) => textHelpers.escapeHtml(...args);
const firstSentence = (...args) => textHelpers.firstSentence(...args);
const normalizeSlug = (...args) => textHelpers.normalizeSlug(...args);
const sentenceCase = (...args) => textHelpers.sentenceCase(...args);
const trimText = (...args) => textHelpers.trimText(...args);
const trimWords = (...args) => textHelpers.trimWords(...args);
const normalizeSocialLineFingerprint = (value) => normalizeSlug(cleanText(value || ""));

const htmlHelpers = createHtmlHelpers({
  cleanText,
  escapeHtml,
});
const ensureStringArray = (...args) => htmlHelpers.ensureStringArray(...args);
const normalizeHtml = (...args) => htmlHelpers.normalizeHtml(...args);

const listHelpers = createListHelpers({
  cleanMultilineText,
  cleanText,
  ensureStringArray,
  normalizeSlug,
});
const buildFallbackCaption = (...args) => listHelpers.buildFallbackCaption(...args);
const joinNaturalList = (...args) => listHelpers.joinNaturalList(...args);
const sharedWordsRatio = (...args) => listHelpers.sharedWordsRatio(...args);

const runtimeCore = createRuntimeCore({ config, sleep, IDLE_MS });
const idleLoop = (...args) => runtimeCore.idleLoop(...args);
const hasWorkerConfig = (...args) => runtimeCore.hasWorkerConfig(...args);
const isPlainObject = (...args) => runtimeCore.isPlainObject(...args);

const runtimeNormalizers = createRuntimeNormalizers({
  cleanMultilineText,
  cleanText,
  isPlainObject,
});
const normalizeFacebookPages = (...args) => runtimeNormalizers.normalizeFacebookPages(...args);
const normalizePreset = (...args) => runtimeNormalizers.normalizePreset(...args);
const resolveTypedGuidance = (...args) => runtimeNormalizers.resolveTypedGuidance(...args);

const settingsHelpers = createSettingsHelpers({
  cleanMultilineText,
  cleanText,
  config,
  isPlainObject,
  normalizeFacebookPages,
  normalizePreset,
  resolveTypedGuidance,
  trimTrailingSlash,
  PROMPT_VERSION,
});
const mergeSettings = (...args) => settingsHelpers.mergeSettings(...args);

let contentHelpers;

const socialVariantHelpers = createSocialVariantHelpers({
  cleanText,
  cleanMultilineText,
  countLines,
  countWords,
  frontLoadedClickSignalScore: (...args) => contentHelpers.contentQualityUtils.frontLoadedClickSignalScore(...args),
  normalizeAngleKey,
  normalizeSlug,
  normalizeSocialLineFingerprint,
  sharedWordsRatio,
  trimText,
});
const classifySocialHookForm = (...args) => socialVariantHelpers.classifySocialHookForm(...args);
const scoreSocialVariant = (...args) => socialVariantHelpers.scoreSocialVariant(...args);
const socialVariantActionabilitySignal = (...args) => socialVariantHelpers.socialVariantActionabilitySignal(...args);
const socialVariantAnchorSignal = (...args) => socialVariantHelpers.socialVariantAnchorSignal(...args);
const socialVariantConsequenceSignal = (...args) => socialVariantHelpers.socialVariantConsequenceSignal(...args);
const socialVariantContrastSignal = (...args) => socialVariantHelpers.socialVariantContrastSignal(...args);
const socialVariantConversationSignal = (...args) => socialVariantHelpers.socialVariantConversationSignal(...args);
const socialVariantCuriositySignal = (...args) => socialVariantHelpers.socialVariantCuriositySignal(...args);
const socialVariantHabitShiftSignal = (...args) => socialVariantHelpers.socialVariantHabitShiftSignal(...args);
const socialVariantIdentityShiftSignal = (...args) => socialVariantHelpers.socialVariantIdentityShiftSignal(...args);
const socialVariantImmediacySignal = (...args) => socialVariantHelpers.socialVariantImmediacySignal(...args);
const socialVariantLooksWeak = (...args) => socialVariantHelpers.socialVariantLooksWeak(...args);
const socialVariantNoveltyScore = (...args) => socialVariantHelpers.socialVariantNoveltyScore(...args);
const socialVariantPainPointSignal = (...args) => socialVariantHelpers.socialVariantPainPointSignal(...args);
const socialVariantPayoffSignal = (...args) => socialVariantHelpers.socialVariantPayoffSignal(...args);
const socialVariantPromiseFocusSignal = (...args) => socialVariantHelpers.socialVariantPromiseFocusSignal(...args);
const socialVariantPromiseSyncSignal = (...args) => socialVariantHelpers.socialVariantPromiseSyncSignal(...args);
const socialVariantProofSignal = (...args) => socialVariantHelpers.socialVariantProofSignal(...args);
const socialVariantRelatabilitySignal = (...args) => socialVariantHelpers.socialVariantRelatabilitySignal(...args);
const socialVariantResolvesEarly = (...args) => socialVariantHelpers.socialVariantResolvesEarly(...args);
const socialVariantSavvySignal = (...args) => socialVariantHelpers.socialVariantSavvySignal(...args);
const socialVariantScannabilitySignal = (...args) => socialVariantHelpers.socialVariantScannabilitySignal(...args);
const socialVariantSelfRecognitionSignal = (...args) => socialVariantHelpers.socialVariantSelfRecognitionSignal(...args);
const socialVariantSpecificityScore = (...args) => socialVariantHelpers.socialVariantSpecificityScore(...args);
const socialVariantTwoStepSignal = (...args) => socialVariantHelpers.socialVariantTwoStepSignal(...args);
const stripHookEchoFromCaption = (...args) => socialVariantHelpers.stripHookEchoFromCaption(...args);

let generatedPayloadHelpers;
let channelAdapterHelpers;
let channelDraftHelpers;
let facebookHelpers;
let imageAssetHelpers;
let generationRunner;
let jobUtils;

const buildFacebookComment = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.buildFacebookComment(...args);
};
const buildFacebookCommentUrl = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.buildFacebookCommentUrl(...args);
};
const buildFacebookPostMessage = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.buildFacebookPostMessage(...args);
};
const buildFacebookPostUrl = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.buildFacebookPostUrl(...args);
};
const buildFallbackFacebookCaption = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.buildFallbackFacebookCaption(...args);
};
const normalizeFacebookDistribution = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.normalizeFacebookDistribution(...args);
};
const normalizeSocialPack = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.normalizeSocialPack(...args);
};
const buildFacebookGroupsDraft = (...args) => {
  if (!channelDraftHelpers) {
    throw new Error("Channel draft helpers are not ready.");
  }
  return channelDraftHelpers.buildFacebookGroupsDraft(...args);
};
const buildPinterestDraft = (...args) => {
  if (!channelDraftHelpers) {
    throw new Error("Channel draft helpers are not ready.");
  }
  return channelDraftHelpers.buildPinterestDraft(...args);
};
const resolveSelectedFacebookPages = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.resolveSelectedFacebookPages(...args);
};
const seedLegacyFacebookDistribution = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.seedLegacyFacebookDistribution(...args);
};
const publishFacebookDistribution = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.publishFacebookDistribution(...args);
};
function hydrateStoredGeneratedPayload(...args) {
  if (!generatedPayloadHelpers) {
    throw new Error("Generated payload helpers are not ready.");
  }
  return generatedPayloadHelpers.hydrateStoredGeneratedPayload(...args);
}
function normalizeGeneratedPayload(...args) {
  if (!generatedPayloadHelpers) {
    throw new Error("Generated payload helpers are not ready.");
  }
  return generatedPayloadHelpers.normalizeGeneratedPayload(...args);
}
function normalizeRecipe(...args) {
  if (!generatedPayloadHelpers) {
    throw new Error("Generated payload helpers are not ready.");
  }
  return generatedPayloadHelpers.normalizeRecipe(...args);
}
function readGeneratedArray(...args) {
  if (!generatedPayloadHelpers) {
    throw new Error("Generated payload helpers are not ready.");
  }
  return generatedPayloadHelpers.readGeneratedArray(...args);
}
function readGeneratedString(...args) {
  if (!generatedPayloadHelpers) {
    throw new Error("Generated payload helpers are not ready.");
  }
  return generatedPayloadHelpers.readGeneratedString(...args);
}
const ensureJobImages = (...args) => {
  if (!imageAssetHelpers) {
    throw new Error("Image asset helpers are not ready.");
  }
  return imageAssetHelpers.ensureJobImages(...args);
};
const generateCoreArticlePackage = (...args) => {
  if (!generationRunner) {
    throw new Error("Generation runner is not ready.");
  }
  return generationRunner.generateCoreArticlePackage(...args);
};
const generateSocialCandidatePool = (...args) => {
  if (!generationRunner) {
    throw new Error("Generation runner is not ready.");
  }
  return generationRunner.generateSocialCandidatePool(...args);
};
const assertFacebookConfigured = (...args) => {
  if (!jobUtils) {
    throw new Error("Job utils are not ready.");
  }
  return jobUtils.assertFacebookConfigured(...args);
};
const assertRecipeDistributionTargets = (...args) => {
  if (!jobUtils) {
    throw new Error("Job utils are not ready.");
  }
  return jobUtils.assertRecipeDistributionTargets(...args);
};
const firstAttachment = (...args) => {
  if (!jobUtils) {
    throw new Error("Job utils are not ready.");
  }
  return jobUtils.firstAttachment(...args);
};
const firstSuccessfulDistributionResult = (...args) => {
  if (!jobUtils) {
    throw new Error("Job utils are not ready.");
  }
  return jobUtils.firstSuccessfulDistributionResult(...args);
};
const summarizeFacebookFailures = (...args) => {
  if (!jobUtils) {
    throw new Error("Job utils are not ready.");
  }
  return jobUtils.summarizeFacebookFailures(...args);
};
const ensureInternalLinks = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageContentHelpers.ensureInternalLinks(...args);
};
const ensureInternalLinksOnPages = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageContentHelpers.ensureInternalLinksOnPages(...args);
};
const mergeContentPagesIntoHtml = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageContentHelpers.mergeContentPagesIntoHtml(...args);
};
const normalizeContentPageItem = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageContentHelpers.normalizeContentPageItem(...args);
};
const extractFirstHeading = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageFlowHelpers.extractFirstHeading(...args);
};
const normalizeGeneratedPageFlow = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageFlowHelpers.normalizeGeneratedPageFlow(...args);
};
const resolveGeneratedContentHtml = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageSplittingHelpers.resolveGeneratedContentHtml(...args);
};
const resolveGeneratedContentPages = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageSplittingHelpers.resolveGeneratedContentPages(...args);
};
const splitHtmlIntoPages = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageSplittingHelpers.splitHtmlIntoPages(...args);
};
const stabilizeGeneratedContentPages = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.pageSplittingHelpers.stabilizeGeneratedContentPages(...args);
};
const buildArticleSocialSignals = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.socialSignalHelpers.buildArticleSocialSignals(...args);
};
const buildImagePrompt = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.buildImagePrompt(...args);
};
const validateGeneratedPayload = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.articleValidationHelpers.validateGeneratedPayload(...args);
};
const buildCoreArticlePrompt = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.promptBuilders.buildCoreArticlePrompt(...args);
};
const buildSocialCandidatePrompt = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.promptBuilders.buildSocialCandidatePrompt(...args);
};
const summarizeArticleStage = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.summarizeArticleStage(...args);
};
const summarizeSocialCandidatePool = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.summarizeSocialCandidatePool(...args);
};
const buildQualitySummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.buildQualitySummary(...args);
};
const summarizeSelectedSocialPack = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.summarizeSelectedSocialPack(...args);
};
const ensureSocialPackCoverage = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.ensureSocialPackCoverage(...args);
};
const assertQualityGate = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.validatorSummaryHelpers.assertQualityGate(...args);
};
const mergeValidatorSummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.validatorSummaryHelpers.mergeValidatorSummary(...args);
};
const normalizeCaptionOpeningFingerprint = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.normalizeCaptionOpeningFingerprint(...args);
};
const normalizeHookFingerprint = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.normalizeHookFingerprint(...args);
};
const normalizeSocialFingerprint = (...args) => {
  if (!facebookHelpers) {
    throw new Error("Facebook helpers are not ready.");
  }
  return facebookHelpers.normalizeSocialFingerprint(...args);
};
const deriveLegacyFacebookCaptionMirror = (...args) => {
  if (!channelAdapterHelpers) {
    throw new Error("Channel adapter helpers are not ready.");
  }
  return channelAdapterHelpers.deriveLegacyFacebookCaptionMirror(...args);
};
const deriveLegacyGroupShareKitMirror = (...args) => {
  if (!channelAdapterHelpers) {
    throw new Error("Channel adapter helpers are not ready.");
  }
  return channelAdapterHelpers.deriveLegacyGroupShareKitMirror(...args);
};
const resolveCanonicalContentPackage = (...args) => {
  if (!channelAdapterHelpers) {
    throw new Error("Channel adapter helpers are not ready.");
  }
  return channelAdapterHelpers.resolveCanonicalContentPackage(...args);
};
const resolveFacebookChannelAdapter = (...args) => {
  if (!channelAdapterHelpers) {
    throw new Error("Channel adapter helpers are not ready.");
  }
  return channelAdapterHelpers.resolveFacebookChannelAdapter(...args);
};
const syncGeneratedContractContainers = (...args) => {
  if (!channelAdapterHelpers) {
    throw new Error("Channel adapter helpers are not ready.");
  }
  return channelAdapterHelpers.syncGeneratedContractContainers(...args);
};
const desiredSocialSignalTargets = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.desiredSocialSignalTargets(...args);
};
const findBestSocialCandidateIndex = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.findBestSocialCandidateIndex(...args);
};
const buildFallbackSocialPack = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.buildFallbackSocialPack(...args);
};
const qualityCheckReasonMessage = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.qualityCheckReasonMessage(...args);
};
const buildEditorialReadinessSummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.buildEditorialReadinessSummary(...args);
};
const extractValidatorSummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.extractValidatorSummary(...args);
};
const extractGeneratedContractVersions = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.extractGeneratedContractVersions(...args);
};
const isCanonicalContractVersion = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.isCanonicalContractVersion(...args);
};
const collectCanonicalContractChecks = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.collectCanonicalContractChecks(...args);
};
const filterChannelWarningChecks = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.filterChannelWarningChecks(...args);
};
const buildContentPackageQualitySummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.buildContentPackageQualitySummary(...args);
};
const buildFacebookChannelQualitySummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.buildFacebookChannelQualitySummary(...args);
};
const buildDormantChannelQualitySummary = (...args) => {
  if (!contentHelpers) {
    throw new Error("Content helpers are not ready.");
  }
  return contentHelpers.qualityEntryPoints.buildDormantChannelQualitySummary(...args);
};

const contractsHelpers = createContractsHelpers({
  buildArticleSocialSignals,
  buildChannelProfile,
  buildContentPackageQualitySummary,
  buildContentTypeProfile,
  buildDormantChannelQualitySummary,
  buildFacebookChannelQualitySummary,
  buildFacebookPostMessage,
  CHANNEL_ADAPTER_CONTRACT_VERSION,
  cleanMultilineText,
  cleanText,
  CONTENT_PACKAGE_CONTRACT_VERSION,
  ensureInternalLinks,
  ensureInternalLinksOnPages,
  ensureStringArray,
  formatError,
  isPlainObject,
  mergeContentPagesIntoHtml,
  normalizeFacebookDistribution,
  normalizeGeneratedPageFlow,
  normalizeRecipe,
  normalizeSlug,
  normalizeSocialPack,
  resolveGeneratedContentHtml,
  resolveGeneratedContentPages,
  splitHtmlIntoPages,
  stabilizeGeneratedContentPages,
  syncGeneratedContractContainers,
  trimText,
});
generatedPayloadHelpers = contractsHelpers.generatedPayloadHelpers;
channelDraftHelpers = contractsHelpers.channelDraftHelpers;
channelAdapterHelpers = contractsHelpers.channelAdapterHelpers;

facebookHelpers = createFacebookHelpers({
  cleanText,
  cleanMultilineText,
  formatError,
  formPostJson,
  normalizeAngleKey,
  normalizeSlug,
  normalizeSocialLineFingerprint,
  resolveCanonicalContentPackage,
  stripHookEchoFromCaption,
  trimText,
});

contentHelpers = createContentHelpers({
  angleDefinition,
  angleDefinitionsForType,
  buildAngleSequence,
  buildFallbackCaption,
  buildContentTypeProfile,
  buildFacebookPostMessage,
  buildPageAnglePlan,
  classifySocialHookForm,
  cleanMultilineText,
  cleanText,
  config,
  countInternalLinks,
  countWords,
  desiredSocialSignalTargets,
  ensureOpenAiConfigured,
  ensureStringArray,
  escapeHtml,
  extractOpeningParagraphText,
  firstSentence,
  formatError,
  generateImageBase64,
  internalLinkTargetsForJob,
  isPlainObject,
  joinNaturalList,
  log,
  normalizeAngleKey,
  normalizeCaptionOpeningFingerprint,
  normalizeHtml,
  normalizeHookFingerprint,
  normalizeSlug,
  normalizeSocialFingerprint,
  normalizeSocialLineFingerprint,
  normalizeSocialPack,
  parseJsonObject,
  readGeneratedArray,
  readGeneratedString,
  requestOpenAiChat,
  resolveCanonicalContentPackage,
  resolveFacebookChannelAdapter,
  resolveSelectedFacebookPages,
  resolveTypedGuidance,
  runBuildContentPackageQualitySummary,
  runBuildDormantChannelQualitySummary,
  runBuildEditorialReadinessSummary,
  runBuildFacebookChannelQualitySummary,
  runBuildFallbackSocialPack,
  runBuildQualitySummary,
  runCollectCanonicalContractChecks,
  runDesiredSocialSignalTargets,
  runEnsureSocialPackCoverage,
  runExtractGeneratedContractVersions,
  runExtractValidatorSummary,
  runFilterChannelWarningChecks,
  runFindBestSocialCandidateIndex,
  runGenerateCoreArticlePackage,
  runGenerateSocialCandidatePool,
  runIsCanonicalContractVersion,
  runQualityCheckReasonMessage,
  runSummarizeArticleStage,
  runSummarizeSelectedSocialPack,
  runSummarizeSocialCandidatePool,
  scoreSocialVariant,
  sentenceCase,
  sharedWordsRatio,
  socialVariantActionabilitySignal,
  socialVariantAnchorSignal,
  socialVariantConsequenceSignal,
  socialVariantContrastSignal,
  socialVariantConversationSignal,
  socialVariantCuriositySignal,
  socialVariantHabitShiftSignal,
  socialVariantIdentityShiftSignal,
  socialVariantImmediacySignal,
  socialVariantLooksWeak,
  socialVariantNoveltyScore,
  socialVariantPainPointSignal,
  socialVariantPayoffSignal,
  socialVariantPromiseFocusSignal,
  socialVariantPromiseSyncSignal,
  socialVariantProofSignal,
  socialVariantRelatabilitySignal,
  socialVariantResolvesEarly,
  socialVariantSavvySignal,
  socialVariantScannabilitySignal,
  socialVariantSelfRecognitionSignal,
  socialVariantSpecificityScore,
  socialVariantTwoStepSignal,
  stripHookEchoFromCaption,
  summarizeSelectedSocialPack,
  toInt,
  trimText,
  trimWords,
  wpRequest,
  normalizeGeneratedPayload,
  syncGeneratedContractContainers,
});

imageAssetHelpers = contentHelpers ? { ensureJobImages: (...args) => contentHelpers.ensureJobImages(...args) } : null;
generationRunner = contentHelpers ? contentHelpers.generationRunner : null;

jobUtils = createJobUtils({
  cleanText,
  normalizeFacebookDistribution,
  toInt,
});

const timeHelpers = createTimeHelpers({ cleanText });
const isFutureUtcTimestamp = (...args) => timeHelpers.isFutureUtcTimestamp(...args);

const jobsHelpers = createJobsHelpers({
  WORKER_VERSION,
  config,
  deriveLegacyFacebookCaptionMirror,
  deriveLegacyGroupShareKitMirror,
  formatError,
  log,
  resolveCanonicalContentPackage,
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
  resolvePreferredAngle,
  resolvePublicationProfile,
  resolveSelectedFacebookPages,
  resolveTypedGuidance,
  summarizeSelectedSocialPack,
  syncGeneratedContractContainers,
  assertFacebookConfigured,
  assertQualityGate,
  assertRecipeDistributionTargets,
  buildFallbackFacebookCaption,
  buildFallbackCaption,
  buildQualitySummary,
  ensureJobImages,
  ensureOpenAiConfigured,
  firstAttachment,
  firstSuccessfulDistributionResult,
  hydrateStoredGeneratedPayload,
  isFutureUtcTimestamp,
  mergeSettings,
  mergeValidatorSummary,
  publishFacebookDistribution,
  seedLegacyFacebookDistribution,
  summarizeFacebookFailures,
});
const wordPressJobClient = jobsHelpers.wordPressJobClient;
const packageGenerator = jobsHelpers.packageGenerator;
const jobOrchestrator = jobsHelpers.jobOrchestrator;
const claimNextJob = (...args) => wordPressJobClient.claimNextJob(...args);
const completeJob = (...args) => wordPressJobClient.completeJob(...args);
const idleHeartbeatState = (...args) => wordPressJobClient.idleHeartbeatState(...args);
const publishBlogPost = (...args) => wordPressJobClient.publishBlogPost(...args);
const safeFailJob = (...args) => wordPressJobClient.safeFailJob(...args);
const sendHeartbeatBestEffort = (...args) => wordPressJobClient.sendHeartbeatBestEffort(...args);
const updateJobProgress = (...args) => wordPressJobClient.updateJobProgress(...args);
const generatePackage = (...args) => packageGenerator.generatePackage(...args);
const processNextJob = (...args) => jobOrchestrator.processNextJob(...args);

const workerRunner = createWorkerRunner({
  config,
  sleep,
  SECOND_MS,
  log,
  formatError,
  sendHeartbeatBestEffort,
  idleLoop,
  hasWorkerConfig,
  idleHeartbeatState,
  processNextJob,
});

workerRunner.run().catch(async (error) => {
  await workerRunner.handleFatal(error);
  process.exit(1);
});
