export function createRuntimeCore(deps) {
  const { config, sleep, IDLE_MS } = deps;

  function isPlainObject(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  async function idleLoop() {
    do {
      await sleep(IDLE_MS);
    } while (true);
  }

  function hasWorkerConfig() {
    return Boolean(config?.internalWordPressUrl && config?.sharedSecret);
  }

  return {
    hasWorkerConfig,
    idleLoop,
    isPlainObject,
  };
}
