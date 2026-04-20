import { createChannelAdapterHelpers } from "./channel-adapters.mjs";
import { createChannelDraftHelpers } from "./channel-drafts.mjs";
import { createGeneratedPayloadHelpers } from "./generated-payload.mjs";

export function createContractsHelpers(deps) {
  const {
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
  } = deps;

  const channelDraftHelpers = createChannelDraftHelpers({
    cleanMultilineText,
    cleanText,
    isPlainObject,
    trimText,
  });

  const channelAdapterHelpers = createChannelAdapterHelpers({
    buildArticleSocialSignals,
    buildChannelProfile,
    buildContentPackageQualitySummary,
    buildContentTypeProfile,
    buildDormantChannelQualitySummary,
    buildFacebookChannelQualitySummary,
    buildFacebookGroupsDraft: channelDraftHelpers.buildFacebookGroupsDraft,
    buildPinterestDraft: channelDraftHelpers.buildPinterestDraft,
    buildFacebookPostMessage,
    CHANNEL_ADAPTER_CONTRACT_VERSION,
    cleanMultilineText,
    cleanText,
    CONTENT_PACKAGE_CONTRACT_VERSION,
    isPlainObject,
    mergeContentPagesIntoHtml,
    normalizeFacebookDistribution,
    normalizeGeneratedPageFlow,
    normalizeRecipe,
    normalizeSlug,
    normalizeSocialPack,
    splitHtmlIntoPages,
    stabilizeGeneratedContentPages,
    trimText,
  });

  const generatedPayloadHelpers = createGeneratedPayloadHelpers({
    cleanText,
    cleanMultilineText,
    ensureInternalLinks,
    ensureInternalLinksOnPages,
    ensureStringArray,
    formatError,
    isPlainObject,
    mergeContentPagesIntoHtml,
    normalizeFacebookDistribution,
    normalizeGeneratedPageFlow,
    normalizeSlug,
    normalizeSocialPack,
    resolveLegacyChannelMirrors: channelAdapterHelpers.resolveLegacyChannelMirrors,
    resolveGeneratedContentHtml,
    resolveGeneratedContentPages,
    splitHtmlIntoPages,
    stabilizeGeneratedContentPages,
    syncGeneratedContractContainers,
    trimText,
  });

  return {
    channelAdapterHelpers,
    channelDraftHelpers,
    generatedPayloadHelpers,
  };
}
