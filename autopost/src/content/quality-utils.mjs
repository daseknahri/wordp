export function createContentQualityUtils(deps) {
  const {
    cleanText,
    cleanMultilineText,
    trimWords,
    countWords,
    normalizeSlug,
    sharedWordsRatio,
  } = deps;

  const pageStartsWithExpectedLead = (pageHtml, index) => {
    const page = String(pageHtml || "").trim();
    if (!page) {
      return false;
    }

    if (index === 0) {
      return /^<p\b/i.test(page);
    }

    return /^<(h2|blockquote|ul|ol)\b/i.test(page);
  };

  const frontLoadedClickSignalScore = (text, contentType = "recipe") => {
    const lead = trimWords(cleanText(text || ""), 5).toLowerCase();
    if (!lead) {
      return 0;
    }

    let score = 0;
    if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|truth|myth|actually|really|better|crispy|creamy|budget|weeknight|juicy|quick|simple|get wrong|most people)\b/i.test(lead)) {
      score += 2;
    }
    if (contentType === "recipe" && /\b(one-pan|sheet pan|air fryer|skillet|cheesy|garlicky|comfort|dinner|takeout)\b/i.test(lead)) {
      score += 1;
    }
    if (contentType === "food_fact" && /\b(why|how|what|truth|myth|mistake|actually)\b/i.test(lead)) {
      score += 1;
    }
    if (/\b\d+\b/.test(lead)) {
      score += 1;
    }
    if (/^(you need to|you should|this is|this one|these are|here'?s why|the best)\b/i.test(lead)) {
      score -= 2;
    }

    return score;
  };

  const contrastClickSignalScore = (text) => {
    const normalized = cleanText(text || "").toLowerCase();
    if (!normalized) {
      return 0;
    }

    return /\b(instead of|rather than|not just|not the|more than|less about|what most people miss|what changes|vs\.?|versus)\b/i.test(normalized)
      ? 1
      : 0;
  };

  const headlineSpecificityScore = (title, contentType = "recipe", topic = "") => {
    const text = cleanText(title || "");
    const normalizedTitle = normalizeSlug(text);
    const normalizedTopic = normalizeSlug(topic || "");
    const words = countWords(text);
    let score = 0;

    if (!text) {
      return 0;
    }
    if (words >= 5 && words <= 13) {
      score += 3;
    } else if (words >= 4 && words <= 16) {
      score += 1;
    } else {
      score -= 2;
    }

    if (normalizedTopic && normalizedTitle && normalizedTitle !== normalizedTopic) {
      score += 2;
    }

    if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|why|how|actually|really|most people|get wrong)\b/i.test(text)) {
      score += 3;
    }
    if (/\b(one-pan|weeknight|crispy|creamy|cheesy|garlicky|juicy|budget|air fryer|oven|skillet|better than takeout)\b/i.test(text)) {
      score += 2;
    }
    if (/\b\d+\b/.test(text)) {
      score += 1;
    }
    score += frontLoadedClickSignalScore(text, contentType);
    score += contrastClickSignalScore(text);
    if (/[?]/.test(text)) {
      score -= 1;
    }
    if (/\b(recipe|guide|tips|ideas|facts|article)\b/i.test(text) && words <= 6) {
      score -= 2;
    }
    if (contentType === "food_fact" && normalizedTopic && normalizedTitle === normalizedTopic) {
      score -= 2;
    }

    return score;
  };

  const openingPromiseAlignmentScore = (title, openingParagraph) => {
    const titleText = cleanText(title || "");
    const openingText = cleanText(openingParagraph || "");
    if (!titleText || !openingText) {
      return 0;
    }

    const overlap = sharedWordsRatio(titleText, openingText);
    let score = 0;
    if (overlap >= 0.24) {
      score += 3;
    } else if (overlap >= 0.14) {
      score += 2;
    } else if (overlap >= 0.08) {
      score += 1;
    }
    if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|payoff|problem|why|how)\b/i.test(openingText)) {
      score += 1;
    }
    if (frontLoadedClickSignalScore(openingText) > 0) {
      score += 1;
    }
    score += contrastClickSignalScore(openingText);

    return score;
  };

  const excerptClickSignalScore = (excerpt, title = "", openingParagraph = "") => {
    const text = cleanText(excerpt || "");
    const words = countWords(text);
    const titleOverlap = sharedWordsRatio(text, title);
    const openingOverlap = openingParagraph ? sharedWordsRatio(text, openingParagraph) : 0;
    let score = 0;

    if (!text) {
      return 0;
    }
    if (words >= 12 && words <= 30) {
      score += 2;
    } else if (words >= 10 && words <= 36) {
      score += 1;
    }
    if (titleOverlap <= 0.72) {
      score += 2;
    } else if (titleOverlap >= 0.9) {
      score -= 2;
    }
    if (openingParagraph && openingOverlap >= 0.08 && openingOverlap <= 0.7) {
      score += 1;
    }
    if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i.test(text)) {
      score += 2;
    }
    if (frontLoadedClickSignalScore(text) > 0) {
      score += 1;
    }
    score += contrastClickSignalScore(text);

    return score;
  };

  const seoDescriptionSignalScore = (seoDescription, title = "", excerpt = "") => {
    const text = cleanText(seoDescription || "");
    const words = countWords(text);
    const titleOverlap = sharedWordsRatio(text, title);
    const excerptOverlap = excerpt ? sharedWordsRatio(text, excerpt) : 0;
    let score = 0;

    if (!text) {
      return 0;
    }
    if (words >= 12 && words <= 28) {
      score += 2;
    } else if (words >= 10 && words <= 32) {
      score += 1;
    }
    if (titleOverlap <= 0.72) {
      score += 2;
    } else if (titleOverlap >= 0.9) {
      score -= 2;
    }
    if (excerpt && excerptOverlap >= 0.08 && excerptOverlap <= 0.8) {
      score += 1;
    }
    if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i.test(text)) {
      score += 2;
    }
    if (frontLoadedClickSignalScore(text) > 0) {
      score += 1;
    }
    score += contrastClickSignalScore(text);

    return score;
  };

  const titleLooksStrong = (title, topic = "", contentType = "recipe") => {
    const text = cleanText(title || "");
    const words = countWords(text);
    if (!text) {
      return false;
    }
    if (words < 4 || words > 16) {
      return false;
    }
    if (!/[a-zA-Z]/.test(text)) {
      return false;
    }
    if (normalizeSlug(text) === normalizeSlug(topic || "")) {
      return false;
    }
    if (frontLoadedClickSignalScore(text, contentType) < 0) {
      return false;
    }
    return headlineSpecificityScore(text, contentType, topic) >= 3;
  };

  const excerptAddsNewValue = (title, excerpt) => {
    if (!excerpt) {
      return false;
    }

    const overlap = sharedWordsRatio(title, excerpt);
    return overlap < 0.8;
  };

  const openingParagraphAddsNewValue = (contentHtml, title, excerpt = "") => {
    const opening = cleanText(String(contentHtml || "").replace(/<[^>]+>/g, " ")).split(/\r?\n/).find(Boolean) || "";
    if (!opening) {
      return false;
    }

    const titleOverlap = sharedWordsRatio(opening, title);
    const excerptOverlap = excerpt ? sharedWordsRatio(opening, excerpt) : 0;
    return titleOverlap < 0.82 && excerptOverlap < 0.82;
  };

  const normalizePageFlowLabelFingerprint = (value) => {
    return cleanText(
      String(value || "")
        .replace(/^(page|part|section|step)\s+\d+\s*[:.)-]?\s*/i, "")
        .replace(/[^a-z0-9\s]/gi, " "),
    )
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();
  };

  const pageFlowLabelLooksStrong = (label, index = 0) => {
    const text = cleanText(label || "");
    const fallbackLabel = `Page ${index + 1}`;
    const fingerprint = normalizePageFlowLabelFingerprint(text || fallbackLabel);
    if (!fingerprint) {
      return false;
    }

    const wordCount = fingerprint.split(/\s+/).filter(Boolean).length;
    if (wordCount < 2 || fingerprint.length < 8) {
      return false;
    }

    return !/^(page|part|section|continue|next page|keep reading|read more)\b/i.test(text);
  };

  const pageFlowSummaryLooksStrong = (summary, label = "") => {
    const text = cleanText(summary || "");
    if (!text) {
      return false;
    }

    const summaryFingerprint = normalizePageFlowLabelFingerprint(text);
    const labelFingerprint = normalizePageFlowLabelFingerprint(label);
    const wordCount = summaryFingerprint.split(/\s+/).filter(Boolean).length;
    if (wordCount < 6) {
      return false;
    }
    if (labelFingerprint && summaryFingerprint === labelFingerprint) {
      return false;
    }

    return !/^(page|part)\s+\d+\b|^(keep reading|continue reading|read more|next up)\b/i.test(text);
  };

  return {
    pageStartsWithExpectedLead,
    frontLoadedClickSignalScore,
    contrastClickSignalScore,
    headlineSpecificityScore,
    openingPromiseAlignmentScore,
    excerptClickSignalScore,
    seoDescriptionSignalScore,
    titleLooksStrong,
    excerptAddsNewValue,
    openingParagraphAddsNewValue,
    normalizePageFlowLabelFingerprint,
    pageFlowLabelLooksStrong,
    pageFlowSummaryLooksStrong,
  };
}
