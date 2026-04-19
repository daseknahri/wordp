import { config } from "../runtime/config.mjs";

export async function wpRequest(path, options = {}) {
  const url = `${config.internalWordPressUrl}${path}`;
  const secretHeaderName = String(options.secretHeaderName || config.defaultWorkerSecretHeader || "x-kuchnia-worker-secret");
  const headers = {
    [secretHeaderName]: config.sharedSecret,
    ...(options.body ? { "Content-Type": "application/json" } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(url, {
    method: options.method || "GET",
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  return parseJsonResponse(response, "WordPress");
}

export async function formPostJson(url, params) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: params.toString(),
  });

  return parseJsonResponse(response, "Facebook");
}

export async function parseJsonResponse(response, sourceName) {
  const raw = await response.text();
  let payload = null;

  try {
    payload = raw ? JSON.parse(raw) : null;
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const message =
      payload?.message ||
      payload?.error?.message ||
      raw ||
      `${response.status} ${response.statusText}`;
    throw new Error(`${sourceName} request failed with status ${response.status}: ${message}`);
  }

  if (payload?.error?.message) {
    throw new Error(`${sourceName} error: ${payload.error.message}`);
  }

  return payload;
}
