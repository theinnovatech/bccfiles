<template>
    <div class="about-page min-h-[100dvh] bg-white text-[#1a1a1a]">
        <!-- Doc header -->
        <header class="about-doc-header">
            <div class="about-doc-header-inner">
                <div class="flex items-center gap-3">
                    <img
                        src="/images/logo1.png"
                        alt="DepEd"
                        class="h-8 w-8 object-contain"
                    />
                    <img
                        src="/images/logo2.png"
                        alt="Division"
                        class="h-8 w-8 object-contain"
                    />
                    <span class="about-doc-brand">OBIMS</span>
                </div>
                <a href="/" class="about-doc-back">
                    <i class="pi pi-arrow-left text-xs" aria-hidden="true"></i>
                    Back to Sign In
                </a>
            </div>
        </header>

        <div class="about-doc-layout">
            <!-- Sidebar TOC -->
            <aside class="about-doc-aside">
                <p class="about-doc-aside-label">On this page</p>
                <nav class="about-doc-nav">
                    <a href="#overview">Overview</a>
                    <a href="#features">Key Features</a>
                    <a href="#organization">Organization</a>
                    <a href="#team">Research Team</a>
                </nav>
            </aside>

            <!-- Main content -->
            <article class="about-doc-content">
                <header class="about-doc-title-block">
                    <p class="about-doc-eyebrow">Documentation</p>
                    <h1 class="about-doc-h1">About OBIMS</h1>
                    <p class="about-doc-lead">
                        Online Barcode Inventory Management System for the
                        Department of Education supply unit.
                    </p>
                    <p class="about-doc-meta">
                        {{ organizationName }} &middot; {{ currentYear }}
                    </p>
                </header>

                <section id="overview" class="about-doc-section">
                    <h2 class="about-doc-h2">Overview</h2>
                    <p>
                        OBIMS (Online Barcode Inventory Management System) is an
                        online, barcode-driven inventory management platform
                        built for the Department of Education. It helps the
                        supply unit track, manage, and optimize division
                        materials efficiently.
                    </p>
                    <p>
                        The system supports the full inventory lifecycle — from
                        item registration and stock operations to supply
                        requests, issuance, returns, and reporting.
                    </p>
                </section>

                <section id="features" class="about-doc-section">
                    <h2 class="about-doc-h2">Key Features</h2>
                    <ul class="about-doc-list">
                        <li v-for="feature in features" :key="feature.label">
                            <strong>{{ feature.label }}</strong>
                            <span> — {{ feature.desc }}</span>
                        </li>
                    </ul>
                </section>

                <section id="organization" class="about-doc-section">
                    <h2 class="about-doc-h2">Organization</h2>
                    <dl class="about-doc-dl">
                        <div>
                            <dt>Institution</dt>
                            <dd>Department of Education</dd>
                        </div>
                        <div>
                            <dt>Unit</dt>
                            <dd>{{ organizationName }}</dd>
                        </div>
                        <div>
                            <dt>System</dt>
                            <dd>Online Barcode Inventory Management System</dd>
                        </div>
                    </dl>
                </section>

                <section id="team" class="about-doc-section">
                    <h2 class="about-doc-h2">Research Team</h2>
                    <p>
                        The researchers behind the study, design, and
                        development of OBIMS.
                    </p>

                    <div class="about-doc-team">
                        <div class="about-doc-team-lead">
                            <div
                                class="about-doc-team-photo about-doc-team-photo--lead"
                            >
                                <img
                                    v-if="!projectManager.imageError"
                                    :src="projectManager.image"
                                    :alt="projectManager.name"
                                    @error="projectManager.imageError = true"
                                />
                                <span v-else>{{
                                    projectManager.initials
                                }}</span>
                            </div>
                            <div
                                class="about-doc-team-info about-doc-team-info--center"
                            >
                                <h3>{{ projectManager.name }}</h3>
                                <p class="about-doc-team-role">
                                    {{ projectManager.role }}
                                </p>
                                <a :href="`mailto:${projectManager.email}`">{{
                                    projectManager.email
                                }}</a>
                            </div>
                        </div>

                        <div class="about-doc-team-grid">
                            <div
                                v-for="member in teamMembers"
                                :key="member.email"
                                class="about-doc-team-member"
                            >
                                <div class="about-doc-team-photo">
                                    <img
                                        v-if="!member.imageError"
                                        :src="member.image"
                                        :alt="member.name"
                                        @error="member.imageError = true"
                                    />
                                    <span v-else>{{ member.initials }}</span>
                                </div>
                                <div class="about-doc-team-info">
                                    <h3>{{ member.name }}</h3>
                                    <p class="about-doc-team-role">
                                        {{ member.role }}
                                    </p>
                                    <a :href="`mailto:${member.email}`">{{
                                        member.email
                                    }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <footer class="about-doc-footer">
                    <p>
                        OBIMS &middot; Department of Education &middot;
                        {{ currentYear }}
                    </p>
                </footer>
            </article>
        </div>
    </div>
</template>

<script setup>
import { reactive } from "vue";

defineProps({
    organizationName: {
        type: String,
        default: "DepEd Supply Unit Inventory",
    },
});

const currentYear = new Date().getFullYear();

const features = [
    {
        label: "Barcode Scanning",
        desc: "Identify and track items using online barcode technology",
    },
    { label: "Registered Items", desc: "Commonly used supplies" },
    {
        label: "Stock Tracking",
        desc: "Monitor stock levels, receive goods, and record adjustments",
    },
    {
        label: "Supply Requests",
        desc: "Handle department supply request workflows",
    },
    {
        label: "Issuance & Returns",
        desc: "Track item issuance and returns to inventory",
    },
    { label: "Reports", desc: "Generate inventory reports and insights" },
    {
        label: "Physical Inventory",
        desc: "Conduct stock count sessions and reconcile variances",
    },
];

const projectManager = reactive({
    name: "Hubert Villaruel",
    role: "Project Manager",
    email: "hubertvillaruel15@gmail.com",
    image: "/images/team/Hubert%20Villaruel.jpg",
    initials: "HV",
    imageError: false,
});

const teamMembers = reactive([
    {
        name: "Erika Mae Brisenio",
        role: "Member",
        email: "brisenioerikamae876@gmail.com",
        image: "/images/team/Erika%20Mae%20Brisenio.jpg",
        initials: "EB",
        imageError: false,
    },
    {
        name: "Princess Ellaine Francisco",
        role: "Member",
        email: "princessellainefrancisco11@gmail.com",
        image: "/images/team/Priincess%20Ellaine%20Francisco.jpg",
        initials: "PF",
        imageError: false,
    },
    {
        name: "Jona Badong",
        role: "Member",
        email: "jonabadong73@gmail.com",
        image: "/images/team/Jona%20Badong.jpg",
        initials: "JB",
        imageError: false,
    },
    {
        name: "Cindy Deris",
        role: "Member",
        email: "felismeniocindy@gmail.com",
        image: "/images/team/Cindy%20Deris.jpg",
        initials: "CD",
        imageError: false,
    },
]);
</script>

<style scoped>
.about-doc-header {
    position: sticky;
    top: 0;
    z-index: 20;
    border-bottom: 1px solid #e5e7eb;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
}

.about-doc-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    max-width: 90rem;
    margin: 0 auto;
    padding: 1rem 2rem;
}

