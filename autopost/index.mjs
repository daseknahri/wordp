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
import { createLateBoundMethodFacade } from "./src/runtime/late-bound.mjs";

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
const {
  buildFacebookComment,
  buildFacebookCommentUrl,
  buildFacebookPostMessage,
  buildFacebookPostUrl,
  buildFallbackFacebookCaption,
  normalizeFacebookDistribution,
  normalizeSocialPack,
  resolveSelectedFacebookPages,
  seedLegacyFacebookDistribution,
  publishFacebookDistribution,
  normalizeCaptionOpeningFingerprint,
  normalizeHookFingerprint,
  normalizeSocialFingerprint,
} = createLateBoundMethodFacade(
  () => facebookHelpers,
  "Facebook helpers are not ready.",
  {
    buildFacebookComment: "buildFacebookComment",
    buildFacebookCommentUrl: "buildFacebookCommentUrl",
    buildFacebookPostMessage: "buildFacebookPostMessage",
    buildFacebookPostUrl: "buildFacebookPostUrl",
    buildFallbackFacebookCaption: "buildFallbackFacebookCaption",
    normalizeFacebookDistribution: "normalizeFacebookDistribution",
    normalizeSocialPack: "normalizeSocialPack",
    resolveSelectedFacebookPages: "resolveSelectedFacebookPages",
    seedLegacyFacebookDistribution: "seedLegacyFacebookDistribution",
    publishFacebookDistribution: "publishFacebookDistribution",
    normalizeCaptionOpeningFingerprint: "normalizeCaptionOpeningFingerprint",
    normalizeHookFingerprint: "normalizeHookFingerprint",
    normalizeSocialFingerprint: "normalizeSocialFingerprint",
  },
);

const {
  buildFacebookGroupsDraft,
  buildPinterestDraft,
} = createLateBoundMethodFacade(
  () => channelDraftHelpers,
  "Channel draft helpers are not ready.",
  {
    buildFacebookGroupsDraft: "buildFacebookGroupsDraft",
    buildPinterestDraft: "buildPinterestDraft",
  },
);

const {
  hydrateStoredGeneratedPayload,
  normalizeGeneratedPayload,
  normalizeRecipe,
  readGeneratedArray,
  readGeneratedString,
} = createLateBoundMethodFacade(
  () => generatedPayloadHelpers,
  "Generated payload helpers are not ready.",
  {
    hydrateStoredGeneratedPayload: "hydrateStoredGeneratedPayload",
    normalizeGeneratedPayload: "normalizeGeneratedPayload",
    normalizeRecipe: "normalizeRecipe",
    readGeneratedArray: "readGeneratedArray",
    readGeneratedString: "readGeneratedString",
  },
);

const {
  ensureJobImages,
} = createLateBoundMethodFacade(
  () => imageAssetHelpers,
  "Image asset helpers are not ready.",
  {
    ensureJobImages: "ensureJobImages",
  },
);

const {
  generateCoreArticlePackage,
  generateSocialCandidatePool,
} = createLateBoundMethodFacade(
  () => generationRunner,
  "Generation runner is not ready.",
  {
    generateCoreArticlePackage: "generateCoreArticlePackage",
    generateSocialCandidatePool: "generateSocialCandidatePool",
  },
);

const {
  assertFacebookConfigured,
  assertRecipeDistributionTargets,
  firstAttachment,
  firstSuccessfulDistributionResult,
  summarizeFacebookFailures,
} = createLateBoundMethodFacade(
  () => jobUtils,
  "Job utils are not ready.",
  {
    assertFacebookConfigured: "assertFacebookConfigured",
    assertRecipeDistributionTargets: "assertRecipeDistributionTargets",
    firstAttachment: "firstAttachment",
    firstSuccessfulDistributionResult: "firstSuccessfulDistributionResult",
    summarizeFacebookFailures: "summarizeFacebookFailures",
  },
);

