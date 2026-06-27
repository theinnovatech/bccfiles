<template>
    <div
        class="login-page flex min-h-screen min-h-[100dvh] overflow-x-hidden flex-col md:h-screen md:overflow-hidden md:flex-row bg-gradient-to-br from-[#3a6fd6] via-[#1e5bc4] to-[#0046a8]"
        :class="{ 'login-split-open': splitting }"
    >
        <!-- Left Side: White panel -->
        <div
            class="hidden md:flex md:w-1/2 flex-col items-center justify-center bg-white p-8 lg:p-12 rounded-r-[2.5rem] shadow-2xl z-10 login-panel-left"
            :class="{ 'panel-slide-left': splitting }"
        >
            <div class="max-w-md w-full text-center space-y-5">
                <div class="flex items-center justify-center gap-5">
                    <div class="login-logo-circle login-logo-circle--lg">
                        <img
                            src="/images/logo1.png"
                            alt="Kagawaran ng Edukasyon"
                        />
                    </div>
                    <div class="login-logo-circle login-logo-circle--lg">
                        <img
                            src="/images/logo2.png"
                            alt="Iriga City Division"
                        />
                    </div>
                </div>
                <div class="space-y-1">
                    <h2
                        class="text-2xl font-bold tracking-tight text-[#0038a8]"
                    >
                        OBIMS
                    </h2>
                    <p class="text-sm text-[#5b7fbf] font-medium">
                        {{ organizationName }}
                    </p>
                </div>
                <div class="flex justify-center py-2">
                    <Vue3Lottie
                        :animation-data="timeManagementJson"
                        :height="280"
                        :width="280"
                        :loop="true"
                        :auto-play="true"
                    />
                </div>
                <p
                    class="login-tagline-typewriter text-xs text-[#8fa7db] leading-relaxed min-h-[2.75rem]"
                    aria-label="Supply Unit Inventory Management System. Track, manage, and optimize division materials efficiently."
                >
                    <span aria-hidden="true">{{ typedTagline }}</span><span
                        v-if="typewriterActive"
                        class="login-typewriter-cursor"
                        aria-hidden="true"
                    ></span>
                </p>
            </div>
        </div>

        <!-- Right Side: Blue login panel -->
        <div
            class="login-panel-right flex-1 md:w-1/2 flex items-start md:items-center justify-center bg-transparent relative overflow-x-hidden overflow-y-auto md:overflow-hidden"
            :class="{ 'panel-slide-right': splitting }"
        >
            <div
                class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white/5 blur-3xl pointer-events-none hidden sm:block"
            ></div>
            <div
                class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-white/5 blur-3xl pointer-events-none hidden sm:block"
            ></div>

            <div
                class="login-panel-inner w-full max-w-md relative z-10 login-enter mx-auto"
            >
                <!-- Mobile Only Header -->
                <div
                    class="login-enter-item login-mobile-header md:hidden text-center"
                    style="--login-enter-delay: 0.05s"
                >
                    <div
                        class="flex items-center justify-center gap-4 mx-auto w-fit"
                    >
                        <div class="login-logo-circle">
                            <img
                                src="/images/logo1.png"
                                alt="Kagawaran ng Edukasyon"
                            />
                        </div>
                        <div class="login-logo-circle">
                            <img
                                src="/images/logo2.png"
                                alt="Iriga City Division"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="login-enter-item login-mobile-title md:hidden text-center"
                    style="--login-enter-delay: 0.13s"
                >
                    <h1
                        class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white"
                    >
                        OBIMS
                    </h1>
                    <p
                        class="text-xs sm:text-sm text-[#d6e0f5] font-medium px-2 break-words"
                    >
                        {{ organizationName }}
                    </p>
                </div>

                <!-- Login -->
                <div class="login-form-block space-y-5 sm:space-y-6">
                    <div class="space-y-2">
                        <div
                            class="login-enter-item h-1 w-12 bg-[#fcd116] rounded-full"
                            style="--login-enter-delay: 0.21s"
                        ></div>
                        <h2
                            class="login-enter-item text-2xl sm:text-3xl font-bold text-white tracking-tight"
                            style="--login-enter-delay: 0.29s"
                        >
                            Welcome Back
                        </h2>
                        <p
                            class="login-enter-item text-sm sm:text-base text-[#e8efff]"
                            style="--login-enter-delay: 0.37s"
                        >
                            Please sign in to access your account
                        </p>
                    </div>

                    <form
                        class="login-form-enter login-form-stack"
                        autocomplete="off"
                        @submit.prevent="submit"
                    >
                        <!-- Email -->
                        <div
                            class="login-enter-item login-field"
                            style="--login-enter-delay: 0.45s"
                        >
                            <label class="login-field-label" for="login-email">
                                Email Address
                            </label>
                            <div class="login-field-box">
                                <input
                                    id="login-email"
                                    v-model="form.email"
                                    type="email"
                                    name="obims_email"
                                    class="login-field-input"
                                    placeholder="name@obims.local"
                                    autocomplete="off"
                                    :readonly="blockAutofill"
                                    required
                                    @focus="releaseAutofillBlock"
                                />
                                <i
                                    class="pi pi-envelope login-field-icon"
                                    aria-hidden="true"
                                ></i>
                            </div>
                        </div>

                        <!-- Password -->
                        <div
                            class="login-enter-item login-field"
                            style="--login-enter-delay: 0.53s"
                        >
                            <label
                                class="login-field-label"
                                for="login-password"
                            >
                                Password
                            </label>
                            <div
                                class="login-field-box login-field-box--password"
                            >
                                <input
                                    id="login-password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="obims_password"
                                    class="login-field-input"
                                    placeholder="Enter your password"
                                    autocomplete="new-password"
                                    :readonly="blockAutofill"
                                    required
                                    @focus="releaseAutofillBlock"
                                />
                                <i
                                    class="pi pi-lock login-field-icon"
                                    aria-hidden="true"
                                ></i>
                                <button
                                    type="button"
                                    class="login-field-toggle"
                                    :aria-label="
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    "
                                    @click="showPassword = !showPassword"
                                >
                                    <i
                                        :class="
                                            showPassword
                                                ? 'pi pi-eye-slash'
                                                : 'pi pi-eye'
                                        "
                                        aria-hidden="true"
                                    ></i>
                                </button>
                            </div>
                        </div>

                        <!-- Cloudflare Turnstile -->
                        <div
                            v-if="turnstileSiteKey"
                            class="login-enter-fade login-turnstile-field"
                            style="--login-enter-delay: 0.61s"
                        >
                            <p
                                v-if="turnstileLoading"
                                class="text-xs text-[#c8d6f0] text-left"
                            >
                                Loading security check...
                            </p>
                            <div
                                ref="turnstileContainer"
                                class="cf-turnstile-wrapper"
                            ></div>
                            <p
                                v-if="turnstileError"
                                class="text-xs text-red-300 flex items-center gap-1.5"
                            >
                                <i
                                    class="pi pi-exclamation-triangle text-xs"
                                ></i>
                                <span>{{ turnstileError }}</span>
                                <button
                                    type="button"
                                    class="ml-1 underline underline-offset-2 hover:text-white"
                                    @click="initTurnstile"
                                >
                                    Retry
                                </button>
                            </p>
                        </div>

                        <label
                            class="login-enter-item login-remember"
                            style="--login-enter-delay: 0.69s"
                        >
                            <input
                                v-model="remember"
                                type="checkbox"
                                class="login-remember-input"
                            />
                            <span class="login-remember-box" aria-hidden="true">
                                <i class="pi pi-check login-remember-icon"></i>
                            </span>
                            <span class="login-remember-label">Remember me</span>
                        </label>

                        <UiButton
                            type="submit"
                            class="login-enter-item login-submit-btn w-full h-11 min-h-[2.75rem] bg-[#fcd116] hover:bg-[#e5bc00] text-[#001e5a] hover:text-[#001e5a] font-bold rounded-xl transition-all shadow-lg shadow-[#fcd116]/10 border-none"
                            style="--login-enter-delay: 0.77s"
                            :loading="loading"
                        >
                            Sign In
                        </UiButton>

                        <p
                            class="login-enter-item text-center"
                            style="--login-enter-delay: 0.85s"
                        >
                            <a
                                href="/about"
                                class="text-sm text-[#e8efff]/80 transition-colors hover:text-white hover:underline"
                            >
                                About OBIMS
                            </a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { Vue3Lottie } from "vue3-lottie";
