import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js'
import type { AxiosInstance } from 'axios'
import api from './api'

declare global {
  interface Window {
    Pusher?: typeof Pusher
  }
}

// Register Pusher globally so Laravel Echo can instantiate it
if (typeof window !== 'undefined') {
  window.Pusher = Pusher
}

export interface EchoOptionsOverride {
  appKey?: string
  host?: string
  port?: number
  scheme?: 'http' | 'https'
}

export function createCustomAuthorizer(axiosInstance: AxiosInstance) {
  return (
    channel: { name: string },
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    _options?: unknown
  ) => ({
    authorize: (
      socketId: string,
      callback: ChannelAuthorizationCallback
    ) => {
      return axiosInstance
        .post('/broadcasting/auth', {
          socket_id: socketId,
          channel_name: channel.name,
        })
        .then((response) => {
          callback(null, response.data)
        })
        .catch((error: unknown) => {
          const err = error instanceof Error ? error : new Error(String(error))
          callback(err, null)
        })
    },
  })
}

export function getEchoConfig(overrides?: EchoOptionsOverride) {
  const isHttps =
    typeof window !== 'undefined' && window.location.protocol === 'https:'

  const scheme =
    overrides?.scheme ??
    (import.meta.env.VITE_REVERB_SCHEME as 'http' | 'https' | undefined) ??
    (isHttps ? 'https' : 'http')

  const forceTLS = scheme === 'https'

  const host =
    overrides?.host ??
    import.meta.env.VITE_REVERB_HOST ??
    (typeof window !== 'undefined' && window.location.hostname
      ? window.location.hostname
      : 'localhost')

  const defaultReverbPort = forceTLS ? 443 : 8085

  const rawWindowPort =
    typeof window !== 'undefined' && window.location.port
      ? Number(window.location.port)
      : null

  const isViteDevPort = rawWindowPort !== null && rawWindowPort >= 5173 && rawWindowPort <= 5179

  const fallbackPort =
    rawWindowPort !== null && !isViteDevPort
      ? rawWindowPort
      : defaultReverbPort

  const port =
    overrides?.port ??
    (import.meta.env.VITE_REVERB_PORT
      ? Number(import.meta.env.VITE_REVERB_PORT)
      : fallbackPort)

  const appKey =
    overrides?.appKey ??
    import.meta.env.VITE_REVERB_APP_KEY ??
    ''

  return {
    broadcaster: 'reverb' as const,
    key: appKey,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    enabledTransports: ['ws', 'wss'] as ('ws' | 'wss')[],
    Pusher,
    authorizer: createCustomAuthorizer(api),
  }
}

export function createEchoInstance(overrides?: EchoOptionsOverride): Echo<'reverb'> {
  return new Echo(getEchoConfig(overrides))
}
