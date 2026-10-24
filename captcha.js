(function () {
  let apiPromise;
  const verificationError = "No se pudo completar la verificación. Inténtalo de nuevo o contacta con info@goodmeals.es.";

  function loadApi() {
    if (apiPromise) return apiPromise;
    if (window.turnstile) return Promise.resolve(window.turnstile);

    apiPromise = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      const fail = () => {
        window.clearTimeout(timer);
        script.remove();
        reject(new Error(verificationError));
      };
      const timer = window.setTimeout(fail, 15000);
      script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
      script.async = true;
      script.onerror = fail;
      script.onload = () => {
        if (!window.turnstile) return fail();
        // The async script has finished executing. Calling ready() here throws
        // because Turnstile only supports ready() with synchronous loading.
        window.clearTimeout(timer);
        resolve(window.turnstile);
      };
      document.head.appendChild(script);
    }).catch((error) => {
      apiPromise = undefined;
      throw error;
    });

    return apiPromise;
  }

  window.GOOD_MEALS_CAPTCHA = {
    async getToken(form, endpoint) {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ action: "captcha-config" }),
        cache: "no-store",
        signal: AbortSignal.timeout(10000)
      }).catch(() => { throw new Error(verificationError); });
      if (response.status === 405) {
        console.error("Good Meals: the contact endpoint rejected the CAPTCHA configuration POST request. Check that api/contact.php is running the current version.");
        throw new Error(verificationError);
      }
      const config = await response.json().catch(() => { throw new Error(verificationError); });
      if (!response.ok || !config.sitekey) {
        throw new Error(config.message || verificationError);
      }

      const api = await loadApi();
      const container = form.querySelector("[data-turnstile]");
      let widgetId;
      let timer;

      try {
        return await new Promise((resolve, reject) => {
          const fail = () => reject(new Error(verificationError));
          timer = window.setTimeout(fail, 25000);
          widgetId = api.render(container, {
            sitekey: config.sitekey,
            action: "contact",
            language: "es",
            execution: "execute",
            "response-field": false,
            retry: "never",
            callback: resolve,
            "error-callback": () => { fail(); return true; },
            "expired-callback": fail,
            "timeout-callback": fail
          });
          api.execute(widgetId);
        });
      } finally {
        window.clearTimeout(timer);
        if (widgetId !== undefined) api.remove(widgetId);
      }
    }
  };
})();
