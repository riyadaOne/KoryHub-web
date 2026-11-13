import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            // Polices IBM Plex hébergées avec le site (UXUI §4.3) : deux graisses au plus.
            fonts: [
                bunny('IBM Plex Sans', {
                    weights: [400, 600],
                }),
                bunny('IBM Plex Sans Arabic', {
                    weights: [400, 600],
                }),
                bunny('IBM Plex Mono', {
                    weights: [400],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
