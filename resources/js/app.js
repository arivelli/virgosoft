import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import Pusher from 'pusher-js';
import App from './App.vue';

// Import components
import Login from './components/Login.vue';
import Dashboard from './components/Dashboard.vue';

// Create router
const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/dashboard' },
        { path: '/login', component: Login },
        { path: '/dashboard', component: Dashboard, meta: { requiresAuth: true } },
    ],
});

// Router guard
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    if (to.meta.requiresAuth && !token) {
        next('/login');
    } else if (to.path === '/login' && token) {
        next('/dashboard');
    } else {
        next();
    }
});

// Create Pinia store
const pinia = createPinia();

// Configure Pusher
window.Pusher = Pusher;
window.pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
        },
    },
});

// Create app
const app = createApp(App);
app.use(router);
app.use(pinia);
app.mount('#app');
