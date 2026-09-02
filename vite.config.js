import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Marketing site
                'resources/css/web/app.css',
                'resources/css/web/pages.css',
                'resources/css/web/servicios-conversion.css',
                'resources/js/app.js',
                'resources/js/servicios-conversion.js',
                'resources/css/web/blog.css',
                'resources/js/blog-publico.js',
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
                'resources/css/admin/blog.css',
                'resources/js/blog.js',
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
                'resources/css/admin/usuarios.css',
                'resources/js/usuarios.js',
                'resources/css/admin/integraciones.css',
                'resources/css/admin/roles.css',
                'resources/js/roles.js',
                'resources/js/documentos.js',
                'resources/js/conversiones.js',
                'resources/js/conversiones-embudo.js',
                'resources/css/admin/automatizaciones.css',
                'resources/js/automatizaciones.js',
                'resources/css/admin/reportes.css',
                'resources/js/reportes.js',
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
