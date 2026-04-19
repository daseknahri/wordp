const DEFAULT_REST_NAMESPACE = "kuchnia-twist/v1";
const DEFAULT_ROUTE_TEMPLATES = {
  claim: "jobs/claim",
  media: "jobs/{id}/media",
  publish_blog: "jobs/{id}/publish-blog",
  progress: "jobs/{id}/progress",
  complete: "jobs/{id}/complete",
  fail: "jobs/{id}/fail",
  heartbeat: "worker/heartbeat",
};

function safeObject(value) {
  return value && typeof value === "object" && !Array.isArray(value) ? value : {};
}

function trimPath(value, fallback = "") {
  const source = String(value || fallback || "");
  return source.replace(/^\/+|\/+$/g, "");
}

function routeTemplate(routes, key, fallbackKey = key) {
  return trimPath(routes[key] || routes[fallbackKey] || DEFAULT_ROUTE_TEMPLATES[fallbackKey], DEFAULT_ROUTE_TEMPLATES[fallbackKey]);
}

function interpolateRoute(template, params = {}) {
  return String(template || "").replace(/\{(\w+)\}/g, (_, key) => encodeURIComponent(String(params[key] ?? "")));
}

export function resolvePlatformPolicy(settings = {}, job = null, config = {}) {
  const normalizedSettings = safeObject(settings);
  const contentMachine = safeObject(normalizedSettings.contentMachine || normalizedSettings.content_machine);
  const jobRequest = safeObject(job?.request_payload || job?.requestPayload);
  const jobMachine = safeObject(jobRequest.content_machine || jobRequest.contentMachine);
  const provided = safeObject(
    contentMachine.platformPolicy ||
      contentMachine.platform_policy ||
      jobMachine.platformPolicy ||
      jobMachine.platform_policy,
  );
  const rest = safeObject(provided.rest);
  const auth = safeObject(provided.auth);
  const routes = safeObject(rest.routes || provided.routes);
  const delivery = safeObject(provided.delivery);

  return {
    restNamespace: trimPath(
      rest.namespace ||
        provided.rest_namespace ||
        config.defaultRestNamespace ||
        DEFAULT_REST_NAMESPACE,
      DEFAULT_REST_NAMESPACE,
    ),
    routes: {
      claim: routeTemplate(routes, "claim"),
      media: routeTemplate(routes, "media"),
      publish_blog: routeTemplate(routes, "publish_blog"),
      progress: routeTemplate(routes, "progress"),
      complete: routeTemplate(routes, "complete"),
      fail: routeTemplate(routes, "fail"),
      heartbeat: routeTemplate(routes, "heartbeat"),
    },
    auth: {
      secretHeader: String(
        auth.secret_header ||
          auth.secretHeader ||
          config.defaultWorkerSecretHeader ||
          "x-kuchnia-worker-secret",
      ),
    },
    delivery: {
      utmSource: String(
        delivery.utm_source ||
          normalizedSettings.utmSource ||
          normalizedSettings.utm_source ||
          config.fallbackUtmSource ||
          "facebook",
      ),
      utmCampaignPrefix: String(
        delivery.utm_campaign_prefix ||
          normalizedSettings.utmCampaignPrefix ||
          normalizedSettings.utm_campaign_prefix ||
          config.fallbackUtmCampaignPrefix ||
          "publication",
      ),
    },
  };
}

export function buildPlatformRestPath(platformPolicy, routeKey, params = {}) {
  const policy = safeObject(platformPolicy);
  const namespace = trimPath(policy.restNamespace || DEFAULT_REST_NAMESPACE, DEFAULT_REST_NAMESPACE);
  const routes = safeObject(policy.routes);
  const template = routeTemplate(routes, routeKey, routeKey);
  return `/wp-json/${namespace}/${interpolateRoute(template, params)}`;
}
