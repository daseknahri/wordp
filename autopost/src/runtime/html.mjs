export function createHtmlHelpers(deps) {
  const {
    cleanText,
    escapeHtml,
  } = deps;

  function normalizeHtml(value) {
    const html = String(value || "").trim();
    if (!html) {
      return "";
    }

    if (html.includes("<")) {
      return html;
    }

    return html
      .split(/\n{2,}/)
      .map((paragraph) => paragraph.trim())
      .filter(Boolean)
      .map((paragraph) => `<p>${escapeHtml(paragraph)}</p>`)
      .join("\n");
  }

  function ensureStringArray(value) {
    if (!Array.isArray(value)) {
      return [];
    }

    return value
      .map((item) => cleanText(item))
      .filter(Boolean);
  }

  return {
    ensureStringArray,
    normalizeHtml,
  };
}
