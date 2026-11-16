/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{vue,js,ts}'],
  theme: {
    extend: {
      colors: {
        surface: {
          page: 'var(--surface-page)',
          card: 'var(--surface-card)',
          raised: 'var(--surface-raised)',
          input: 'var(--surface-input)',
          DEFAULT: 'var(--surface-card)',
        },
        subtle: 'var(--border-subtle)',
        strong: 'var(--border-strong)',
        heading: 'var(--text-heading)',
        body: 'var(--text-body)',
        muted: 'var(--text-muted)',
        placeholder: 'var(--text-placeholder)',
        'accent-token': 'var(--accent)',
        'accent-text': 'var(--accent-text)',
        overlay: 'var(--overlay)',
        status: {
          'success-bg': 'var(--status-success-bg)',
          'success-border': 'var(--status-success-border)',
          'success-text': 'var(--status-success-text)',
          'warning-bg': 'var(--status-warning-bg)',
          'warning-border': 'var(--status-warning-border)',
          'warning-text': 'var(--status-warning-text)',
          'info-bg': 'var(--status-info-bg)',
          'info-border': 'var(--status-info-border)',
          'info-text': 'var(--status-info-text)',
          'purple-bg': 'var(--status-purple-bg)',
          'purple-border': 'var(--status-purple-border)',
          'purple-text': 'var(--status-purple-text)',
          'danger-bg': 'var(--status-danger-bg)',
          'danger-border': 'var(--status-danger-border)',
          'danger-text': 'var(--status-danger-text)',
          'neutral-bg': 'var(--status-neutral-bg)',
          'neutral-border': 'var(--status-neutral-border)',
          'neutral-text': 'var(--status-neutral-text)',
          'orange-bg': 'var(--status-orange-bg)',
          'orange-border': 'var(--status-orange-border)',
          'orange-text': 'var(--status-orange-text)',
        },
        // Concrete palettes preserved for backward compatibility
        background: '#F8FAFC',
        foreground: '#0F172A',
        card: '#FFFFFF',
        primary: '#0369A1',
        'primary-light': '#0284C7',
        accent: '#0284C7',
        'accent-light': '#E0F2FE',
        neutral: {
          50: '#F8FAFC',
          100: '#F1F5F9',
          200: '#E2E8F0',
          300: '#CBD5E1',
          400: '#94A3B8',
          500: '#64748B',
          600: '#475569',
          700: '#334155',
          800: '#1E293B',
          900: '#0F172A'
        }
      },
      fontFamily: {
        sans: [
          'Inter',
          'ui-sans-serif',
          'system-ui',
          '-apple-system',
          'sans-serif'
        ]
      }
    }
  },
  // DaisyUI is intentionally disabled: its theme variables previously reduced contrast.
  plugins: []
}
