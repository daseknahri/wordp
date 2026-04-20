const DEFAULT_PRIMARY_ACTION = "publish_blog";
const DEFAULT_PRIMARY_STAGE = "publishing_blog";
const DEFAULT_PRIMARY_EXECUTOR = "wordpress_publish";
const DEFAULT_CHANNEL_ORDER = ["facebook"];
const DEFAULT_CHANNELS = {
  facebook: {
    enabled: true,
    executor: "facebook_distribution",
    stage: "publishing_facebook",
    requiresTargets: true,
  },
  facebook_groups: {
    enabled: false,
    executor: "facebook_groups_draft",
    stage: "preparing_facebook_groups",
    requiresTargets: false,
  },
  pinterest: {
    enabled: false,
    executor: "pinterest_draft",
    stage: "preparing_pinterest",
    requiresTargets: false,
  },
};

function safeObject(value) {
  return value && typeof value === "object" && !Array.isArray(value) ? value : {};
}

function cleanString(value, fallback = "") {
  const text = String(value ?? "").trim();
  return text || fallback;
}

function normalizeStringList(value, fallback = []) {
  const list = Array.isArray(value) ? value : fallback;
  const seen = new Set();

  return list
    .map((item) => cleanString(item))
    .filter((item) => {
      if (!item || seen.has(item)) {
        return false;
      }

      seen.add(item);
      return true;
    });
}

function resolveJobMachine(job = null) {
  const requestPayload = safeObject(job?.request_payload || job?.requestPayload);
  return safeObject(requestPayload.content_machine || requestPayload.contentMachine);
}

function normalizeChannelPolicy(key, value) {
  const defaults = safeObject(DEFAULT_CHANNELS[key]);
  const provided = safeObject(value);

  return {
    enabled: Object.prototype.hasOwnProperty.call(provided, "enabled")
      ? Boolean(provided.enabled)
      : Boolean(defaults.enabled),
    executor: cleanString(provided.executor, cleanString(defaults.executor, key)),
    stage: cleanString(provided.stage, cleanString(defaults.stage, `publishing_${key}`)),
    requiresTargets: Object.prototype.hasOwnProperty.call(provided, "requiresTargets")
      ? Boolean(provided.requiresTargets)
      : Object.prototype.hasOwnProperty.call(provided, "requires_targets")
        ? Boolean(provided.requires_targets)
        : Boolean(defaults.requiresTargets),
  };
}

export function resolvePostingPolicy(settings = {}, job = null) {
  const normalizedSettings = safeObject(settings);
  const contentMachine = safeObject(normalizedSettings.contentMachine || normalizedSettings.content_machine);
  const jobMachine = resolveJobMachine(job);
  const provided = safeObject(
    contentMachine.postingPolicy ||
      contentMachine.posting_policy ||
      jobMachine.postingPolicy ||
      jobMachine.posting_policy,
  );
  const primary = safeObject(provided.primary);
  const channels = safeObject(provided.channels);
  const channelOrder = normalizeStringList(
    provided.channel_order || provided.channelOrder,
    DEFAULT_CHANNEL_ORDER,
  );
  const resolvedChannels = {};
  const knownChannelKeys = Array.from(
    new Set([
      ...Object.keys(DEFAULT_CHANNELS),
      ...Object.keys(channels),
      ...channelOrder,
    ]),
  );

  for (const channelKey of knownChannelKeys) {
    resolvedChannels[channelKey] = normalizeChannelPolicy(channelKey, channels[channelKey]);
  }

  return {
    primary: {
      action: cleanString(primary.action || provided.primary_action, DEFAULT_PRIMARY_ACTION),
      executor: cleanString(primary.executor || provided.primary_executor, DEFAULT_PRIMARY_EXECUTOR),
      stage: cleanString(primary.stage || provided.primary_stage, DEFAULT_PRIMARY_STAGE),
    },
    channelOrder: channelOrder.length ? channelOrder : DEFAULT_CHANNEL_ORDER.slice(),
    channels: resolvedChannels,
  };
}

export function buildPostingPlan({
  settings = {},
  job = null,
  retryTarget = "full",
  hasPrimaryPublication = false,
  targetCounts = {},
} = {}) {
  const policy = resolvePostingPolicy(settings, job);
  const steps = [];
  const shouldPublishPrimary = !hasPrimaryPublication || retryTarget === "publish" || retryTarget === "full";

  if (shouldPublishPrimary) {
    steps.push({
      key: policy.primary.action,
      executorKey: cleanString(policy.primary.executor, policy.primary.action),
      kind: "primary",
      stage: policy.primary.stage,
    });
  }

  for (const channelKey of policy.channelOrder) {
    const channel = safeObject(policy.channels[channelKey]);
    if (!channel.enabled) {
      continue;
    }

    const targetCount = Math.max(0, Number(targetCounts?.[channelKey] || 0));
    if (channel.requiresTargets && targetCount < 1) {
      continue;
    }

    steps.push({
      key: channelKey,
      executorKey: cleanString(channel.executor, channelKey),
      kind: "channel",
      stage: cleanString(channel.stage, `publishing_${channelKey}`),
    });
  }

  return {
    policy,
    steps,
  };
}
