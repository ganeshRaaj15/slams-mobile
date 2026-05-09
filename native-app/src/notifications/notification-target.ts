import type { NotificationResponse } from 'expo-notifications';

import { navigateToStack, navigateToTab } from '../navigation/navigation-service';
import type { NotificationItem } from '../types/api';

type TargetPayload = {
  url?: string;
  entityType?: string;
  entityId?: number | string | null;
};

function numericId(value: number | string | null | undefined) {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function resolveTarget(payload: TargetPayload, role?: string) {
  const url = (payload.url ?? '').toLowerCase();
  const entityType = (payload.entityType ?? '').toLowerCase();
  const entityId = numericId(payload.entityId);
  const reviewMatch = url.match(/\/dashboard\/external-requests\/(\d+)/);

  if (url.includes('/dashboard/approvals')) {
    if (entityId > 0) {
      return () => navigateToStack('ApprovalDetail', { bookingId: entityId });
    }

    return () => navigateToTab('Approvals');
  }

  if (url.includes('/technician/maintenance') || (entityType === 'maintenance' && role === 'technician')) {
    if (entityId > 0) {
      return () => navigateToStack('MaintenanceForm', { maintenanceId: entityId });
    }

    return () => navigateToTab('Maintenance');
  }

  if (url.includes('/dashboard/report-issue') || (entityType === 'maintenance' && role !== 'technician')) {
    return () => navigateToTab('Issues');
  }

  if (reviewMatch && (role === 'pic' || role === 'manager' || role === 'admin')) {
    return () => navigateToStack('ExternalRequestReviewDetail', { requestId: Number(reviewMatch[1]) });
  }

  if (url.includes('/dashboard/external-requests') && (role === 'pic' || role === 'manager' || role === 'admin')) {
    return () => navigateToTab('Requests');
  }

  if (entityType === 'external_request' && (role === 'pic' || role === 'manager' || role === 'admin') && entityId > 0) {
    return () => navigateToStack('ExternalRequestReviewDetail', { requestId: entityId });
  }

  if ((url.includes('/dashboard/external') || entityType === 'external_request') && role === 'external') {
    return () => navigateToTab('Requests');
  }

  if (entityType === 'booking' && entityId > 0) {
    return () => navigateToStack('BookingDetail', { bookingId: entityId });
  }

  return () => navigateToTab('Notifications');
}

export function openNotificationItem(notification: NotificationItem, role?: string) {
  resolveTarget(
    {
      url: notification.link,
      entityType: notification.entity_type,
      entityId: notification.entity_id,
    },
    role,
  )();
}

export function openNotificationResponse(response: NotificationResponse, role?: string) {
  void response;
  void role;
  navigateToTab('Notifications');
}
