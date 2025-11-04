'use strict';

import Alpine from 'alpinejs';
import api from './api';

export function dashboardView() {
    Alpine.data('dashboard', () => ({
        isProcessing: false,
        users: null,
        subscriptions: [],
        orders: [],
        datasets: {
            usage: [],
            countries: null
        },

        init() {
            this.getUsageDataset();
            this.getCountryDataset();
            this.getUsers();
            this.getSubscriptions();
            this.getOrders();
        },

        getUsers() {
            api.get(`/users?sort=created_at:desc&limit=5`)
                .then(response => {
                    this.users = response.data.data;
                }).catch(error => {
                    this.users = [];
                });
        },

        getSubscriptions() {
            api.get(`/subscriptions?sort=created_at:desc&limit=5`)
                .then(response => {
                    this.subscriptions = response.data.data;
                });
        },

        getOrders() {
            api.get(`/orders?sort=created_at:desc&limit=5`)
                .then(response => {
                    this.orders = response.data.data;
                });
        },

        getUsageDataset() {
            api.get(`/reports/dataset/usage`)
                .then(response => {
                    this.datasets.usage = response.data;
                });
        },

        getCountryDataset() {
            api.get(`/reports/dataset/country`)
                .then(response => {
                    let list = response.data;
                    list.sort((a, b) => b.value - a.value);

                    this.datasets.countries = list;
                });
        },
    }));

    Alpine.data('stats', () => ({
        isProcessing: false,
        stats: null,
        isCached: false,

        init() {
            this.getStats();
        },

        getStats() {
            api.get(`/reports/stats`)
                .then(response => {
                    this.stats = response.data;
                    this.isCached = response.headers.get('x-is-cached') === 'true';
                });
        },
    }));
}