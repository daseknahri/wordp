import { buildPlatformRestPath, resolvePlatformPolicy } from "../runtime/platform-policy.mjs";

export function createWordPressJobClient(deps) {
  const {
    WORKER_VERSION,
    config,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    formatError,
    log,
    resolveCanonicalContentPackage,
    resolveFacebookDefaultCtas,
    resolveFacebookChannelAdapter,
    toInt,
    wpRequest,
  } = deps;

  async function claimNextJob() {
    const platformPolicy = resolvePlatformPolicy({}, null, config);
    const claimed = await wpRequest(buildPlatformRestPath(platformPolicy, "claim"), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
    });

    if (!claimed?.job) {
      return null;
    }

    return claimed;
  }

  async function publishBlogPost(jobId, job, generated, featuredImageId, facebookImageId) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    const contentPackage = resolveCanonicalContentPackage(generated, job);
    const { facebookPostTeaserCta } = resolveFacebookDefaultCtas(generated, job);
    const facebookCaption = deriveLegacyFacebookCaptionMirror(
      resolveFacebookChannelAdapter(generated, job),
      generated.facebook_caption || "",
      facebookPostTeaserCta,
    );
    const groupShareKit = deriveLegacyGroupShareKitMirror(generated);
    return wpRequest(buildPlatformRestPath(platformPolicy, "publish_blog", { id: jobId }), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
      body: {
        content_type: contentPackage.content_type || job.content_type,
        title: contentPackage.title,
        slug: contentPackage.slug,
        excerpt: contentPackage.excerpt,
        seo_description: contentPackage.seo_description,
        content_html: contentPackage.content_html,
        featured_image_id: featuredImageId,
        facebook_image_id: facebookImageId,
        facebook_caption: facebookCaption,
        group_share_kit: groupShareKit,
        generated_payload: generated,
      },
    });
  }

  async function completeJob(jobId, payload, job = null) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    await wpRequest(buildPlatformRestPath(platformPolicy, "complete", { id: jobId }), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
      body: payload,
    });
  }

  async function safeFailJob(jobId, payload, job = null) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    try {
      await wpRequest(buildPlatformRestPath(platformPolicy, "fail", { id: jobId }), {
        method: "POST",
        secretHeaderName: platformPolicy.auth.secretHeader,
        body: payload,
      });
    } catch (error) {
      log(`unable to report failure for job #${jobId}: ${formatError(error)}`);
    }
  }

  async function updateJobProgress(jobId, payload, job = null) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    return wpRequest(buildPlatformRestPath(platformPolicy, "progress", { id: jobId }), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
      body: payload,
    });
  }

  async function sendHeartbeatBestEffort(overrides = {}) {
    if (!config.internalWordPressUrl || !config.sharedSecret) {
      return;
    }

    try {
      const platformPolicy = resolvePlatformPolicy({}, null, config);
      await wpRequest(buildPlatformRestPath(platformPolicy, "heartbeat"), {
        method: "POST",
        secretHeaderName: platformPolicy.auth.secretHeader,
        body: {
          worker_version: WORKER_VERSION,
          enabled: config.enabled,
          run_once: config.runOnce,
          poll_seconds: config.pollSeconds,
          startup_delay_seconds: config.startupDelaySeconds,
          config_ok: Boolean(config.internalWordPressUrl && config.sharedSecret),
          last_seen_at: new Date().toISOString(),
          last_loop_result: "",
          last_job_id: 0,
          last_job_status: "",
          last_error: "",
          ...overrides,
        },
      });
    } catch (error) {
      log(`heartbeat failed: ${formatError(error)}`);
    }
  }

  function idleHeartbeatState() {
    return {
      foundJob: false,
      last_loop_result: "no_due_jobs",
      last_job_id: 0,
      last_job_status: "",
      last_error: "",
    };
  }

  function mergeHeartbeatState(current, next) {
    return {
      ...current,
      ...next,
      last_job_id: toInt(next.last_job_id || current.last_job_id || 0),
      last_job_status: String(next.last_job_status || current.last_job_status || ""),
      last_error: String(next.last_error || current.last_error || ""),
    };
  }

  return {
    claimNextJob,
    completeJob,
    idleHeartbeatState,
    mergeHeartbeatState,
    publishBlogPost,
    safeFailJob,
    sendHeartbeatBestEffort,
    updateJobProgress,
  };
}