import UiButton from "../components/ui/UiButton.vue";
import { useNotify } from "../composables/useNotify";
import { loadTurnstileScript, resetTurnstileLoader } from "../lib/turnstile";
import { useAuthStore } from "../stores/auth";
import timeManagementJson from "../../animations/Time management.json";

const { organizationName, turnstileSiteKey } = defineProps({
    organizationName: {
        type: String,
        default: "DepEd Supply Unit Inventory",
    },
    turnstileSiteKey: {
        type: String,
        default: "",
    },
});

const auth = useAuthStore();
const notify = useNotify();
const loading = ref(false);
const splitting = ref(false);
const turnstileContainer = ref(null);
const turnstileToken = ref("");
const turnstileError = ref("");
const turnstileLoading = ref(false);
const turnstileWidgetId = ref(null);
const showPassword = ref(false);
const remember = ref(false);
const blockAutofill = ref(true);
const form = reactive({ email: "", password: "" });

const TAGLINE =
    "Supply Unit Inventory Management System. Track, manage, and optimize division materials efficiently.";
const typedTagline = ref("");
const typewriterActive = ref(false);
let typewriterTimer = null;

function clearTypewriterTimer() {
    if (typewriterTimer !== null) {
        clearTimeout(typewriterTimer);
        typewriterTimer = null;
    }
}