.about-doc-brand {
    font-size: 1.0625rem;
    font-weight: 600;
    color: #0038a8;
    letter-spacing: -0.01em;
}

.about-doc-back {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: #5b7fbf;
    transition: color 0.15s ease;
}

.about-doc-back:hover {
    color: #0038a8;
}

.about-doc-layout {
    display: flex;
    gap: 3.5rem;
    max-width: 90rem;
    margin: 0 auto;
    padding: 3rem 2rem 5rem;
}

.about-doc-aside {
    display: none;
    width: 13rem;
    flex-shrink: 0;
}

@media (min-width: 1024px) {
    .about-doc-aside {
        display: block;
    }
}

.about-doc-aside-label {
    margin-bottom: 0.75rem;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #9ca3af;
}

.about-doc-nav {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    position: sticky;
    top: 5rem;
}

.about-doc-nav a {
    padding: 0.375rem 0;
    font-size: 0.9375rem;
    color: #6b7280;
    border-left: 2px solid transparent;
    padding-left: 0.75rem;
    transition:
        color 0.15s ease,
        border-color 0.15s ease;
}

.about-doc-nav a:hover {
    color: #0038a8;
    border-left-color: #c8d6ef;
}

.about-doc-content {
    flex: 1;
    min-width: 0;
    max-width: none;
}

