import { describe, it, expect, vi, beforeEach } from 'vitest'
import api from '../api'
import { createRequest } from '../requests'

vi.mock('../api', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    patch: vi.fn(),
  }
}))

describe('createRequest service', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('includes request_type_tier_id when submitting with file attachments (FormData)', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1 } } } as any)
    const file = new File(['dummy content'], 'test.pdf', { type: 'application/pdf' })

    await createRequest({
      request_type_id: 1,
      request_type_tier_id: 2,
      description: 'Transcript needed',
      attachments: [file],
    })

    expect(api.post).toHaveBeenCalledOnce()
    const [endpoint, formData] = vi.mocked(api.post).mock.calls[0]
    expect(endpoint).toBe('/requests')
    expect(formData).toBeInstanceOf(FormData)
    expect((formData as FormData).get('request_type_id')).toBe('1')
    expect((formData as FormData).get('request_type_tier_id')).toBe('2')
    expect((formData as FormData).get('description')).toBe('Transcript needed')
  })

  it('includes request_type_tier_id when submitting without file attachments (JSON)', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 1 } } } as any)

    await createRequest({
      request_type_id: 1,
      request_type_tier_id: 2,
      description: 'Transcript needed',
    })

    expect(api.post).toHaveBeenCalledOnce()
    expect(api.post).toHaveBeenCalledWith('/requests', {
      request_type_id: 1,
      request_type_tier_id: 2,
      description: 'Transcript needed',
    })
  })
})
