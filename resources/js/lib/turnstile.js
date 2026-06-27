const TURNSTILE_SCRIPT_SRC =
    "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";

let loadPromise = null;

export function loadTurnstileScript() {
    if (window.turnstile?.render) {
        return Promise.resolve();
    }

    if (loadPromise) {
        return loadPromise;
    }

    loadPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector(
            `script[src="${TURNSTILE_SCRIPT_SRC}"]`,
        );

        if (existing) {
            if (window.turnstile?.render) {
                resolve();
                return;
            }

            existing.addEventListener("load", () => resolve(), { once: true });
            existing.addEventListener(
                "error",
                () => {
                    loadPromise = null;
                    reject(new Error("Turnstile script failed to load"));
                },
                { once: true },
            );

            return;
        }

        const script = document.createElement("script");
        script.src = TURNSTILE_SCRIPT_SRC;
        script.onload = () => resolve();
        script.onerror = () => {
            loadPromise = null;
            reject(new Error("Turnstile script failed to load"));
        };
        document.head.appendChild(script);
    });

    return loadPromise;
}

export function resetTurnstileLoader() {
    loadPromise = null;
}
