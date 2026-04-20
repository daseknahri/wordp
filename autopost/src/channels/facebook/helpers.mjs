import { createFacebookAdapterHelpers } from "./adapter-utils.mjs";
import { createFacebookApiHelpers } from "./api.mjs";
import { createFacebookDistributionHelpers } from "./distribution.mjs";
import { createFacebookFingerprintHelpers } from "./fingerprints.mjs";
import { createFacebookPublishHelpers } from "./publish.mjs";
import { createFacebookStateHelpers } from "./state.mjs";

export function createFacebookHelpers(deps) {
  const {
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
  } = deps;

  const adapterHelpers = createFacebookAdapterHelpers({
    cleanText,
    cleanMultilineText,
    normalizeAngleKey,
    normalizeSocialLineFingerprint,
    resolveCanonicalContentPackage,
    stripHookEchoFromCaption,
  });

  const fingerprintHelpers = createFacebookFingerprintHelpers({
    buildFacebookPostMessage: adapterHelpers.buildFacebookPostMessage,
    cleanMultilineText,
    normalizeSocialLineFingerprint,
  });

  const distributionHelpers = createFacebookDistributionHelpers({
    buildFacebookCommentUrl: adapterHelpers.buildFacebookCommentUrl,
    buildFacebookPostMessage: adapterHelpers.buildFacebookPostMessage,
    buildFacebookPostUrl: adapterHelpers.buildFacebookPostUrl,
    cleanMultilineText,
    cleanText,
    normalizeFacebookDistribution: adapterHelpers.normalizeFacebookDistribution,
  });

  const stateHelpers = createFacebookStateHelpers({
    buildFallbackFacebookCaption: adapterHelpers.buildFallbackFacebookCaption,
    buildFacebookCommentUrl: adapterHelpers.buildFacebookCommentUrl,
    buildFacebookPostUrl: adapterHelpers.buildFacebookPostUrl,
    cleanMultilineText,
    cleanText,
    firstSuccessfulDistributionResult: adapterHelpers.firstSuccessfulDistributionResult,
    formatError,
    normalizeAngleKey,
    syncGeneratedContractContainers: deps.syncGeneratedContractContainers,
  });

  const apiHelpers = createFacebookApiHelpers({
    buildFacebookPostUrl: adapterHelpers.buildFacebookPostUrl,
    formPostJson,
  });

  const publishHelpers = createFacebookPublishHelpers({
    buildFacebookComment: adapterHelpers.buildFacebookComment,
    buildFacebookCommentUrl: adapterHelpers.buildFacebookCommentUrl,
    buildFacebookPostMessage: adapterHelpers.buildFacebookPostMessage,
    buildFallbackFacebookCaption: adapterHelpers.buildFallbackFacebookCaption,
    buildFacebookPublishPageState: stateHelpers.buildFacebookPublishPageState,
    markFacebookPublishFailure: stateHelpers.markFacebookPublishFailure,
    normalizeFacebookDistribution: adapterHelpers.normalizeFacebookDistribution,
    normalizeSlug,
    publishFacebookComment: apiHelpers.publishFacebookComment,
    publishFacebookPost: apiHelpers.publishFacebookPost,
    resolveFacebookPublishCtas: stateHelpers.resolveFacebookPublishCtas,
    resolveCanonicalContentPackage,
    trimText,
  });

  return {
    buildFacebookComment: adapterHelpers.buildFacebookComment,
    buildFacebookCommentUrl: adapterHelpers.buildFacebookCommentUrl,
    buildFacebookPostMessage: adapterHelpers.buildFacebookPostMessage,
    buildFacebookPostUrl: adapterHelpers.buildFacebookPostUrl,
    buildFallbackFacebookCaption: adapterHelpers.buildFallbackFacebookCaption,
    normalizeFacebookDistribution: adapterHelpers.normalizeFacebookDistribution,
    normalizeSocialPack: adapterHelpers.normalizeSocialPack,
    normalizeCaptionOpeningFingerprint: fingerprintHelpers.normalizeCaptionOpeningFingerprint,
    normalizeHookFingerprint: fingerprintHelpers.normalizeHookFingerprint,
    normalizeSocialFingerprint: fingerprintHelpers.normalizeSocialFingerprint,
    resolveFacebookDistributionContext: distributionHelpers.resolveFacebookDistributionContext,
    resolveFacebookDistributionResult: stateHelpers.resolveFacebookDistributionResult,
    resolveFacebookTargets: distributionHelpers.resolveFacebookTargets,
    resolveSelectedFacebookPages: distributionHelpers.resolveSelectedFacebookPages,
    publishFacebookDistribution: publishHelpers.publishFacebookDistribution,
  };
}
