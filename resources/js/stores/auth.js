import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async login(email, password) {
      try {
        const response = await axios.post('/api/login', {
          email,
          password,
        });

        const token = response.data.token;
        this.token = token;
        localStorage.setItem('token', token);

        // Set default auth header
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // Get user data
        await this.fetchUser();
        
        // Reinitialize Pusher with new token
        this.initPusher();

        return true;
      } catch (error) {
        throw error.response.data.errors?.email?.[0] || 'Login failed';
      }
    },

    async logout() {
      try {
        await axios.post('/api/logout');
      } finally {
        this.token = null;
        this.user = null;
        localStorage.removeItem('token');
        delete axios.defaults.headers.common['Authorization'];
      }
    },

    async fetchUser() {
      const response = await axios.get('/api/me');
      this.user = response.data;
    },

    async initAuth() {
      const token = localStorage.getItem('token');
      if (token) {
        this.token = token;
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        await this.fetchUser();
        this.initPusher();
      }
    },
    
    initPusher() {
      if (!this.token || !this.user) return;
      
      // Disconnect existing connection if any
      if (window.pusher) {
        window.pusher.disconnect();
      }
      
      // Reinitialize Pusher with current token
      window.pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        authEndpoint: '/api/broadcasting/auth',
        auth: {
          headers: {
            Authorization: `Bearer ${this.token}`,
          },
        },
      });
    },
  },
});
