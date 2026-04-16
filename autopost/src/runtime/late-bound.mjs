export function createLateBoundMethodFacade(getTarget, errorMessage, methodMap) {
  const facade = {};

  for (const [alias, path] of Object.entries(methodMap)) {
    const segments = Array.isArray(path) ? path : [path];

    facade[alias] = (...args) => {
      let target = getTarget();
      if (!target) {
        throw new Error(errorMessage);
      }

      for (const segment of segments) {
        target = target?.[segment];
      }

      if (typeof target !== "function") {
        throw new Error(errorMessage);
      }

      return target(...args);
    };
  }

  return facade;
}
