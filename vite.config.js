import { defineConfig } from 'vite';
import laravel, {refreshPaths} from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1', // Bind to IPv4
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/company/theme.css',
                'resources/css/filament/user/theme.css',
            ],
            refresh: [
                ...refreshPaths,
                'app/Livewire/**',
                'app/Filament/**',
            ],
        }),
    ],
  
});
