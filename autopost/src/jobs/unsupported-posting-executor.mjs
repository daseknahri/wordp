export function createUnsupportedPostingExecutor({
  executorKey = "",
  label = "",
} = {}) {
  const resolvedExecutorKey = String(executorKey || "").trim() || "unknown_executor";
  const resolvedLabel = String(label || "").trim() || resolvedExecutorKey;

  return async function runUnsupportedPostingExecutor() {
    throw new Error(`${resolvedLabel} is not implemented for this site adapter yet (${resolvedExecutorKey}).`);
  };
}
