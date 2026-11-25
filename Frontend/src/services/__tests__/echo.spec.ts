import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { User } from '@/types'
import api from '../api'
import { createCustomAuthorizer, createEchoInstance, getEchoConfig } from '../echo'
import { RealtimeManager } from '../realtime'

describe('Echo Configuration and Sanctum Authorizer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    delete window.__APP_CONFIG__
  })

  it('creates default configuration for Reverb', () => {
    const config = getEchoConfig({
      appKey: 'test-key',
      host: 'localhost',
      port: 8080,
      scheme: 'http',
    })

    expect(config.broadcaster).toBe('reverb')
    expect(config.key).toBe('test-key')
    expect(config.wsHost).toBe('localhost')
    expect(config.wsPort).toBe(8080)
    expect(config.forceTLS).toBe(false)
    expect(typeof config.authorizer).toBe('function')
  })

  it('prefers the runtime config key over the build environment', () => {
    const originalEnvKey = import.meta.env.VITE_REVERB_APP_KEY
    try {
      ;(import.meta.env as any).VITE_REVERB_APP_KEY = 'build-key'
      window.__APP_CONFIG__ = { reverbAppKey: 'runtime-key' }
      expect(getEchoConfig().key).toBe('runtime-key')
    } finally {
      ;(import.meta.env as any).VITE_REVERB_APP_KEY = originalEnvKey
      delete window.__APP_CONFIG__
    }
  })

  it('falls back to the build environment and never invents a key', () => {
    const originalEnvKey = import.meta.env.VITE_REVERB_APP_KEY
    try {
      ;(import.meta.env as any).VITE_REVERB_APP_KEY = 'build-key'
      expect(getEchoConfig().key).toBe('build-key')

      ;(import.meta.env as any).VITE_REVERB_APP_KEY = ''
      expect(getEchoConfig().key).toBe('')

      delete (import.meta.env as any).VITE_REVERB_APP_KEY
      expect(getEchoConfig().key).toBe('')
    } finally {
      ;(import.meta.env as any).VITE_REVERB_APP_KEY = originalEnvKey
    }
  })

  it('throws a clear error when an Echo instance is created without a key', () => {
    const originalEnvKey = import.meta.env.VITE_REVERB_APP_KEY
    try {
      ;(import.meta.env as any).VITE_REVERB_APP_KEY = ''
      expect(() => createEchoInstance()).toThrow('Reverb app key is not configured')
    } finally {
      ;(import.meta.env as any).VITE_REVERB_APP_KEY = originalEnvKey
    }
  })

  it('keeps the SPA usable when realtime cannot initialize', () => {
    const manager = new RealtimeManager(() => {
      throw new Error('Reverb app key is not configured')
    })

    expect(() =>
      manager.syncUser({ id: 1, role: 'student' } as User)
    ).not.toThrow()
  })

  it('bypasses window.location.port when running on Vite dev port 5173 and defaults to Reverb port 8085', () => {
    const originalLocation = window.location
    delete (window as any).location
    window.location = { ...originalLocation, port: '5173', hostname: 'localhost' } as any

    const config = getEchoConfig()
    expect(config.wsPort).toBe(8085)

    window.location = originalLocation
  })

  it('uses window.location.port when on a custom proxy or reverse-proxy port (not Vite dev range)', () => {
    const originalLocation = window.location
    delete (window as any).location
    window.location = { ...originalLocation, port: '9000', hostname: 'localhost' } as any

    const config = getEchoConfig()
    // When VITE_REVERB_PORT is set, it takes precedence; otherwise falls back to 9000
    if (!import.meta.env.VITE_REVERB_PORT) {
      expect(config.wsPort).toBe(9000)
    } else {
      expect(config.wsPort).toBe(Number(import.meta.env.VITE_REVERB_PORT))
    }

    window.location = originalLocation
  })

  it('authorizer calls api.post to /broadcasting/auth with socket_id and channel_name', async () => {
    const authorizerFactory = createCustomAuthorizer(api)
    const channel = { name: 'private-users.42' }
    const authorizer = authorizerFactory(channel, {})

    const authData = { auth: 'test-key:signature' }
    const postSpy = vi.spyOn(api, 'post').mockResolvedValueOnce({ data: authData })

    const callback = vi.fn()
    await authorizer.authorize('12345.67890', callback)

    expect(postSpy).toHaveBeenCalledWith('/broadcasting/auth', {
      socket_id: '12345.67890',
      channel_name: 'private-users.42',
    })
    expect(callback).toHaveBeenCalledWith(null, authData)
  })

  it('authorizer handles authorization failure and passes error to callback', async () => {
    const authorizerFactory = createCustomAuthorizer(api)
    const channel = { name: 'private-users.42' }
    const authorizer = authorizerFactory(channel, {})

    const failure = new Error('Forbidden')
    vi.spyOn(api, 'post').mockRejectedValueOnce(failure)

    const callback = vi.fn()
    await authorizer.authorize('12345.67890', callback)

    expect(callback).toHaveBeenCalledWith(failure, null)
  })
})
