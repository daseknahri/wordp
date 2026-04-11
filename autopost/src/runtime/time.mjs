export function createTimeHelpers(deps) {
  const { cleanText } = deps;

  function isFutureUtcTimestamp(value) {
    const normalized = cleanText(value || "");
    if (!normalized) {
      return false;
    }

    const timestamp = Date.parse(normalized.replace(" ", "T") + "Z");
    if (Number.isNaN(timestamp)) {
      return false;
    }

    return timestamp > Date.now();
  }

  return { isFutureUtcTimestamp };
}
