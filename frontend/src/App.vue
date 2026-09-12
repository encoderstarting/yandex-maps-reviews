<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import apiClient from './api/client.js';

const credentials = reactive({
    email: 'demo@example.com',
    password: 'password',
});

const user = ref(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const organizations = ref([]);
const organizationsLoading = ref(false);
const organizationUrl = ref('');
const organizationUrlError = ref('');
const addingOrganization = ref(false);
const organizationNotice = ref('');
let pollTimer = null;

const initials = computed(() => user.value?.name?.slice(0, 1).toUpperCase() ?? '?');
const hasOrganizations = computed(() => organizations.value.length > 0);

const statusLabels = {
    pending: 'Ожидает запуска',
    running: 'Собираем отзывы',
    completed: 'Синхронизировано',
    failed: 'Ошибка синхронизации',
    blocked: 'Яндекс ограничил доступ',
    source_changed: 'Источник изменился',
};

function statusLabel(status) {
    return statusLabels[status] ?? 'Статус неизвестен';
}

function isSyncing(organization) {
    return ['pending', 'running'].includes(organization.sync?.status);
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function schedulePolling() {
    stopPolling();

    if (user.value && organizations.value.some(isSyncing)) {
        pollTimer = window.setTimeout(pollSyncStatuses, 1500);
    }
}

function replaceOrganization(updatedOrganization) {
    const index = organizations.value.findIndex(({ id }) => id === updatedOrganization.id);

    if (index === -1) {
        organizations.value.unshift(updatedOrganization);
    } else {
        organizations.value[index] = updatedOrganization;
    }
}

function requestMessage(requestError, fallback) {
    if (requestError.response?.status === 401) {
        user.value = null;
        organizations.value = [];
        stopPolling();

        return 'Сессия завершилась. Войдите снова.';
    }

    return requestError.response?.data?.message ?? fallback;
}

async function loadUser() {
    try {
        const response = await apiClient.get('/api/v1/user');
        user.value = response.data.data;
        await loadOrganizations();
    } catch (requestError) {
        if (requestError.response?.status !== 401) {
            error.value = 'Не удалось проверить сессию.';
        }
    } finally {
        loading.value = false;
    }
}

async function login() {
    submitting.value = true;
    error.value = '';

    try {
        await apiClient.get('/sanctum/csrf-cookie');
        const response = await apiClient.post('/login', credentials);
        user.value = response.data.data;
        await loadOrganizations();
    } catch (requestError) {
        error.value = requestError.response?.data?.message ?? 'Не удалось войти.';
    } finally {
        submitting.value = false;
    }
}

async function logout() {
    error.value = '';

    try {
        await apiClient.delete('/logout');
        stopPolling();
        organizations.value = [];
        user.value = null;
    } catch {
        error.value = 'Не удалось выйти. Попробуйте ещё раз.';
    }
}

async function loadOrganizations() {
    organizationsLoading.value = true;
    error.value = '';

    try {
        const response = await apiClient.get('/api/v1/organizations');
        organizations.value = response.data.data;
        schedulePolling();
    } catch (requestError) {
        error.value = requestMessage(requestError, 'Не удалось загрузить компании.');
    } finally {
        organizationsLoading.value = false;
    }
}

async function addOrganization() {
    addingOrganization.value = true;
    organizationUrlError.value = '';
    organizationNotice.value = '';
    error.value = '';

    try {
        const response = await apiClient.post('/api/v1/organizations', {
            url: organizationUrl.value,
        });
        replaceOrganization(response.data.data);
        organizationUrl.value = '';
        organizationNotice.value = response.status === 201
            ? 'Компания добавлена. Начали сбор данных.'
            : 'Эта компания уже подключена.';
        schedulePolling();
    } catch (requestError) {
        if (requestError.response?.status === 422) {
            organizationUrlError.value = requestError.response.data.errors?.url?.[0]
                ?? 'Проверьте ссылку на компанию.';
        } else {
            error.value = requestMessage(requestError, 'Не удалось добавить компанию.');
        }
    } finally {
        addingOrganization.value = false;
    }
}

async function pollSyncStatuses() {
    pollTimer = null;
    const syncingOrganizations = organizations.value.filter(isSyncing);

    await Promise.all(syncingOrganizations.map(async (organization) => {
        try {
            const response = await apiClient.get(`/api/v1/organizations/${organization.id}/sync-status`);
            organization.sync = response.data.data;

            if (!isSyncing(organization)) {
                const details = await apiClient.get(`/api/v1/organizations/${organization.id}`);
                replaceOrganization(details.data.data);
            }
        } catch (requestError) {
            error.value = requestMessage(requestError, 'Не удалось обновить статус синхронизации.');
        }
    }));

    schedulePolling();
}

onMounted(loadUser);
onUnmounted(stopPolling);
</script>

<template>
    <main class="min-h-screen bg-[#f3f0e8] text-[#191919]">
        <div v-if="loading" class="grid min-h-screen place-items-center px-6">
            <div class="flex items-center gap-3 text-sm font-medium">
                <span class="loading-mark" aria-hidden="true"></span>
                Загружаем…
            </div>
        </div>

        <div v-else-if="!user" class="min-h-screen lg:grid lg:grid-cols-[minmax(0,1.08fr)_minmax(420px,0.92fr)]">
            <section class="login-intro relative flex min-h-[42vh] flex-col overflow-hidden bg-[#efc84a] px-6 py-7 sm:px-10 sm:py-9 lg:min-h-screen lg:px-14 lg:py-12">
                <a class="relative z-10 flex w-fit items-center gap-3 text-lg font-bold tracking-[-0.02em]" href="#" aria-label="Отклик">
                    <span class="grid size-8 place-items-center bg-[#191919] text-sm text-white">О</span>
                    Отклик
                </a>

                <div class="relative z-10 my-auto max-w-[720px] py-16 lg:py-10">
                    <p class="mb-5 text-xs font-bold uppercase tracking-[0.18em]">Отзывы о вашем бизнесе</p>
                    <h1 class="display-title max-w-3xl text-[clamp(3rem,6.2vw,6.9rem)] leading-[0.88] tracking-[-0.065em]">
                        <br>

                    </h1>
                    <p class="mt-8 max-w-md text-base leading-7 sm:text-lg">
                        Собирайте обратную связь о компании в нашем сервисе!
                    </p>
                </div>

                <div class="relative z-10 flex items-end justify-between gap-8 border-t border-black/30 pt-5 text-xs font-semibold uppercase tracking-[0.12em]">
                    <span>Яндекс Карты</span>
                    <span class="hidden text-right sm:block">Рейтинг · отзывы · динамика</span>
                </div>

                <div class="map-pattern" aria-hidden="true">
                    <span class="map-pin"></span>
                </div>
            </section>

            <section class="flex min-h-[58vh] items-center justify-center bg-[#fcfbf7] px-6 py-14 sm:px-12 lg:min-h-screen lg:px-16">
                <form class="w-full max-w-md" @submit.prevent="login">
                    <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#e64b2f]">Личный кабинет</p>
                    <h2 class="display-title text-5xl leading-none tracking-[-0.045em]">С возвращением</h2>
                    <p class="mt-4 text-[15px] leading-6 text-[#66645f]">Войдите, чтобы продолжить работу с отзывами.</p>

                    <div class="mt-11 flex flex-col gap-7">
                        <label class="field-label">
                            <span>Электронная почта</span>
                            <input v-model="credentials.email" autocomplete="email" class="field-input" type="email" placeholder="name@example.com" required>
                        </label>

                        <label class="field-label">
                            <span>Пароль</span>
                            <input v-model="credentials.password" autocomplete="current-password" class="field-input" type="password" placeholder="Введите пароль" required>
                        </label>
                    </div>

                    <p v-if="error" class="mt-5 border-l-2 border-[#e64b2f] pl-3 text-sm leading-5 text-[#b62f1d]" role="alert">{{ error }}</p>

                    <button class="primary-button mt-8 flex w-full items-center justify-between px-5 py-4 text-sm font-bold disabled:cursor-wait disabled:opacity-60" :disabled="submitting" type="submit">
                        <span>{{ submitting ? 'Входим…' : 'Войти' }}</span>
                        <svg v-if="!submitting" aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24">
                            <path d="M5 12h14M14 7l5 5-5 5" stroke="currentColor" stroke-linecap="square" stroke-width="1.8"/>
                        </svg>
                    </button>
                </form>
            </section>
        </div>

        <div v-else class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
            <aside class="flex items-center justify-between bg-[#1d1d1b] px-5 py-5 text-white lg:min-h-screen lg:flex-col lg:items-stretch lg:px-6 lg:py-7">
                <a class="flex items-center gap-3 text-lg font-bold tracking-[-0.02em]" href="#" aria-label="Отклик">
                    <span class="grid size-8 place-items-center bg-[#efc84a] text-sm text-[#191919]">О</span>
                    Отклик
                </a>

                <nav class="hidden flex-col gap-1 lg:flex" aria-label="Основная навигация">
                    <a class="nav-link nav-link-active" href="#">
                        <svg aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24">
                            <path d="M4 19V9l8-5 8 5v10H4Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.6"/>
                            <path d="M9 19v-6h6v6" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                        Обзор
                    </a>
                    <span class="nav-link text-white/35">
                        <svg aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24">
                            <path d="M6 8h12M6 12h8M6 16h10" stroke="currentColor" stroke-linecap="square" stroke-width="1.6"/>
                            <path d="M4 4h16v16H4z" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                        Отзывы
                    </span>
                </nav>

                <div class="flex items-center gap-3 lg:border-t lg:border-white/15 lg:pt-5">
                    <span class="grid size-9 shrink-0 place-items-center bg-[#f2eee3] text-sm font-bold text-[#191919]">{{ initials }}</span>
                    <div class="hidden min-w-0 flex-1 lg:block">
                        <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                        <button class="mt-1 text-xs text-white/55 transition hover:text-white" type="button" @click="logout">Выйти</button>
                    </div>
                    <button class="text-xs text-white/65 lg:hidden" type="button" @click="logout">Выйти</button>
                </div>
            </aside>

            <section class="px-5 py-7 sm:px-8 sm:py-9 lg:px-12 lg:py-11 xl:px-16">
                <header class="flex flex-col gap-3 border-b border-black/15 pb-8 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-[#e64b2f]">Рабочее пространство</p>
                        <h1 class="display-title text-5xl tracking-[-0.045em] sm:text-6xl">Компании</h1>
                    </div>
                    <p class="max-w-xs text-sm leading-6 text-[#6d6a64]">Следите за рейтингом и новой обратной связью в одном месте.</p>
                </header>

                <p v-if="error" class="mt-6 border-l-2 border-[#e64b2f] pl-3 text-sm text-[#b62f1d]" role="alert">{{ error }}</p>

                <div class="mt-10 grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <section class="border border-black/15 bg-[#fcfbf7]">
                        <div class="border-b border-black/15 px-6 py-5 sm:px-8">
                            <span class="text-xs font-bold uppercase tracking-[0.14em]">Новая компания</span>
                        </div>

                        <form class="px-6 py-10 sm:px-8 sm:py-12" @submit.prevent="addOrganization">
                            <div class="mb-9 grid size-14 place-items-center bg-[#efc84a]">
                                <svg aria-hidden="true" class="size-7" fill="none" viewBox="0 0 24 24">
                                    <path d="M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z" stroke="currentColor" stroke-width="1.6"/>
                                    <circle cx="12" cy="10" r="2" stroke="currentColor" stroke-width="1.6"/>
                                </svg>
                            </div>
                            <h2 class="display-title max-w-xl text-4xl leading-[1.05] tracking-[-0.035em] sm:text-5xl">Добавьте компанию с Яндекс Карт</h2>
                            <p class="mt-4 max-w-xl text-[15px] leading-7 text-[#66645f]">Вставьте ссылку на карточку компании — рейтинг и отзывы появятся здесь.</p>

                            <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                                <input
                                    v-model.trim="organizationUrl"
                                    class="company-input min-w-0 flex-1 px-4 py-3.5"
                                    :aria-invalid="Boolean(organizationUrlError)"
                                    :disabled="addingOrganization"
                                    aria-describedby="organization-url-error"
                                    aria-label="Ссылка на компанию"
                                    autocomplete="url"
                                    placeholder="https://yandex.ru/maps/org/..."
                                    required
                                    type="url"
                                >
                                <button class="secondary-button px-6 py-3.5 text-sm font-bold disabled:cursor-wait disabled:opacity-60" :disabled="addingOrganization" type="submit">
                                    {{ addingOrganization ? 'Добавляем…' : 'Добавить' }}
                                </button>
                            </div>
                            <p v-if="organizationUrlError" id="organization-url-error" class="mt-3 text-sm text-[#b62f1d]" role="alert">{{ organizationUrlError }}</p>
                            <p v-else-if="organizationNotice" class="mt-3 text-sm text-[#376748]" role="status">{{ organizationNotice }}</p>
                            <p v-else class="mt-3 text-xs text-[#8a877f]">Нужна публичная HTTPS-ссылка на карточку в Яндекс Картах.</p>
                        </form>
                    </section>

                    <aside class="flex flex-col bg-[#e64b2f] p-6 text-white sm:p-8 xl:min-h-[480px]">
                        <div class="flex items-start justify-between">
                            <span class="text-xs font-bold uppercase tracking-[0.14em]">Синхронизации</span>
                            <span class="text-4xl font-light text-white/55">{{ String(organizations.length).padStart(2, '0') }}</span>
                        </div>

                        <div v-if="organizationsLoading" class="my-auto flex items-center gap-3 py-16 text-sm">
                            <span class="loading-mark loading-mark-light" aria-hidden="true"></span>
                            Загружаем компании…
                        </div>

                        <div v-else-if="!hasOrganizations" class="mt-auto pt-20">
                            <p class="display-title text-3xl leading-tight tracking-[-0.03em]">Добавьте ссылку, чтобы получить отзывы</p>
                            <p class="mt-5 text-sm leading-6 text-white/75">После добавления здесь будет виден ход сбора данных.</p>
                        </div>

                        <div v-else class="mt-8 flex flex-col gap-3">
                            <article v-for="organization in organizations" :key="organization.id" class="border border-white/25 bg-black/10 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold">{{ organization.name || 'Новая компания' }}</p>
                                        <p class="mt-1 text-xs text-white/70">{{ statusLabel(organization.sync?.status) }}</p>
                                    </div>
                                    <span class="shrink-0 text-sm font-bold">{{ organization.sync?.progress ?? 0 }}%</span>
                                </div>

                                <div class="mt-4 h-1.5 overflow-hidden bg-black/20" role="progressbar" :aria-label="`Синхронизация ${organization.name || 'компании'}`" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="organization.sync?.progress ?? 0">
                                    <span class="block h-full bg-[#efc84a] transition-[width] duration-500" :style="{ width: `${organization.sync?.progress ?? 0}%` }"></span>
                                </div>

                                <p v-if="organization.sync?.processed_reviews" class="mt-3 text-xs text-white/70">Получено отзывов: {{ organization.sync.processed_reviews }}</p>
                                <p v-if="organization.sync?.error?.message" class="mt-3 text-xs leading-5 text-white" role="alert">{{ organization.sync.error.message }}</p>
                            </article>
                        </div>
                    </aside>
                </div>

                <footer class="mt-10 flex flex-col gap-2 border-t border-black/15 pt-5 text-xs text-[#77746d] sm:flex-row sm:justify-between">
                    <span>Отклик</span>
                    <span>Обратная связь о бизнесе</span>
                </footer>
            </section>
        </div>
    </main>
</template>
