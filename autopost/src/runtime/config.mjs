import { toBool, toNumber, trimTrailingSlash } from "./utils.mjs";

export const SECOND_MS = 1000;
export const IDLE_MS = 60 * 60 * SECOND_MS;
export const WORKER_VERSION = "1.8.9";
export const PROMPT_VERSION = "typed-content-v10";
export const CONTENT_PACKAGE_CONTRACT_VERSION = "content-package-v1";
export const CHANNEL_ADAPTER_CONTRACT_VERSION = "channel-adapters-v1";
export const QUALITY_SCORE_THRESHOLD = 75;

export const config = {
  enabled: toBool(process.env.AUTOPOST_ENABLED, false),
  runOnce: toBool(process.env.AUTOPOST_RUN_ONCE, false),
  startupDelaySeconds: toNumber(process.env.AUTOPOST_STARTUP_DELAY_SECONDS, 30),
  pollSeconds: toNumber(process.env.AUTOPOST_POLL_SECONDS, 15),
  internalWordPressUrl: trimTrailingSlash(process.env.AUTOPOST_WORDPRESS_INTERNAL_URL || "http://wordpress"),
  publicWordPressUrl: trimTrailingSlash(process.env.WORDPRESS_URL || process.env.AUTOPOST_WORDPRESS_URL || ""),
  sharedSecret: process.env.CONTENT_PIPELINE_SHARED_SECRET || "",
  defaultRestNamespace: String(process.env.AUTOPOST_REST_NAMESPACE || "kuchnia-twist/v1").replace(/^\/+|\/+$/g, ""),
  defaultWorkerSecretHeader: process.env.AUTOPOST_WORKER_SECRET_HEADER || "x-kuchnia-worker-secret",
  fallbackSiteName: "Kitchen Journal",
  fallbackBrandVoice: "Warm, useful, story-aware, and editorial without sounding stiff.",
  fallbackUtmSource: process.env.AUTOPOST_UTM_SOURCE || "facebook",
  fallbackUtmCampaignPrefix: process.env.AUTOPOST_UTM_CAMPAIGN_PREFIX || "publication",
  minWords: toNumber(process.env.AUTOPOST_MIN_WORDS, 1000),
  maxWords: toNumber(process.env.AUTOPOST_MAX_WORDS, 1500),
  fallbackOpenAiKey: process.env.OPENAI_API_KEY || "",
  fallbackOpenAiBaseUrl: trimTrailingSlash(process.env.OPENAI_BASE_URL || "https://api.openai.com/v1"),
  fallbackTextModel: process.env.OPENAI_MODEL || "gpt-5-mini",
  strictContractMode: toBool(process.env.AUTOPOST_STRICT_CONTRACT_MODE, false),
};
