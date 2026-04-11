export function createWordPressJobClient(deps) {
  const {
    WORKER_VERSION,
    config,
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    formatError,
    log,
    resolveCanonicalContentPackage,
    resolveFacebookChannelAdapter,
    toInt,
    wpRequest,
  } = deps;

  async function claimNextJob() {
    const claimed = await wpRequest("/wp-json/kuchnia-twist/v1/jobs/claim", {
      method: "POST",
    });

    if (!claimed?.job) {
      return null;
    }

    return claimed;
  }

  async function publishBlogPost(jobId, job, generated, featuredImageId, facebookImageId) {
    const contentPackage = resolveCanonicalContentPackage(generated, job);
    const facebookCaption = deriveLegacyFacebookCaptionMirror(resolveFacebookChannelAdapter(generated, job), generated.facebook_caption || "");
    const groupShareKit = deriveLegacyGroupShareKitMirror(generated);
    return wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/publish-blog`, {
      method: "POST",
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

  async function completeJob(jobId, payload) {
    await wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/complete`, {
      method: "POST",
      body: payload,
    });
  }

  async function safeFailJob(jobId, payload) {
    try {
      await wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/fail`, {
        method: "POST",
        body: payload,
      });
    } catch (error) {
      log(`unable to report failure for job #${jobId}: ${formatError(error)}`);
    }
  }

  async function updateJobProgress(jobId, payload) {
    return wpRequest(`/wp-json/kuchnia-twist/v1/jobs/${jobId}/progress`, {
      method: "POST",
      body: payload,
    });
  }

  async function sendHeartbeatBestEffort(overrides = {}) {
    if (!config.internalWordPressUrl || !config.sharedSecret) {
      return;
    }

    try {
      await wpRequest("/wp-json/kuchnia-twist/v1/worker/heartbeat", {
        method: "POST",
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
