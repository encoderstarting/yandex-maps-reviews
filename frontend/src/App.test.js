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
