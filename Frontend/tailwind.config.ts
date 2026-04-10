/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        background: '#ffffff',
        foreground: '#1f2937',
        /* Navy blue — must stay dark; DaisyUI was overriding `primary` with pale theme vars */
        primary: '#063251',
        'primary-light': '#0a4d73',
        accent: '#d4a574',
        'accent-light': '#e8d5c4',
        neutral: {
          50: '#f9fafb',
          100: '#f3f4f6',
          200: '#e5e7eb',
          300: '#d1d5db',
          400: '#9ca3af',
          500: '#6b7280',
          600: '#4b5563',
          700: '#374151',
          800: '#1f2937',
          900: '#111827',
        },
      },
      fontFamily: {
        sans: ['Geist', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['Geist Mono', 'monospace'],
      },
    },
  },
  /* daisyUI removed: it extended `primary` to CSS vars and made `text-primary` too light on white */
  plugins: [],
}
