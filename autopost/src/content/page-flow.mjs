export function createPageFlowHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    trimText,
    trimWords,
    firstSentence,
    isPlainObject,
    normalizePageFlowLabelFingerprint,
    pageFlowLabelLooksStrong,
    pageFlowSummaryLooksStrong,
  } = deps;

  const extractFirstHeading = (html) => {
    const match = String(html || "").match(/<h2\b[^>]*>(.*?)<\/h2>/i);
    return cleanText(String(match?.[1] || "").replace(/<[^>]+>/g, " "));
  };

  const extractGeneratedPageLabel = (pageHtml, fallback = "") => {
    const html = String(pageHtml || "");
    const heading = cleanText(extractFirstHeading(html));
    if (heading) {
      return trimWords(heading, 8);
    }

    const paragraphMatch = html.match(/<p\b[^>]*>(.*?)<\/p>/i);
    if (paragraphMatch?.[1]) {
      const lead = firstSentence(String(paragraphMatch[1]).replace(/<[^>]+>/g, " "), 90);
      if (lead) {
        return trimWords(lead, 8);
      }
    }

    const plaintext = trimText(cleanText(html.replace(/<[^>]+>/g, " ")), 90);
    return trimWords(plaintext || cleanText(fallback), 8);
  };

  const extractGeneratedPageSummary = (pageHtml, fallback = "") => {
    const html = String(pageHtml || "");
    const paragraphMatch = html.match(/<p\b[^>]*>(.*?)<\/p>/i);
    if (paragraphMatch?.[1]) {
      const paragraph = cleanText(String(paragraphMatch[1]).replace(/<[^>]+>/g, " "));
      if (paragraph) {
        const parts = paragraph.split(/(?<=[.!?])\s+/).filter(Boolean);
        const summary = trimText(cleanText(parts[1] || parts[0] || paragraph), 150);
        if (summary) {
          return summary;
        }
      }
    }

    return firstSentence(html.replace(/<[^>]+>/g, " "), 150) || cleanText(fallback);
  };

  const derivePageFlowLabelFromSummary = (summary, fallback = "") => {
    const source = cleanText(summary || fallback || "");
    if (!source) {
      return "";
    }

    return trimWords(firstSentence(source, 90), 8);
  };

  const buildGeneratedPageFlow = (contentPages) => {
    return (Array.isArray(contentPages) ? contentPages : [])
      .map((page, index) => {
        const html = String(page || "");
        if (!cleanText(html.replace(/<[^>]+>/g, " "))) {
          return null;
        }

        return {
          index: index + 1,
          label: extractGeneratedPageLabel(html, `Page ${index + 1}`),
          summary: extractGeneratedPageSummary(html, ""),
        };
      })
      .filter(Boolean);
  };

  const normalizeGeneratedPageFlow = (value, contentPages) => {
    const fallback = buildGeneratedPageFlow(contentPages);
    if (!Array.isArray(value) || !value.length) {
      return fallback;
    }

    const usedLabels = new Set();

    return fallback.map((page, index) => {
      const raw = value[index];
      const fallbackLabel = cleanText(page?.label || `Page ${index + 1}`);
      const fallbackSummary = cleanText(page?.summary || "");

      if (isPlainObject(raw)) {
        let label = trimWords(cleanText(raw.label || raw.title || raw.page_label || raw.pageLabel || ""), 8);
        let summary = trimText(cleanText(raw.summary || raw.page_summary || raw.pageSummary || raw.description || ""), 150);
        if (!pageFlowLabelLooksStrong(label, index)) {
          label = fallbackLabel;
        }
        if (!pageFlowSummaryLooksStrong(summary, label)) {
          summary = fallbackSummary;
        }

        let fingerprint = normalizePageFlowLabelFingerprint(label);
        const fallbackFingerprint = normalizePageFlowLabelFingerprint(fallbackLabel);
        if ((!fingerprint || usedLabels.has(fingerprint)) && fallbackFingerprint && !usedLabels.has(fallbackFingerprint)) {
          label = fallbackLabel;
          fingerprint = fallbackFingerprint;
        }

        if (!fingerprint || usedLabels.has(fingerprint)) {
          const derivedLabel = derivePageFlowLabelFromSummary(summary, fallbackSummary || fallbackLabel);
          const derivedFingerprint = normalizePageFlowLabelFingerprint(derivedLabel);
          if (derivedFingerprint && !usedLabels.has(derivedFingerprint) && pageFlowLabelLooksStrong(derivedLabel, index)) {
            label = derivedLabel;
            fingerprint = derivedFingerprint;
          }
        }

        if (!pageFlowSummaryLooksStrong(summary, label)) {
          summary = fallbackSummary;
        }
        if (!summary) {
          summary = fallbackSummary;
        }
        if (fingerprint) {
          usedLabels.add(fingerprint);
        }

        return {
          index: page.index,
          label: label || fallbackLabel,
          summary: summary || fallbackSummary,
        };
      }

      if (typeof raw === "string") {
        let label = trimWords(cleanText(raw), 8) || fallbackLabel;
        let fingerprint = normalizePageFlowLabelFingerprint(label);
        if ((!fingerprint || usedLabels.has(fingerprint)) && fallbackLabel) {
          label = fallbackLabel;
          fingerprint = normalizePageFlowLabelFingerprint(label);
        }
        if (!fingerprint || usedLabels.has(fingerprint) || !pageFlowLabelLooksStrong(label, index)) {
          const derivedLabel = derivePageFlowLabelFromSummary(fallbackSummary, fallbackLabel);
          const derivedFingerprint = normalizePageFlowLabelFingerprint(derivedLabel);
          if (derivedFingerprint && !usedLabels.has(derivedFingerprint) && pageFlowLabelLooksStrong(derivedLabel, index)) {
            label = derivedLabel;
            fingerprint = derivedFingerprint;
          }
        }
        if (fingerprint) {
          usedLabels.add(fingerprint);
        }

        return {
          index: page.index,
          label: label || fallbackLabel,
          summary: fallbackSummary,
        };
      }

      const fallbackFingerprint = normalizePageFlowLabelFingerprint(fallbackLabel);
      if (fallbackFingerprint) {
        usedLabels.add(fallbackFingerprint);
      }
      return page;
    });
  };

  return {
    extractFirstHeading,
    extractGeneratedPageLabel,
    extractGeneratedPageSummary,
    buildGeneratedPageFlow,
    normalizeGeneratedPageFlow,
    derivePageFlowLabelFromSummary,
  };
}
