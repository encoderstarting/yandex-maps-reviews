import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import App from './App.vue';
import apiClient from './api/client.js';

vi.mock('./api/client.js', () => ({
    default: {
        delete: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
    },
}));

const mountedWrappers = [];

function mountApp() {
    const wrapper = mount(App);
    mountedWrappers.push(wrapper);

    return wrapper;
}

function completedOrganization() {
    return {
        id: 7,
        url: 'https://yandex.ru/maps/org/test/123456',
        name: 'Тестовая кофейня',
        rating: '4.8',
        ratings_count: 128,
        reviews_count: 51,
        last_synced_at: '2026-09-12T15:00:00+03:00',
        sync: { status: 'completed', progress: 100, processed_reviews: 51 },
    };
}

function reviewsResponse(page) {
    return {
        data: {
            data: [{
                id: page,
                author: page === 1 ? 'Анна' : 'Иван',
                rating: page === 1 ? 5 : 4,
                text: page === 1 ? 'Отличный кофе' : 'Уютное место',
                published_at: '2026-09-01T12:00:00+03:00',
            }],
            meta: {
                current_page: page,
                last_page: 2,
                per_page: 50,
                total: 51,
            },
        },
    };
}

beforeEach(() => {
    apiClient.get.mockImplementation((url) => {
        if (url === '/api/v1/user') {
            return Promise.resolve({
                data: { data: { id: 1, name: 'Никита', email: 'demo@example.com' } },
            });
        }

        if (url === '/api/v1/organizations') {
            return Promise.resolve({ data: { data: [] } });
        }

        return Promise.reject(new Error(`Неожиданный GET-запрос: ${url}`));
    });
});

afterEach(() => {
    mountedWrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.clearAllMocks();
});

describe('подключение компании', () => {
    it('показывает ошибку валидации рядом со ссылкой', async () => {
        apiClient.post.mockRejectedValue({
            response: {
                status: 422,
                data: { errors: { url: ['Укажите ссылку на организацию в Яндекс Картах.'] } },
            },
        });
        const wrapper = mountApp();
        await flushPromises();

        await wrapper.get('input[aria-label="Ссылка на компанию"]').setValue('https://example.com/company');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Укажите ссылку на организацию в Яндекс Картах.');
    });

    it('показывает статус после добавления компании', async () => {
        apiClient.post.mockResolvedValue({
            status: 201,
            data: {
                data: {
                    id: 7,
                    name: null,
                    sync: { status: 'pending', progress: 0, processed_reviews: 0 },
                },
            },
        });
        const wrapper = mountApp();
        await flushPromises();

        await wrapper.get('input[aria-label="Ссылка на компанию"]').setValue('https://yandex.ru/maps/org/test/123456');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(apiClient.post).toHaveBeenCalledWith('/api/v1/organizations', {
            url: 'https://yandex.ru/maps/org/test/123456',
        });
        expect(wrapper.text()).toContain('Компания добавлена. Начали сбор данных.');
        expect(wrapper.text()).toContain('Ожидает запуска');
    });
});

describe('просмотр отзывов', () => {
    beforeEach(() => {
        apiClient.get.mockImplementation((url, config) => {
            if (url === '/api/v1/user') {
                return Promise.resolve({
                    data: { data: { id: 1, name: 'Никита', email: 'demo@example.com' } },
                });
            }

            if (url === '/api/v1/organizations') {
                return Promise.resolve({ data: { data: [completedOrganization()] } });
            }

            if (url === '/api/v1/organizations/7/reviews') {
                return Promise.resolve(reviewsResponse(config?.params?.page ?? 1));
            }

            return Promise.reject(new Error(`Неожиданный GET-запрос: ${url}`));
        });
    });

    it('показывает агрегаты и первую страницу отзывов', async () => {
        const wrapper = mountApp();
        await flushPromises();

        expect(wrapper.text()).toContain('Тестовая кофейня');
        expect(wrapper.text()).toContain('Средний рейтинг');
        expect(wrapper.text()).toContain('4.8');
        expect(wrapper.text()).toContain('Оценок');
        expect(wrapper.text()).toContain('128');
        expect(wrapper.text()).toContain('Отзывов');
        expect(wrapper.text()).toContain('51');
        expect(wrapper.text()).toContain('Анна');
        expect(wrapper.text()).toContain('Отличный кофе');
        expect(wrapper.text()).toContain('Страница 1 из 2');
    });

    it('переключает страницу без перезагрузки', async () => {
        const wrapper = mountApp();
        await flushPromises();
        const nextButton = wrapper.findAll('button').find((button) => button.text() === 'Вперёд');

        expect(nextButton).toBeDefined();
        await nextButton.trigger('click');
        await flushPromises();

        expect(apiClient.get).toHaveBeenCalledWith(
            '/api/v1/organizations/7/reviews',
            { params: { page: 2 } },
        );
        expect(wrapper.text()).toContain('Иван');
        expect(wrapper.text()).toContain('Уютное место');
        expect(wrapper.text()).toContain('Страница 2 из 2');
    });
});