const {
  ensureInternalLinks,
  ensureInternalLinksOnPages,
  mergeContentPagesIntoHtml,
  normalizeContentPageItem,
  extractFirstHeading,
  normalizeGeneratedPageFlow,
  resolveGeneratedContentHtml,
  resolveGeneratedContentPages,
  splitHtmlIntoPages,
  stabilizeGeneratedContentPages,
  buildArticleSocialSignals,
  buildImagePrompt,
  validateGeneratedPayload,
  buildCoreArticlePrompt,
  buildSocialCandidatePrompt,
  summarizeArticleStage,
  summarizeSocialCandidatePool,
  buildQualitySummary,
  summarizeSelectedSocialPack,
  ensureSocialPackCoverage,
  assertQualityGate,
  mergeValidatorSummary,
  desiredSocialSignalTargets,
  findBestSocialCandidateIndex,
  buildFallbackSocialPack,
  qualityCheckReasonMessage,
  buildEditorialReadinessSummary,
  extractValidatorSummary,
  extractGeneratedContractVersions,
  isCanonicalContractVersion,
  collectCanonicalContractChecks,
  filterChannelWarningChecks,
  buildContentPackageQualitySummary,
  buildFacebookChannelQualitySummary,
  buildDormantChannelQualitySummary,
} = createLateBoundMethodFacade(
  () => contentHelpers,
  "Content helpers are not ready.",
  {
    ensureInternalLinks: ["pageContentHelpers", "ensureInternalLinks"],
    ensureInternalLinksOnPages: ["pageContentHelpers", "ensureInternalLinksOnPages"],
    mergeContentPagesIntoHtml: ["pageContentHelpers", "mergeContentPagesIntoHtml"],
    normalizeContentPageItem: ["pageContentHelpers", "normalizeContentPageItem"],
    extractFirstHeading: ["pageFlowHelpers", "extractFirstHeading"],
    normalizeGeneratedPageFlow: ["pageFlowHelpers", "normalizeGeneratedPageFlow"],
    resolveGeneratedContentHtml: ["pageSplittingHelpers", "resolveGeneratedContentHtml"],
    resolveGeneratedContentPages: ["pageSplittingHelpers", "resolveGeneratedContentPages"],
    splitHtmlIntoPages: ["pageSplittingHelpers", "splitHtmlIntoPages"],
    stabilizeGeneratedContentPages: ["pageSplittingHelpers", "stabilizeGeneratedContentPages"],
    buildArticleSocialSignals: ["socialSignalHelpers", "buildArticleSocialSignals"],
    buildImagePrompt: "buildImagePrompt",
    validateGeneratedPayload: ["articleValidationHelpers", "validateGeneratedPayload"],
    buildCoreArticlePrompt: ["promptBuilders", "buildCoreArticlePrompt"],
    buildSocialCandidatePrompt: ["promptBuilders", "buildSocialCandidatePrompt"],
    summarizeArticleStage: ["qualityEntryPoints", "summarizeArticleStage"],
    summarizeSocialCandidatePool: ["qualityEntryPoints", "summarizeSocialCandidatePool"],
    buildQualitySummary: ["qualityEntryPoints", "buildQualitySummary"],
    summarizeSelectedSocialPack: ["qualityEntryPoints", "summarizeSelectedSocialPack"],
    ensureSocialPackCoverage: ["qualityEntryPoints", "ensureSocialPackCoverage"],
    assertQualityGate: ["validatorSummaryHelpers", "assertQualityGate"],
    mergeValidatorSummary: ["validatorSummaryHelpers", "mergeValidatorSummary"],
    desiredSocialSignalTargets: ["qualityEntryPoints", "desiredSocialSignalTargets"],
    findBestSocialCandidateIndex: ["qualityEntryPoints", "findBestSocialCandidateIndex"],
    buildFallbackSocialPack: ["qualityEntryPoints", "buildFallbackSocialPack"],
    qualityCheckReasonMessage: ["qualityEntryPoints", "qualityCheckReasonMessage"],
    buildEditorialReadinessSummary: ["qualityEntryPoints", "buildEditorialReadinessSummary"],
    extractValidatorSummary: ["qualityEntryPoints", "extractValidatorSummary"],
    extractGeneratedContractVersions: ["qualityEntryPoints", "extractGeneratedContractVersions"],
    isCanonicalContractVersion: ["qualityEntryPoints", "isCanonicalContractVersion"],
    collectCanonicalContractChecks: ["qualityEntryPoints", "collectCanonicalContractChecks"],
    filterChannelWarningChecks: ["qualityEntryPoints", "filterChannelWarningChecks"],
    buildContentPackageQualitySummary: ["qualityEntryPoints", "buildContentPackageQualitySummary"],
    buildFacebookChannelQualitySummary: ["qualityEntryPoints", "buildFacebookChannelQualitySummary"],
    buildDormantChannelQualitySummary: ["qualityEntryPoints", "buildDormantChannelQualitySummary"],
  },
);

const {
  deriveLegacyFacebookCaptionMirror,
  deriveLegacyGroupShareKitMirror,
  resolveCanonicalContentPackage,
  resolveFacebookDefaultCtas,
  resolveFacebookChannelAdapter,
  syncGeneratedContractContainers,
} = createLateBoundMethodFacade(
  () => channelAdapterHelpers,
  "Channel adapter helpers are not ready.",
  {
    deriveLegacyFacebookCaptionMirror: "deriveLegacyFacebookCaptionMirror",
    deriveLegacyGroupShareKitMirror: "deriveLegacyGroupShareKitMirror",
    resolveCanonicalContentPackage: "resolveCanonicalContentPackage",
    resolveFacebookDefaultCtas: "resolveFacebookDefaultCtas",
    resolveFacebookChannelAdapter: "resolveFacebookChannelAdapter",
    syncGeneratedContractContainers: "syncGeneratedContractContainers",
  },
);

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
