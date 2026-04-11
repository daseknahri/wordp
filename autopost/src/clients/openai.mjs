import { Buffer } from "node:buffer";
import { parseJsonResponse } from "./http.mjs";
import { log } from "../runtime/utils.mjs";

function cleanText(value) {
  return String(value ?? "")
    .replace(/\r/g, "")
    .replace(/[ \t]+/g, " ")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function cleanMultilineText(value) {
  return String(value ?? "")
    .replace(/\r/g, "")
    .replace(/[ \t]+\n/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === "[object Object]";
}

function maskSecretSuffix(value) {
  const text = cleanText(value);
  if (!text) {
    return "none";
  }

  return text.length <= 4 ? text : text.slice(-4);
}

function isDegenerateModelJsonText(value) {
  const normalized = cleanMultilineText(value);
  if (!normalized) {
    return true;
  }

  return ["[]", "[ ]", "null", "\"[]\"", "'[]'"].includes(normalized);
}

function extractOpenAiMessageText(message) {
  if (typeof message?.content === "string") {
    return message.content;
  }

  if (Array.isArray(message?.content)) {
    const joined = cleanMultilineText(
      message.content
        .map((part) => {
          if (typeof part === "string") {
            return part;
          }

          if (!isPlainObject(part)) {
            return "";
          }

          if (typeof part.text === "string") {
            return part.text;
          }

          if (isPlainObject(part.text) && typeof part.text.value === "string") {
            return part.text.value;
          }

          return cleanMultilineText(part.content || part.value || part.output_text || "");
        })
        .filter(Boolean)
        .join("\n"),
    );

    if (joined) {
      return joined;
    }
  }

  return cleanMultilineText(message?.text || message?.output_text || "");
}

function extractOpenAiResponseText(payload) {
  if (typeof payload?.output_text === "string" && cleanMultilineText(payload.output_text)) {
    return cleanMultilineText(payload.output_text);
  }

  if (Array.isArray(payload?.output)) {
    const joined = cleanMultilineText(
      payload.output
        .flatMap((item) => {
          if (!isPlainObject(item)) {
            return [];
          }

          if (Array.isArray(item.content)) {
            return item.content.map((part) => {
              if (!isPlainObject(part)) {
                return "";
              }

              if (typeof part.text === "string") {
                return part.text;
              }

              if (isPlainObject(part.text) && typeof part.text.value === "string") {
                return part.text.value;
              }

              if (typeof part.output_text === "string") {
                return part.output_text;
              }

              return cleanMultilineText(part.content || part.value || "");
            });
          }

          return [cleanMultilineText(item.text || item.output_text || "")];
        })
        .filter(Boolean)
        .join("\n"),
    );

    if (joined) {
      return joined;
    }
  }

  return "";
}

export async function openAiJsonRequest(settings, path, body) {
  log(
    `OpenAI request ${path} key_present=${settings.openaiApiKey ? "yes" : "no"} key_suffix=${maskSecretSuffix(settings.openaiApiKey)} base_url=${settings.openaiBaseUrl || "missing"}`,
  );

  const response = await fetch(`${settings.openaiBaseUrl}${path}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${settings.openaiApiKey}`,
    },
    body: JSON.stringify(body),
  });

  return parseJsonResponse(response, "OpenAI");
}

export function ensureOpenAiConfigured(settings) {
  if (!settings?.openaiApiKey) {
    throw new Error("OpenAI API key is missing. Add it in the plugin settings or container environment.");
  }
}

export function coerceParsedJsonValue(value, depth = 0) {
  if (depth > 4) {
    return value;
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if ((trimmed.startsWith("{") && trimmed.endsWith("}")) || (trimmed.startsWith("[") && trimmed.endsWith("]"))) {
      try {
        return coerceParsedJsonValue(JSON.parse(trimmed), depth + 1);
      } catch {
        return value;
      }
    }
  }

  if (Array.isArray(value) && value.length === 1) {
    return coerceParsedJsonValue(value[0], depth + 1);
  }

  return value;
}

export function parseJsonObject(text) {
  try {
    return coerceParsedJsonValue(JSON.parse(text));
  } catch {
    const firstBrace = text.indexOf("{");
    const lastBrace = text.lastIndexOf("}");
    if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
      throw new Error("The model response was not valid JSON.");
    }
    return coerceParsedJsonValue(JSON.parse(text.slice(firstBrace, lastBrace + 1)));
  }
}

export async function requestOpenAiResponses(settings, messages) {
  const payload = await openAiJsonRequest(settings, "/responses", {
    model: settings.openaiModel,
    input: messages.map((message) => ({
      role: message.role === "system" ? "developer" : message.role,
      content: [
        {
          type: "input_text",
          text: String(message.content || ""),
        },
      ],
    })),
    text: {
      format: {
        type: "json_object",
      },
    },
  });

  return extractOpenAiResponseText(payload);
}

export async function requestOpenAiChat(settings, messages) {
  let payload;

  try {
    payload = await openAiJsonRequest(settings, "/chat/completions", {
      model: settings.openaiModel,
      messages,
      response_format: { type: "json_object" },
    });
  } catch (error) {
    const message = String(error?.message || error || "");
    if (!/response_format|json_object|unsupported/i.test(message)) {
      throw error;
    }

    payload = await openAiJsonRequest(settings, "/chat/completions", {
      model: settings.openaiModel,
      messages,
    });
  }

  let content = extractOpenAiMessageText(payload?.choices?.[0]?.message);
  if (!isDegenerateModelJsonText(content)) {
    return content;
  }

  const fallbackMessages = [
    ...messages,
    {
      role: "system",
      content:
        "Your previous reply was invalid for this task. Reply with one JSON object only. Do not return an array, do not return [], do not return markdown, and do not wrap the object in quotes.",
    },
  ];

  content = await requestOpenAiResponses(settings, fallbackMessages);
  return content;
}

export async function generateImageBase64(settings, prompt, size) {
  const payload = await openAiJsonRequest(settings, "/images/generations", {
    model: settings.openaiImageModel,
    prompt,
    size,
  });

  const imageItem = payload?.data?.[0] || payload?.output?.[0] || null;
  if (imageItem?.b64_json) {
    return imageItem.b64_json;
  }

  if (imageItem?.url) {
    const response = await fetch(imageItem.url);
    if (!response.ok) {
      throw new Error(`Image download failed with status ${response.status}.`);
    }
    const binary = Buffer.from(await response.arrayBuffer());
    return binary.toString("base64");
  }

  throw new Error("OpenAI image generation did not return an image.");
}
