import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Marketing site
                'resources/css/web/app.css',
                'resources/js/app.js',
                // Admin panel — shared
                'resources/css/admin/global.css',
                'resources/css/admin/sidebar.css',
                'resources/js/global.js',
                'resources/js/theme.js',
                // Admin panel — dashboard module
                'resources/css/admin/dashboard.css',
                'resources/js/dashboard.js',
                // Admin panel — auth (Laravel Breeze backend, custom views)
                'resources/css/admin/auth.css',
                'resources/js/auth.js',
                // Admin panel — business modules
                'resources/css/admin/clientes.css',
                'resources/js/clientes.js',
                'resources/css/admin/servicios.css',
                'resources/js/servicios.js',
                'resources/css/admin/seo.css',
                'resources/js/seo.js',
                'resources/css/admin/keywords.css',
                'resources/js/keywords.js',
                'resources/css/admin/ads.css',
                'resources/js/ads.js',
                'resources/css/admin/desarrollo.css',
                'resources/js/desarrollo.js',
                'resources/css/admin/finanzas.css',
                'resources/js/finanzas.js',
                'resources/css/admin/archivos.css',
                'resources/js/archivos.js',
                'resources/css/admin/integraciones.css',
                'resources/css/admin/roles.css',
                'resources/js/roles.js',
                'resources/js/documentos.js',
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources'),
        },
    },
});
