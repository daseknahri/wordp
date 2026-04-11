export function createListHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    ensureStringArray,
    normalizeSlug,
  } = deps;

  function joinNaturalList(items) {
    const cleanItems = ensureStringArray(items);
    if (!cleanItems.length) {
      return "";
    }
    if (cleanItems.length === 1) {
      return cleanItems[0];
    }
    if (cleanItems.length === 2) {
      return `${cleanItems[0]} and ${cleanItems[1]}`;
    }

    return `${cleanItems.slice(0, -1).join(", ")}, and ${cleanItems[cleanItems.length - 1]}`;
  }

  function buildFallbackCaption(primaryLine, secondaryLine, tertiaryLine, closer) {
    const lines = [];
    const seen = new Set();

    for (const line of [primaryLine, secondaryLine, tertiaryLine]) {
      const cleaned = cleanText(line);
      const fingerprint = normalizeSlug(cleaned);
      if (!cleaned || !fingerprint || seen.has(fingerprint)) {
        continue;
      }
      seen.add(fingerprint);
      lines.push(cleaned);
      if (lines.length >= 2) {
        break;
      }
    }

    lines.push(cleanText(closer));

    return cleanMultilineText(lines.filter(Boolean).join("\n"));
  }

  const stopWords = new Set([
    "the",
    "a",
    "an",
    "and",
    "or",
    "for",
    "with",
    "your",
    "this",
    "that",
    "from",
    "into",
    "about",
    "what",
    "when",
    "why",
    "how",
    "most",
    "more",
    "than",
  ]);

  function sharedWordsRatio(left, right) {
    const tokenize = (value) =>
      cleanText(value)
        .toLowerCase()
        .split(/[^a-z0-9]+/)
        .map((token) => token.trim())
        .filter((token) => token && token.length > 2 && !stopWords.has(token));

    const leftTokens = Array.from(new Set(tokenize(left)));
    const rightTokens = new Set(tokenize(right));
    if (!leftTokens.length || !rightTokens.size) {
      return 0;
    }

    const shared = leftTokens.filter((token) => rightTokens.has(token)).length;
    return shared / Math.max(1, leftTokens.length);
  }

  return {
    buildFallbackCaption,
    joinNaturalList,
    sharedWordsRatio,
  };
}
