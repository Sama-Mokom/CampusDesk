import type { Config } from 'tailwindcss'
import baseConfig from './tailwind.config.js'

// Tailwind resolves tailwind.config.js first. This file re-exports the same
// object so any tooling that prefers the TypeScript config cannot drift from
// the single source of truth.
export default baseConfig satisfies Config
