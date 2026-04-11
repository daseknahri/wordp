export function createWorkerRunner(deps) {
  const {
    config,
    sleep,
    SECOND_MS,
    log,
    formatError,
    sendHeartbeatBestEffort,
    idleLoop,
    hasWorkerConfig,
    idleHeartbeatState,
    processNextJob,
  } = deps;

  async function run() {
    log("worker booting");
    await sendHeartbeatBestEffort({ last_loop_result: "booting" });

    if (config.startupDelaySeconds > 0) {
      log(`waiting ${config.startupDelaySeconds}s before first check`);
      await sleep(config.startupDelaySeconds * SECOND_MS);
      await sendHeartbeatBestEffort({ last_loop_result: "startup_delay_complete" });
    }

    if (!config.enabled) {
      log("AUTOPOST_ENABLED is off; worker will stay idle");
      await sendHeartbeatBestEffort({ last_loop_result: "disabled" });
      await idleLoop();
      return;
    }

    if (!hasWorkerConfig()) {
      const message = "missing worker configuration; set AUTOPOST_WORDPRESS_INTERNAL_URL and CONTENT_PIPELINE_SHARED_SECRET";
      log(message);
      await sendHeartbeatBestEffort({
        config_ok: false,
        last_loop_result: "invalid_config",
        last_error: message,
      });
      await idleLoop();
      return;
    }

    do {
      let loopState = idleHeartbeatState();

      try {
        loopState = await processNextJob();
      } catch (error) {
        const message = formatError(error);
        log(`run failed: ${message}`);
        loopState = {
          foundJob: false,
          last_loop_result: "loop_error",
          last_job_id: 0,
          last_job_status: "",
          last_error: message,
        };
      }

      await sendHeartbeatBestEffort(loopState);

      if (config.runOnce) {
        if (loopState.foundJob) {
          log("AUTOPOST_RUN_ONCE finished; worker will stay idle until the next deploy");
        } else {
          log("AUTOPOST_RUN_ONCE found no due jobs; worker will stay idle until the next deploy");
        }
        await sendHeartbeatBestEffort({
          last_loop_result: loopState.foundJob ? "run_once_complete" : "run_once_no_job",
          last_job_id: loopState.last_job_id || 0,
          last_job_status: loopState.last_job_status || "",
          last_error: loopState.last_error || "",
        });
        await idleLoop();
        return;
      }

      await sleep(config.pollSeconds * SECOND_MS);
    } while (true);
  }

  async function handleFatal(error) {
    const message = formatError(error);
    await sendHeartbeatBestEffort({
      last_loop_result: "fatal_error",
      last_error: message,
    });
    log(`fatal error: ${message}`);
    return message;
  }

  return { handleFatal, run };
}
