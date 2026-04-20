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

  function isPlainObject(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  function normalizeWorkerCallbackPayload(payload = {}) {
    const source = isPlainObject(payload) ? payload : {};
    const publication = isPlainObject(source.publication) ? source.publication : {};
    const deliveries = isPlainObject(source.deliveries) ? source.deliveries : {};
    const facebook = isPlainObject(deliveries.facebook) ? deliveries.facebook : {};
    const facebookGroups = isPlainObject(deliveries.facebook_groups) ? deliveries.facebook_groups : {};

    return {
      ...source,
      publication: {
        ...publication,
        id: toInt(publication.id ?? publication.postId ?? source.post_id ?? source.postId ?? 0),
        permalink: String(publication.permalink || source.permalink || ""),
      },
      deliveries: {
        ...deliveries,
        facebook: {
          ...facebook,
          postId: String(facebook.postId || source.facebook_post_id || source.facebookPostId || ""),
          commentId: String(facebook.commentId || source.facebook_comment_id || source.facebookCommentId || ""),
          caption: String(facebook.caption || source.facebook_caption || source.facebookCaption || ""),
        },
        facebook_groups: {
          ...facebookGroups,
          draft: String(
            facebookGroups.draft
            || facebookGroups.shareKit
            || source.group_share_kit
            || source.groupShareKit
            || "",
          ),
        },
      },
      facebook_post_id: String(facebook.postId || source.facebook_post_id || source.facebookPostId || ""),
      facebook_comment_id: String(facebook.commentId || source.facebook_comment_id || source.facebookCommentId || ""),
      facebook_caption: String(facebook.caption || source.facebook_caption || source.facebookCaption || ""),
      group_share_kit: String(
        facebookGroups.draft
        || facebookGroups.shareKit
        || source.group_share_kit
        || source.groupShareKit
        || ""
      ),
    };
  }

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
        deliveries: {
          facebook: {
            caption: facebookCaption,
          },
          facebook_groups: {
            draft: groupShareKit,
          },
        },
        facebook_caption: facebookCaption,
        group_share_kit: groupShareKit,
        generated_payload: generated,
      },
    });
  }

  async function completeJob(jobId, payload, job = null) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    const normalizedPayload = normalizeWorkerCallbackPayload(payload);
    await wpRequest(buildPlatformRestPath(platformPolicy, "complete", { id: jobId }), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
      body: normalizedPayload,
    });
  }

  async function safeFailJob(jobId, payload, job = null) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    const normalizedPayload = normalizeWorkerCallbackPayload(payload);
    try {
      await wpRequest(buildPlatformRestPath(platformPolicy, "fail", { id: jobId }), {
        method: "POST",
        secretHeaderName: platformPolicy.auth.secretHeader,
        body: normalizedPayload,
      });
    } catch (error) {
      log(`unable to report failure for job #${jobId}: ${formatError(error)}`);
    }
  }

  async function updateJobProgress(jobId, payload, job = null) {
    const platformPolicy = resolvePlatformPolicy({}, job, config);
    const normalizedPayload = normalizeWorkerCallbackPayload(payload);
    return wpRequest(buildPlatformRestPath(platformPolicy, "progress", { id: jobId }), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
      body: normalizedPayload,
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
