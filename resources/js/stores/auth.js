import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        initialized: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.user,
        role: (state) => state.user?.role,
        isAdmin: (state) => state.user?.role === 'admin',
        isSupplyOfficer: (state) => state.user?.role === 'supply_officer',
        isDepartmentUser: (state) => state.user?.role === 'department_user',
    },
    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/user');
                this.user = data.user;
            } catch {
                this.user = null;
            } finally {
                this.initialized = true;
            }
        },
        async login(credentials) {
            const { data } = await api.post('/login', credentials);
            this.user = data.user;
        },
        async logout() {
            await api.post('/logout');
            this.user = null;
        },
    },
});
