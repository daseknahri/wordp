export function createWordPressPostingExecutor(deps) {
  const {
    log,
    publishBlogPost,
    toInt,
  } = deps;

  return async function publishBlogStep({
    job,
    state,
    featuredImage,
    facebookImage,
    jobLabel = "",
  }) {
    const blogPost = await publishBlogPost(
      job.id,
      job,
      state.generated,
      featuredImage?.id || 0,
      facebookImage?.id || featuredImage?.id || 0,
    );

    const nextState = {
      ...state,
      postId: toInt(blogPost.post_id),
      permalink: String(blogPost.permalink || ""),
      publication: {
        ...(state?.publication && typeof state.publication === "object" ? state.publication : {}),
        id: toInt(blogPost.post_id),
        permalink: String(blogPost.permalink || ""),
      },
    };

    log(`published WordPress article #${nextState.postId} for ${jobLabel}`);

    return {
      state: nextState,
      selectedPages: Array.isArray(state.selectedPages) ? state.selectedPages : [],
      partialFailureMessage: "",
    };
  };
}
