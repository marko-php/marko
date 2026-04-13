import { defineConfig } from 'vite';
import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';

export default defineConfig(
  createBaseConfig({
    entrypoints: ['resources/js/app.ts'],
  }),
);
