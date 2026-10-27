import { describe, expect, it, vi } from 'vitest'
import axios, { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { isTechnicalMessage, parseApiError } from '../error'

function makeAxiosError<T = unknown>(
  status: number | undefined,
  data?: T,
  message = 'Request failed'
): AxiosError<T> {
  const error = new AxiosError<T>(message)
  error.name = 'AxiosError'
  const config = { url: '/api/test', method: 'get' } as InternalAxiosRequestConfig
  error.config = config
  if (status !== undefined) {
    error.response = {
      status,
      statusText: status === 200 ? 'OK' : 'Error',
      data: data as T,
      headers: {},
      config
    } as AxiosResponse<T>
  }
  return error
}

describe('error utility', () => {
  describe('isTechnicalMessage', () => {
    it('detects raw SQL errors and table missing messages', () => {
      const sqlErr =
        "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'campusdesk.payments' doesn't exist (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: campusdesk, SQL: select * from `payments` where `payments`.`request_id` in (25, 26, 27))"
      expect(isTechnicalMessage(sqlErr)).toBe(true)
      expect(isTechnicalMessage('select * from users')).toBe(true)
      expect(isTechnicalMessage('Column not found: unknown column')).toBe(true)
      expect(isTechnicalMessage('Duplicate entry for key PRIMARY')).toBe(true)
    })

    it('detects PHP and framework exception traces', () => {
      expect(isTechnicalMessage('Illuminate\\Database\\QueryException')).toBe(true)
      expect(isTechnicalMessage('PDOException in Connection.php on line 838')).toBe(true)
      expect(isTechnicalMessage('Stack trace: #0 /vendor/laravel/framework')).toBe(true)
      expect(isTechnicalMessage('Fatal error: Call to undefined method')).toBe(true)
    })

    it('detects raw JSON objects or arrays', () => {
      expect(isTechnicalMessage('{"error": "something"}')).toBe(true)
      expect(isTechnicalMessage('[{"message": "err"}]')).toBe(true)
    })

    it('returns false for clean, user-facing error messages', () => {
      expect(isTechnicalMessage('Your requests could not be loaded. Please try again.')).toBe(false)
      expect(isTechnicalMessage('Invalid email or password.')).toBe(false)
      expect(isTechnicalMessage('Only ready requests can be marked as collected.')).toBe(false)
      expect(isTechnicalMessage('Choose a different staff member.')).toBe(false)
    })
  })

  describe('parseApiError', () => {
    it('logs technical errors to console.error', () => {
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const err = makeAxiosError(500, {
        message: "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'campusdesk.payments' doesn't exist"
      })

      parseApiError(err, 'Your requests could not be loaded. Please try again.')

      expect(consoleSpy).toHaveBeenCalledWith(
        '[API Error]',
        expect.objectContaining({
          status: 500,
          url: '/api/test',
          data: expect.objectContaining({
            message: expect.stringContaining('SQLSTATE[42S02]')
          })
        })
      )
      consoleSpy.mockRestore()
    })

    it('suppresses raw SQL error in UI and returns the friendly fallback for 500', () => {
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const err = makeAxiosError(500, {
        message: "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'campusdesk.payments' doesn't exist"
      })

      const uiMessage = parseApiError(
        err,
        'Your requests could not be loaded. Please try again.'
      )

      expect(uiMessage).toBe('Your requests could not be loaded. Please try again.')
      consoleSpy.mockRestore()
    })

    it('extracts validation error messages for 422 responses', () => {
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const err = makeAxiosError(422, {
        message: 'The given data was invalid.',
        errors: {
          matricule: ['The matricule has already been taken.'],
          email: ['The email must be a valid email address.']
        }
      })

      const uiMessage = parseApiError(err, 'Submission failed.')

      expect(uiMessage).toBe(
        'The matricule has already been taken. The email must be a valid email address.'
      )
      consoleSpy.mockRestore()
    })

    it('preserves intentional 4xx domain messages when safe', () => {
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const err = makeAxiosError(409, {
        message: 'The matricule prefix cannot change while students belong to this faculty.'
      })

      const uiMessage = parseApiError(err, 'Conflict occurred.')

      expect(uiMessage).toBe(
        'The matricule prefix cannot change while students belong to this faculty.'
      )
      consoleSpy.mockRestore()
    })

    it('handles network connection failures gracefully', () => {
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const err = makeAxiosError(undefined, undefined, 'Network Error')

      const uiMessage = parseApiError(err, 'Fallback message')

      expect(uiMessage).toBe(
        'Unable to connect to the server. Please check your internet connection.'
      )
      consoleSpy.mockRestore()
    })

    it('handles timeout failures gracefully', () => {
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const err = makeAxiosError(undefined, undefined, 'timeout of 30000ms exceeded')
      err.code = 'ECONNABORTED'

      const uiMessage = parseApiError(err, 'Fallback message')

      expect(uiMessage).toBe(
        'The request timed out. Please check your connection and try again.'
      )
      consoleSpy.mockRestore()
    })
  })
})
