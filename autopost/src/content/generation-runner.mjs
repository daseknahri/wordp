export function createGenerationRunner(deps) {
  const {
    angleDefinitionsForType,
    buildCoreArticlePrompt,
    buildSocialCandidatePrompt,
    formatError,
    normalizeGeneratedPayload,
    normalizeSocialPack,
    parseJsonObject,
    requestOpenAiChat,
    runGenerateCoreArticlePackage,
    runGenerateSocialCandidatePool,
    summarizeArticleStage,
    summarizeSocialCandidatePool,
    validateGeneratedPayload,
  } = deps;

  async function generateCoreArticlePackage(job, settings) {
    return runGenerateCoreArticlePackage({
      job,
      settings,
      requestOpenAiChat,
      parseJsonObject,
      buildCoreArticlePrompt,
      normalizeGeneratedPayload,
      validateGeneratedPayload,
      summarizeArticleStage,
      formatError,
    });
  }

  async function generateSocialCandidatePool(job, settings, article, socialTargets, preferredAngle = "") {
    return runGenerateSocialCandidatePool({
      job,
      settings,
      article,
      socialTargets,
      preferredAngle,
      requestOpenAiChat,
      parseJsonObject,
      buildSocialCandidatePrompt,
      normalizeSocialPack,
      summarizeSocialCandidatePool,
      angleDefinitionsForType,
    });
  }

  return {
    generateCoreArticlePackage,
    generateSocialCandidatePool,
  };
}