function startTaglineTypewriter() {
    clearTypewriterTimer();

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        typedTagline.value = TAGLINE;
        typewriterActive.value = false;
        return;
    }

    typewriterActive.value = true;
    typedTagline.value = "";

    let index = 0;
    let deleting = false;

    const tick = () => {
        if (!deleting) {
            if (index < TAGLINE.length) {
                typedTagline.value += TAGLINE.charAt(index);
                const char = TAGLINE.charAt(index);
                index += 1;
                const delay = char === "." ? 140 : char === "," ? 90 : 38;
                typewriterTimer = window.setTimeout(tick, delay);
                return;
            }

            typewriterTimer = window.setTimeout(() => {
                deleting = true;
                tick();
            }, 2200);
            return;
        }

        if (index > 0) {
            index -= 1;
            typedTagline.value = TAGLINE.slice(0, index);
            typewriterTimer = window.setTimeout(tick, 22);
            return;
        }

        deleting = false;
        typewriterTimer = window.setTimeout(tick, 600);
    };

    typewriterTimer = window.setTimeout(tick, 750);
}

function releaseAutofillBlock() {
    blockAutofill.value = false;
}

function resetLoginFields() {
    form.email = "";
    form.password = "";
}

function turnstileErrorMessage(code) {
    const normalized = String(code ?? "");

    if (normalized.includes("110200")) {
        return `Add ${window.location.hostname} to your Cloudflare Turnstile widget hostnames (127.0.0.1 and localhost for local dev).`;
    }

    if (normalized.includes("110100") || normalized.includes("400020")) {
        return "Invalid Turnstile site key. Check TURNSTILE_SITE_KEY in .env.";
    }

    if (normalized.includes("200500")) {
        return "Could not load security check. Disable ad blockers or allow challenges.cloudflare.com.";
    }

    return "Security check failed. Please try again.";
}

function getTurnstileSize() {
    return window.matchMedia("(max-width: 639px)").matches
        ? "flexible"
        : "normal";
}

