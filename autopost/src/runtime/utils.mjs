export function toBool(value, fallback) {
  if (value == null || value === "") {
    return fallback;
  }

  return ["1", "true", "yes", "on"].includes(String(value).toLowerCase());
}

export function toNumber(value, fallback) {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

export function toInt(value) {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : 0;
}

export function trimTrailingSlash(value) {
  return String(value || "").replace(/\/+$/, "");
}

export function formatError(error) {
  if (error instanceof Error) {
    return error.message;
  }

  return String(error);
}

export function log(message) {
  console.log(`[autopost] ${new Date().toISOString()} ${message}`);
}
