(function () {
  let apiPromise;
  const verificationError = "No se pudo completar la verificación. Inténtalo de nuevo o contacta con info@goodmeals.es.";

  function loadApi() {
    if (window.turnstile) return Promise.resolve(window.turnstile);
    if (apiPromise) return apiPromise;

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
        window.turnstile.ready(() => {
          window.clearTimeout(timer);
          resolve(window.turnstile);
        });
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
        headers: { Accept: "application/json" },
        cache: "no-store",
        signal: AbortSignal.timeout(10000)
      }).catch(() => { throw new Error(verificationError); });
      if (response.status === 405) {
        console.error("Good Meals: the contact endpoint rejected the CAPTCHA configuration request. Upload the matching api/contact.php together with captcha.js.");
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
