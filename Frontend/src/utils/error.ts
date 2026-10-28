import axios from 'axios'

/**
 * Checks whether a message looks like a raw SQL error, stack trace,
 * internal framework error, or raw technical error payload.
 */
export function isTechnicalMessage(message: unknown): boolean {
  if (typeof message !== 'string') return false
  const msg = message.trim()

  if (!msg) return false

  // JSON or object dump string
  if (
    (msg.startsWith('{') && msg.endsWith('}')) ||
    (msg.startsWith('[') && msg.endsWith(']'))
  ) {
    return true
  }

  // SQL syntax, PDO, driver patterns
  const sqlPatterns = [
    /\bSQLSTATE\[/i,
    /\bBase table or view not found\b/i,
    /\bTable ['"].*?['"] doesn't exist\b/i,
    /\bselect\s+.*?\s+from\b/i,
    /\binsert\s+into\b/i,
    /\bupdate\s+.*?\s+set\b/i,
    /\bdelete\s+from\b/i,
    /\bSQL:\s+/i,
    /\bColumn not found\b/i,
    /\bUnknown column\b/i,
    /\bforeign key constraint fails\b/i,
    /\bDuplicate entry\b/i,
    /\bSyntax error or access violation\b/i
  ]

  // Framework and runtime stack traces
  const technicalPatterns = [
    /\bIlluminate\\/i,
    /\bPDOException\b/i,
    /\bQueryException\b/i,
    /\bErrorException\b/i,
    /\bRuntimeException\b/i,
    /\bFatal error\b/i,
    /\bCall to undefined\b/i,
    /\.php\s+on\s+line\s+\d+/i,
    /\bStack trace:\b/i,
    /\bvendor\/laravel\b/i,
    /\bvendor\/composer\b/i,
    /\bAxiosError\b/i,
    /\bNetwork Error\b/i,
    /\bECONNREFUSED\b/i,
    /\bETIMEDOUT\b/i
  ]

  return (
    sqlPatterns.some((pattern) => pattern.test(msg)) ||
    technicalPatterns.some((pattern) => pattern.test(msg))
  )
}

export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[] | string>
}

export interface LikeAxiosError {
  isAxiosError?: boolean
  response?: {
    status?: number
    statusText?: string
    data?: ApiErrorPayload
    headers?: Record<string, unknown>
    config?: unknown
  }
  config?: { url?: string; method?: string }
  code?: string
  message?: string
}

function isApiErrorLike(cause: unknown): cause is LikeAxiosError {
  return (
    axios.isAxiosError(cause) ||
    (typeof cause === 'object' && cause !== null && 'response' in cause)
  )
}

export interface ApiErrorOptions {
  fallback?: string
  logToConsole?: boolean
  context?: string
}

const DEFAULT_FALLBACK = 'The action could not be completed. Please try again.'

/**
 * Normalizes any API or runtime error into a user-friendly UI string,
 * while ensuring that sensitive technical details (SQL errors, stack traces,
 * file paths) are masked in the UI and logged to the developer console.
 */
export function parseApiError(
  cause: unknown,
  fallback = DEFAULT_FALLBACK,
  options: ApiErrorOptions = {}
): string {
  const { logToConsole = true, context } = options

  // Always log full technical details to developer console for debugging
  if (logToConsole) {
    const errorDetails = isApiErrorLike(cause)
      ? {
          status: cause.response?.status,
          statusText: cause.response?.statusText,
          url: cause.config?.url,
          method: cause.config?.method?.toUpperCase(),
          data: cause.response?.data,
          message: cause.message
        }
      : cause

    const prefix = context ? `[API Error: ${context}]` : '[API Error]'
    console.error(prefix, errorDetails)
  }

  // 1. Axios or Axios-like Error
  if (isApiErrorLike(cause)) {
    // Network failures without response (offline, server unreachable, etc.)
    if (!cause.response) {
      if (
        cause.code === 'ECONNABORTED' ||
        cause.message?.toLowerCase().includes('timeout')
      ) {
        return 'The request timed out. Please check your connection and try again.'
      }
      return 'Unable to connect to the server. Please check your internet connection.'
    }

    const { status, data } = cause.response

    // 1a. Validation errors (e.g. 422 or any response containing field errors)
    if (data?.errors && typeof data.errors === 'object') {
      const joined = Object.values(data.errors)
        .flat()
        .filter(Boolean)
        .join(' ')
      if (joined && !isTechnicalMessage(joined)) {
        return joined
      }
    }

    // 1b. Server errors (500+)
    if (status !== undefined && status >= 500) {
      // If the backend returned a safe non-technical message and no custom fallback was provided
      if (
        data?.message &&
        !isTechnicalMessage(data.message) &&
        data.message !== 'Server Error' &&
        fallback === DEFAULT_FALLBACK
      ) {
        return data.message
      }
      return fallback !== DEFAULT_FALLBACK
        ? fallback
        : 'A server error occurred. Please try again later.'
    }

    // 1c. Safe domain or HTTP status message (400, 401, 403, 404, 409, 422)
    if (data?.message && !isTechnicalMessage(data.message)) {
      return data.message
    }

    // Status-specific fallbacks if no server message or message was technical
    if (status === 401) {
      return 'Your session has expired. Please sign in again.'
    }
    if (status === 403) {
      return 'You do not have permission to perform this action.'
    }
    if (status === 404) {
      return 'The requested resource was not found.'
    }
    if (status === 422) {
      return 'Please check the highlighted details and try again.'
    }

    return fallback
  }

  // 2. Standard JS Error
  if (cause instanceof Error) {
    if (!isTechnicalMessage(cause.message)) {
      return fallback !== DEFAULT_FALLBACK ? fallback : cause.message
    }
    return fallback
  }

  // 3. String error
  if (typeof cause === 'string' && cause.trim() && !isTechnicalMessage(cause)) {
    return cause.trim()
  }

  return fallback
}
