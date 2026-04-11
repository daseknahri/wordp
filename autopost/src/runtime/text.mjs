export function createTextHelpers() {
  function cleanText(value) {
    return String(value || "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function cleanMultilineText(value) {
    return String(value || "")
      .replace(/\r/g, "")
      .split("\n")
      .map((line) => line.trim())
      .join("\n")
      .replace(/\n{3,}/g, "\n\n")
      .trim();
  }

  function trimText(value, maxLength) {
    const text = cleanText(value);
    if (!text || text.length <= maxLength) {
      return text;
    }

    return `${text.slice(0, maxLength - 3).trim()}...`;
  }

  function countWords(value) {
    return cleanText(value).split(/\s+/).filter(Boolean).length;
  }

  function countLines(value) {
    return cleanMultilineText(value)
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean).length;
  }

  function trimWords(value, maxWords) {
    const words = cleanText(value).split(/\s+/).filter(Boolean);
    if (!words.length || words.length <= maxWords) {
      return words.join(" ");
    }

    return words.slice(0, maxWords).join(" ");
  }

  function sentenceCase(value) {
    const text = cleanText(value);
    if (!text) {
      return "";
    }

    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function firstSentence(value, maxLength = 160) {
    const text = cleanText(value);
    if (!text) {
      return "";
    }

    const sentence = text.split(/(?<=[.!?])\s+/)[0] || text;
    return trimText(sentence, maxLength);
  }

  function normalizeSlug(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/&/g, " and ")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .slice(0, 80) || `post-${Date.now()}`;
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  return {
    cleanMultilineText,
    cleanText,
    countLines,
    countWords,
    escapeHtml,
    firstSentence,
    normalizeSlug,
    sentenceCase,
    trimText,
    trimWords,
  };
}
