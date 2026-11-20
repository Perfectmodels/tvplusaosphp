import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { cpSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/dashboard.css', 'resources/css/workspace.css', 'resources/js/shared-workspace.js', 'resources/css/welcome-eye.css', 'resources/js/welcome-eye.js', 'resources/css/welcome-intro.css', 'resources/js/welcome-intro.js', 'resources/css/login-experience.css', 'resources/js/login-experience.js', 'resources/js/scanner-qr.js'],
            refresh: true,
        }),
        {
            name: 'publish-root-assets',
            apply: 'build',
            closeBundle() {
                if (existsSync(new URL('../index.php', import.meta.url))) {
                    cpSync(
                        fileURLToPath(new URL('./public/build', import.meta.url)),
                        fileURLToPath(new URL('../build', import.meta.url)),
                        { recursive: true },
                    );
                }
            },
        },
    ],
});
