import {defineConfig} from 'vite';

export default defineConfig({
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: [
                'resources/css/main.css',
                'resources/css/filament-baseline.css',
            ],
            output: {
                entryFileNames: 'js/[name].js',
                assetFileNames: 'css/[name].[ext]',
            },
        },
    },
});