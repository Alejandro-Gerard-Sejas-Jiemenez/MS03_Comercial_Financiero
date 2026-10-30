/** Paleta y tipografia tomadas del frontend de MS-01, para que todos los microfrontends se vean igual. */
/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  // El portal se monta dentro del shell single-spa: las utilidades solo actuan dentro de `.ms03-portal`
  // y no se usa el reset global (preflight) para no alterar el shell ni los otros microfrontends.
  important: '.ms03-portal',
  corePlugins: { preflight: false },
  theme: {
    extend: {
      colors: {
        brand: {
          primary: '#2563EB',
          hover: '#1D4ED8',
          light: '#EFF6FF',
        },
        surface: {
          base: '#FFFFFF',
          secondary: '#F8FAFC',
        },
        line: {
          DEFAULT: '#E2E8F0',
        },
        ink: {
          primary: '#172033',
          secondary: '#64748B',
        },
        positive: '#16A34A',
        danger: '#DC2626',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px 0 rgba(15, 23, 42, 0.04), 0 1px 3px 0 rgba(15, 23, 42, 0.06)',
        cardHover: '0 4px 12px -2px rgba(15, 23, 42, 0.08), 0 2px 6px -2px rgba(15, 23, 42, 0.06)',
      },
      borderRadius: {
        xl: '0.875rem',
      },
    },
  },
  plugins: [],
}
