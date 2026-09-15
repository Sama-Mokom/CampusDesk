import api from './api'
import type { Notification } from '../types'

export async function fetchNotifications(): Promise<Notification[]> {
  const response = await api.get('/notifications')
  return response.data.data ?? []
}

export async function markNotificationRead(id: number): Promise<Notification> {
  const response = await api.patch(`/notifications/${id}/read`)
  return response.data.data ?? response.data
}
