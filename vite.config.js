import { defineConfig } from 'vite'
const { resolve } = require('path')
const fs = require('fs')
import vue from '@vitejs/plugin-vue'

// https://vitejs.dev/config/
export default defineConfig({
  root: './resources',
  build: {
    outDir: resolve(__dirname, './dist'),
    emptyOutDir: true,
    manifest: true,
    target: 'es2017',
    rollupOptions: {
      input: {
        scss: resolve(__dirname, './resources/scss/app.scss'),
        js: resolve(__dirname, './resources/js/app.js'),
      },
    },
    minify: true,
    write: true,
  },
  plugins: [vue()]
})
