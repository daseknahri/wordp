export function createPostingMachine(deps) {
  const {
    assertFacebookConfigured,
    completeJob,
    finalizeFacebookPhaseState,
    formatError,
    log,
    publishBlogPost,
    publishFacebookDistribution,
    resolveSelectedFacebookPages,
    safeFailJob,
    seedLegacyFacebookDistribution,
    summarizeFacebookFailures,
    toInt,
    updateJobProgress,
  } = deps;

  async function runPublishingFlow({
    job,
    settings,
    generated,
    distribution,
    facebookCaption,
    groupShareKit,
    socialPack,
    featuredImage,
    facebookImage,
    retryTarget = "full",
    postId = 0,
    permalink = "",
    facebookPostId = "",
    facebookCommentId = "",
    jobLabel = "",
    facebookPostTeaserCta = "",
  }) {
    let stage = postId && retryTarget !== "publish" && retryTarget !== "full"
      ? "publishing_facebook"
      : "publishing_blog";
    let nextDistribution = distribution;
    let nextGenerated = generated;
    let nextPostId = toInt(postId);
    let nextPermalink = String(permalink || "");
    let nextFacebookPostId = String(facebookPostId || "");
    let nextFacebookCommentId = String(facebookCommentId || "");
    let nextFacebookCaption = String(facebookCaption || "");
    const nextGroupShareKit = String(groupShareKit || "");

    try {
      if (!nextPostId || retryTarget === "publish" || retryTarget === "full") {
        stage = "publishing_blog";
        await updateJobProgress(job.id, {
          status: "publishing_blog",
          stage,
        }, job);

        const blogPost = await publishBlogPost(
          job.id,
          job,
          nextGenerated,
          featuredImage?.id || 0,
          facebookImage?.id || featuredImage?.id || 0,
        );
        nextPostId = toInt(blogPost.post_id);
        nextPermalink = String(blogPost.permalink || "");

        log(`published WordPress article #${nextPostId} for ${jobLabel}`);
      }

      stage = "publishing_facebook";
      await updateJobProgress(job.id, {
        status: "publishing_facebook",
        stage,
      }, job);

      const selectedPages = resolveSelectedFacebookPages(job, settings);
      assertFacebookConfigured(selectedPages);
      nextDistribution = seedLegacyFacebookDistribution(nextDistribution, selectedPages, job, nextFacebookCaption);

      const facebookResult = await publishFacebookDistribution({
        settings,
        generated: nextGenerated,
        permalink: nextPermalink,
        pages: selectedPages,
        socialPack,
        distribution: nextDistribution,
        imageUrl: facebookImage?.url || featuredImage?.url || "",
        contentType: job.content_type || "recipe",
        retryTarget,
      });

      nextDistribution = facebookResult.distribution;
      ({
        facebookCaption: nextFacebookCaption,
        facebookCommentId: nextFacebookCommentId,
        facebookPostId: nextFacebookPostId,
        generated: nextGenerated,
      } = finalizeFacebookPhaseState({
        job,
        settings,
        generated: nextGenerated,
        distribution: nextDistribution,
        socialPack,
        facebookPostTeaserCta,
      }));

      if (facebookResult.failedPages.length > 0) {
        const message = summarizeFacebookFailures(facebookResult.failedPages);
        await safeFailJob(job.id, {
          status: "partial_failure",
          stage,
          facebook_post_id: nextFacebookPostId,
          facebook_comment_id: nextFacebookCommentId,
          facebook_caption: nextFacebookCaption,
          group_share_kit: nextGroupShareKit,
          generated_payload: nextGenerated,
          error_message: message,
        }, job);

        log(`${jobLabel} partially failed across Facebook pages: ${message}`);
        return {
          ok: false,
          terminal: true,
          stage,
          status: "partial_failure",
          error: message,
          distribution: nextDistribution,
          generated: nextGenerated,
          facebookCaption: nextFacebookCaption,
          groupShareKit: nextGroupShareKit,
          facebookPostId: nextFacebookPostId,
          facebookCommentId: nextFacebookCommentId,
          postId: nextPostId,
          permalink: nextPermalink,
          selectedPages,
        };
      }

      await completeJob(job.id, {
        status: "completed",
        facebook_post_id: nextFacebookPostId,
        facebook_comment_id: nextFacebookCommentId,
        facebook_caption: nextFacebookCaption,
        group_share_kit: nextGroupShareKit,
        generated_payload: nextGenerated,
      }, job);

      log(`completed ${jobLabel}; distributed to ${selectedPages.length} Facebook page(s)`);
      return {
        ok: true,
        terminal: true,
        stage: "completed",
        status: "completed",
        error: "",
        distribution: nextDistribution,
        generated: nextGenerated,
        facebookCaption: nextFacebookCaption,
        groupShareKit: nextGroupShareKit,
        facebookPostId: nextFacebookPostId,
        facebookCommentId: nextFacebookCommentId,
        postId: nextPostId,
        permalink: nextPermalink,
        selectedPages,
      };
    } catch (error) {
      const message = formatError(error);
      const status = nextPostId ? "partial_failure" : "failed";

      await safeFailJob(job.id, {
        status,
        stage,
        facebook_post_id: nextFacebookPostId,
        facebook_comment_id: nextFacebookCommentId,
        facebook_caption: nextFacebookCaption,
        group_share_kit: nextGroupShareKit,
        generated_payload: nextGenerated,
        error_message: message,
      }, job);

      log(`${jobLabel} failed: ${message}`);
      return {
        ok: false,
        terminal: true,
        stage,
        status,
        error: message,
        distribution: nextDistribution,
        generated: nextGenerated,
        facebookCaption: nextFacebookCaption,
        groupShareKit: nextGroupShareKit,
        facebookPostId: nextFacebookPostId,
        facebookCommentId: nextFacebookCommentId,
        postId: nextPostId,
        permalink: nextPermalink,
        selectedPages: [],
      };
    }
  }

  return {
    runPublishingFlow,
  };
}
