import axios from 'axios';
import { createApp } from 'vue';
import App from './App.vue';
import './style.css';

axios.defaults.baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

createApp(App).mount('#app');