function mountTurnstile() {
    if (
        !window.turnstile ||
        !turnstileContainer.value ||
        !turnstileSiteKey ||
        turnstileWidgetId.value !== null
    ) {
        return;
    }

    turnstileWidgetId.value = window.turnstile.render(
        turnstileContainer.value,
        {
            sitekey: turnstileSiteKey,
            theme: "light",
            size: getTurnstileSize(),
            appearance: "execute",
            callback(token) {
                turnstileToken.value = token;
                turnstileError.value = "";
                turnstileLoading.value = false;
            },
            "expired-callback"() {
                turnstileToken.value = "";
            },
            "error-callback"(code) {
                console.error("[Turnstile]", code);
                turnstileToken.value = "";
                turnstileLoading.value = false;
                turnstileError.value = turnstileErrorMessage(code);
            },
        },
    );

    turnstileLoading.value = false;
}

function destroyTurnstileWidget() {
    if (window.turnstile && turnstileWidgetId.value !== null) {
        window.turnstile.remove(turnstileWidgetId.value);
        turnstileWidgetId.value = null;
    }
}

async function initTurnstile() {
    if (!turnstileSiteKey) {
        return;
    }

    turnstileError.value = "";
    turnstileLoading.value = true;
    destroyTurnstileWidget();
    resetTurnstileLoader();

    await nextTick();

    try {
        await loadTurnstileScript();
        await nextTick();
        mountTurnstile();

        if (turnstileWidgetId.value === null) {
            throw new Error("Turnstile widget did not mount");
        }
    } catch (error) {
        console.error(error);
        turnstileLoading.value = false;
        turnstileError.value =
            "Unable to load security check. Check your connection or disable ad blockers.";
    }
}

onMounted(async () => {
    startTaglineTypewriter();
    resetLoginFields();

    requestAnimationFrame(resetLoginFields);
    setTimeout(() => {
        if (blockAutofill.value) {
            resetLoginFields();
        }
    }, 150);

    if (!turnstileSiteKey) {
        return;
    }

    await initTurnstile();
});

onBeforeUnmount(() => {
    clearTypewriterTimer();
    destroyTurnstileWidget();
});

function resetTurnstile() {
    turnstileToken.value = "";

    if (window.turnstile && turnstileWidgetId.value !== null) {
        window.turnstile.reset(turnstileWidgetId.value);
    }
}

async function submit() {
    if (turnstileSiteKey && !turnstileToken.value) {
        turnstileError.value =
            "Please complete the security check before signing in.";
        return;
    }

    turnstileError.value = "";
    loading.value = true;

    try {
        const credentials = {
            email: form.email,
            password: form.password,
            remember: remember.value,
        };

        if (turnstileSiteKey) {
            credentials.cf_turnstile_response = turnstileToken.value;
        }

        await auth.login(credentials);

        splitting.value = true;
        setTimeout(() => {
            window.location.href = "/";
        }, 700);
    } catch (error) {
        loading.value = false;
        resetTurnstile();

        const turnstileMessage =
            error.response?.data?.errors?.cf_turnstile_response?.[0];

        if (turnstileMessage) {
            turnstileError.value = turnstileMessage;
        }

        notify.error(
            turnstileMessage ||
                error.response?.data?.message ||
                "Invalid credentials.",
            "Login failed",
        );
    }
}
</script>

<style>
/* ─── Page load fade-in ─────────────────────────────────────── */
@keyframes loginFadeIn {
    from {
        opacity: 0;
        transform: translateY(1.25rem);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-enter-item {
    opacity: 0;
    animation: loginFadeIn 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--login-enter-delay, 0s);
}

.login-enter-fade {
    opacity: 0;
    animation: loginFadeInOpacity 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--login-enter-delay, 0s);
}

