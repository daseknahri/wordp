export function createPostingTargetResolver(deps) {
  const {
    resolveFacebookTargets,
  } = deps;

  function resolvePostingTargetCounts(job, settings) {
    const facebookTargets = resolveFacebookTargets(job, settings);

    return {
      facebook: Number(facebookTargets?.count || 0),
    };
  }

  return {
    resolvePostingTargetCounts,
  };
}
