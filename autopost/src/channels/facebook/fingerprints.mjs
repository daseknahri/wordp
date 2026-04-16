export function createFacebookFingerprintHelpers(deps) {
  const {
    buildFacebookPostMessage,
    cleanMultilineText,
    normalizeSocialLineFingerprint,
  } = deps;

  function normalizeSocialFingerprint(variant) {
    return buildFacebookPostMessage(variant, "", "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, " ")
      .trim();
  }

  function normalizeHookFingerprint(variant) {
    return normalizeSocialLineFingerprint(variant?.hook || "");
  }

  function normalizeCaptionOpeningFingerprint(variant) {
    const firstLine = cleanMultilineText(variant?.caption || "")
      .split(/\r?\n/)
      .map((line) => line.trim())
      .find(Boolean);

    return normalizeSocialLineFingerprint(firstLine || "");
  }

  return {
    normalizeCaptionOpeningFingerprint,
    normalizeHookFingerprint,
    normalizeSocialFingerprint,
    normalizeSocialLineFingerprint,
  };
}