@keyframes loginFadeInOpacity {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

.login-tagline-typewriter {
    text-align: center;
}

.login-typewriter-cursor {
    display: inline-block;
    width: 2px;
    height: 0.85em;
    margin-left: 1px;
    vertical-align: text-bottom;
    background-color: #8fa7db;
    animation: loginCursorBlink 0.75s step-end infinite;
}

@keyframes loginCursorBlink {
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .login-typewriter-cursor {
        animation: none;
    }
}

.login-form-enter {
    display: contents;
}

.login-form-stack > .login-enter-item + .login-enter-item,
.login-form-stack > .login-enter-item + .login-enter-fade,
.login-form-stack > .login-enter-fade + .login-enter-item,
.login-form-stack > .login-enter-fade + .login-enter-fade {
    margin-top: 1.125rem;
}

@media (min-width: 640px) {
    .login-form-stack > .login-enter-item + .login-enter-item,
    .login-form-stack > .login-enter-item + .login-enter-fade,
    .login-form-stack > .login-enter-fade + .login-enter-item,
    .login-form-stack > .login-enter-fade + .login-enter-fade {
        margin-top: 1.25rem;
    }
}

/* ─── Mobile layout ─────────────────────────────────────────── */
.login-panel-right {
    padding:
        max(1rem, env(safe-area-inset-top))
        max(1rem, env(safe-area-inset-right))
        max(1.25rem, env(safe-area-inset-bottom))
        max(1rem, env(safe-area-inset-left));
}

@media (min-width: 640px) {
    .login-panel-right {
        padding:
            max(1.5rem, env(safe-area-inset-top))
            max(1.5rem, env(safe-area-inset-right))
            max(1.5rem, env(safe-area-inset-bottom))
            max(1.5rem, env(safe-area-inset-left));
    }
}

@media (min-width: 768px) {
    .login-panel-right {
        padding: 3rem;
    }
}

@media (min-width: 1024px) {
    .login-panel-right {
        padding: 4rem;
    }
}

.login-panel-inner {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
    max-width: 28rem;
}

@media (min-width: 640px) {
    .login-panel-inner {
        gap: 1.5rem;
    }
}

@media (min-width: 768px) {
    .login-panel-inner {
        gap: 2rem;
    }
}

.login-mobile-header {
    margin-bottom: 0.25rem;
}

.login-mobile-title {
    margin-bottom: 0.5rem;
}

.login-form-block {
    width: 100%;
}

@media (max-width: 639px) {
    .login-logo-circle {
        width: 4.25rem;
        height: 4.25rem;
        padding: 0.5rem;
    }

    .login-field-box {
        height: 3rem;
    }

    .login-field-input {
        font-size: 16px;
        line-height: 3rem;
    }

    .login-field-label {
        font-size: 0.75rem;
    }

    .login-remember-label {
        font-size: 0.875rem;
    }

    .login-turnstile-field p {
        word-break: break-word;
    }

    .login-submit-btn {
        min-height: 2.875rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .login-enter-item,
    .login-enter-fade {
        animation: none;
        opacity: 1;
        transform: none;
    }
}

/* ─── Split-open transition ─────────────────────────────────── */
.login-panel-left,
.login-panel-right {
    transition: transform 0.65s cubic-bezier(0.76, 0, 0.24, 1);
}

.panel-slide-left {
    transform: translateX(-105%);
}

.panel-slide-right {
    transform: translateX(105%);
}

/* ─── Circular logos ────────────────────────────────────────── */
.login-logo-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 5rem;
    height: 5rem;
    padding: 0.625rem;
    border-radius: 9999px;
    overflow: hidden;
    background: #fff;
}

.login-logo-circle--lg {
    width: 7rem;
    height: 7rem;
    padding: 0.75rem;
}

.login-logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* ─── Cloudflare Turnstile ──────────────────────────────────── */
.login-turnstile-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.cf-turnstile-wrapper {
    display: block;
    width: 100%;
    max-width: 300px;
    line-height: 0;
}

.cf-turnstile-wrapper > div {
    display: inline-block;
    line-height: normal;
    vertical-align: top;
}

@media (max-width: 639px) {
    .cf-turnstile-wrapper {
        width: 100%;
        max-width: none;
        overflow: hidden;
        height: 62px;
    }

    .cf-turnstile-wrapper > div {
        display: block;
        width: 100% !important;
        clip-path: inset(0 0 17px 0);
        -webkit-clip-path: inset(0 0 17px 0);
        margin-bottom: -17px;
    }
}

/* ─── Remember me ───────────────────────────────────────────── */
.login-remember {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    cursor: pointer;
    user-select: none;
}

.login-remember-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.login-remember-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.125rem;
    height: 1.125rem;
    border: 1.5px solid rgba(255, 255, 255, 0.55);
    border-radius: 0.3rem;
    background: rgba(255, 255, 255, 0.12);
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}

