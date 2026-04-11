export function createRuntimeNormalizers(deps) {
  const {
    cleanMultilineText,
    cleanText,
    isPlainObject,
  } = deps;

  function resolveTypedGuidance(settings, channel, contentType, fallback = "") {
    const presets = settings.contentMachine.channelPresets || {};
    const channelPreset = presets[channel];

    if (isPlainObject(channelPreset)) {
      if (typeof channelPreset.guidance === "string" && cleanMultilineText(channelPreset.guidance)) {
        return cleanMultilineText(channelPreset.guidance);
      }

      const typed = channelPreset[contentType];
      if (isPlainObject(typed) && typeof typed.guidance === "string" && cleanMultilineText(typed.guidance)) {
        return cleanMultilineText(typed.guidance);
      }
    }

    return cleanMultilineText(fallback);
  }

  function normalizeFacebookPages(rawPages, legacyPageId = "", legacyAccessToken = "") {
    const pages = [];
    const pageMap = new Map();

    if (Array.isArray(rawPages)) {
      for (const raw of rawPages) {
        if (!isPlainObject(raw)) {
          continue;
        }

        const pageId = cleanText(raw.page_id || raw.pageId);
        const label = cleanText(raw.label || raw.name || "");
        const accessToken = cleanMultilineText(raw.access_token || raw.accessToken || "");
        const active = Boolean(raw.active);

        if (!pageId || !label) {
          continue;
        }

        pageMap.set(pageId, {
          page_id: pageId,
          label,
          access_token: accessToken,
          active,
        });
      }
    }

    const legacyId = cleanText(legacyPageId);
    const legacyToken = cleanMultilineText(legacyAccessToken);
    if (legacyId && !pageMap.has(legacyId)) {
      pageMap.set(legacyId, {
        page_id: legacyId,
        label: "Primary Page",
        access_token: legacyToken,
        active: Boolean(legacyToken),
      });
    }

    for (const page of pageMap.values()) {
      if (!page.page_id) {
        continue;
      }
      pages.push(page);
    }

    return pages;
  }

  function normalizePreset(value, fallbackGuidance, fallbackMinWords) {
    const preset = isPlainObject(value) ? value : {};
    return {
      label: cleanText(preset.label),
      guidance: cleanMultilineText(preset.guidance || fallbackGuidance),
      min_words: Math.max(800, Number(preset.min_words || fallbackMinWords)),
    };
  }

  return {
    normalizeFacebookPages,
    normalizePreset,
    resolveTypedGuidance,
  };
}
