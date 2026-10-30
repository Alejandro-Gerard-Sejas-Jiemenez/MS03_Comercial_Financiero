import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Build para el shell single-spa: un modulo ES (spa.js) que exporta bootstrap/mount/unmount.
export default defineConfig({
  plugins: [react()],
  define: { 'process.env.NODE_ENV': '"production"' },
  build: {
    outDir: 'dist-spa',
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: { entry: 'src/spa-entry.tsx', formats: ['es'], fileName: () => 'spa.js' },
  },
  // `npm run serve:spa`: el shell (otro origen) debe poder importar el modulo.
  preview: { port: 5174, strictPort: true, cors: true },
})