.login-remember-icon {
    font-size: 0.625rem;
    color: #001e5a;
    opacity: 0;
    transform: scale(0.5);
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}

.login-remember-input:checked + .login-remember-box {
    background: #fcd116;
    border-color: #fcd116;
}

.login-remember-input:checked + .login-remember-box .login-remember-icon {
    opacity: 1;
    transform: scale(1);
}

.login-remember-input:focus-visible + .login-remember-box {
    outline: 2px solid #fcd116;
    outline-offset: 2px;
}

.login-remember-label {
    font-size: 0.9375rem;
    color: #e8efff;
}

.login-remember:hover .login-remember-box {
    border-color: rgba(255, 255, 255, 0.75);
}

/* ─── Login fields ──────────────────────────────────────────── */

.login-page {
    color-scheme: dark;
}

.login-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.login-field-label {
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #e8efff;
}

.login-field-box {
    position: relative;
    display: flex;
    align-items: center;
    height: 3.375rem;
    padding: 0 1rem;
    background: rgba(255, 255, 255, 0.18);
    border: 1.5px solid rgba(255, 255, 255, 0.45);
    border-radius: 0.75rem;
    box-shadow: 0 2px 10px rgba(0, 30, 90, 0.2);
    transition:
        border-color 0.2s ease,
        background-color 0.2s ease,
        box-shadow 0.2s ease;
}

.login-field-box:hover {
    background: rgba(255, 255, 255, 0.24);
    border-color: rgba(255, 255, 255, 0.6);
}

.login-field-box:focus-within {
    background: rgba(255, 255, 255, 0.26);
    border-color: #fcd116;
    box-shadow:
        0 2px 10px rgba(0, 30, 90, 0.2),
        0 0 0 3px rgba(252, 209, 22, 0.25);
}

.login-field-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0 1rem 0 2.75rem;
    border: none;
    border-radius: 0.75rem;
    outline: none;
    background: transparent !important;
    background-color: transparent !important;
    box-shadow: none !important;
    -webkit-appearance: none;
    appearance: none;
    color: #fff;
    font-size: 1rem;
    font-family: inherit;
    line-height: 3.375rem;
    z-index: 1;
}

.login-field-box--password .login-field-input {
    padding-right: 2.75rem;
}

.login-field-input[readonly] {
    cursor: text;
}

.login-field-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.login-field-input:-webkit-autofill,
.login-field-input:-webkit-autofill:hover,
.login-field-input:-webkit-autofill:focus,
.login-field-input:-webkit-autofill:active {
    -webkit-text-fill-color: #fff !important;
    caret-color: #fff;
    border: none !important;
    outline: none !important;
    background-color: transparent !important;
    -webkit-box-shadow: 0 0 0 1000px #3d7fd0 inset !important;
    box-shadow: 0 0 0 1000px #3d7fd0 inset !important;
    transition: background-color 9999s ease-out 0s;
}

.login-field-input:autofill,
.login-field-input:autofill:hover,
.login-field-input:autofill:focus {
    -webkit-text-fill-color: #fff !important;
    caret-color: #fff;
    box-shadow: 0 0 0 1000px #3d7fd0 inset !important;
    -webkit-box-shadow: 0 0 0 1000px #3d7fd0 inset !important;
}

.login-field-icon {
    position: relative;
    z-index: 2;
    flex-shrink: 0;
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.65);
    pointer-events: none;
    transition: color 0.2s ease;
}

.login-field-box:focus-within .login-field-icon {
    color: #fcd116;
}

.login-field-toggle {
    position: relative;
    z-index: 2;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    margin-left: auto;
    border: none;
    border-radius: 0.375rem;
    background: transparent;
    color: rgba(255, 255, 255, 0.65);
    cursor: pointer;
    transition:
        color 0.2s ease,
        background-color 0.2s ease;
}

.login-field-toggle:hover {
    color: #fcd116;
    background: rgba(255, 255, 255, 0.08);
}

.login-field-toggle:focus-visible {
    outline: 2px solid #fcd116;
    outline-offset: 2px;
}
</style>