.about-doc-title-block {
    margin-bottom: 3rem;
    padding-bottom: 2.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.about-doc-eyebrow {
    margin-bottom: 0.625rem;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #0038a8;
}

.about-doc-h1 {
    font-size: 2.75rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    line-height: 1.2;
    color: #111827;
}

.about-doc-lead {
    margin-top: 1.25rem;
    font-size: 1.375rem;
    line-height: 1.6;
    color: #4b5563;
}

.about-doc-meta {
    margin-top: 1.25rem;
    font-size: 1rem;
    color: #9ca3af;
}

.about-doc-section {
    margin-bottom: 3rem;
    scroll-margin-top: 5rem;
}

.about-doc-h2 {
    margin-bottom: 1.25rem;
    padding-bottom: 0.625rem;
    font-size: 1.75rem;
    font-weight: 600;
    letter-spacing: -0.015em;
    color: #111827;
    border-bottom: 1px solid #f3f4f6;
}

.about-doc-section p {
    font-size: 1.125rem;
    line-height: 1.75;
    color: #374151;
}

.about-doc-section p + p {
    margin-top: 1rem;
}

.about-doc-list {
    margin: 0;
    padding-left: 1.25rem;
    list-style: disc;
}

.about-doc-list li {
    font-size: 1.125rem;
    line-height: 1.75;
    color: #374151;
}

.about-doc-list li + li {
    margin-top: 0.5rem;
}

.about-doc-list strong {
    font-weight: 600;
    color: #111827;
}

.about-doc-dl {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.about-doc-dl dt {
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9ca3af;
}

.about-doc-dl dd {
    margin-top: 0.25rem;
    font-size: 1.125rem;
    color: #374151;
}

.about-doc-team {
    margin-top: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2.5rem;
}

.about-doc-team-lead {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
    padding-bottom: 2.5rem;
    border-bottom: 1px solid #f3f4f6;
    width: 100%;
}

.about-doc-team-grid {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem 3.5rem;
}

@media (min-width: 640px) {
    .about-doc-team-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.about-doc-team-member {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    min-width: 0;
}

.about-doc-team-photo {
    flex-shrink: 0;
    width: 6.5rem;
    height: 6.5rem;
    overflow: hidden;
    border-radius: 9999px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
}

.about-doc-team-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.about-doc-team-photo span {
    font-size: 1.25rem;
    font-weight: 600;
    color: #0038a8;
}

.about-doc-team-photo--lead {
    width: 8.5rem;
    height: 8.5rem;
}

.about-doc-team-info--center {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.about-doc-team-info {
    min-width: 0;
}

.about-doc-team-info h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
}

.about-doc-team-role {
    margin-top: 0.25rem;
    font-size: 1rem;
    color: #6b7280;
}

.about-doc-team-info a {
    display: inline-block;
    margin-top: 0.375rem;
    font-size: 1rem;
    color: #0038a8;
    word-break: break-word;
    transition: color 0.15s ease;
}

.about-doc-team-info a:hover {
    color: #002a7a;
    text-decoration: underline;
}

.about-doc-footer {
    margin-top: 3.5rem;
    padding-top: 2rem;
    border-top: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #9ca3af;
}
</style>
