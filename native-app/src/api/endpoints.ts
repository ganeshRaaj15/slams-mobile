import { api } from './client';
import { File as ExpoFile } from 'expo-file-system';
import type {
  AdminSettingsWorkspace,
  AdminAssetDetailResponse,
  AdminAssetListResponse,
  AdminLabDetailResponse,
  AdminLabListResponse,
  AdminUserDetailResponse,
  AdminUserListResponse,
  ApprovalQueueDetail,
  ApprovalQueueResponse,
  AuthResponse,
  BookingApplicantInput,
  BookingDetail,
  BookingListResponse,
  DaySlot,
  ExternalRequest,
  ExternalRequestListResponse,
  ExternalRequestReviewItem,
  ExternalRequestReviewQueueResponse,
  FacultyReference,
  IssueReportWorkspaceResponse,
  LabDetail,
  LabSummary,
  MaintenanceDetailResponse,
  MaintenanceWorkspaceResponse,
  NativeBootstrap,
  NativePushStatus,
  NativeUser,
  NotificationListResponse,
  ProfileWorkspace,
  ReportSnapshot,
  RecommendedSlot,
} from '../types/api';
import { getApiAccessToken } from './client';
import { fetchApi } from './fetch';

type ApiEnvelope<T> = {
  status: 'success' | 'error';
  message?: string;
} & T;

type UploadAsset = {
  uri: string;
  name: string;
  mimeType: string;
};

function appendUploadAsset(formData: FormData, fieldName: string, asset: UploadAsset | null | undefined) {
  if (!asset?.uri) {
    return;
  }

  formData.append(fieldName, new ExpoFile(asset.uri), asset.name);
}

export async function loginRequest(payload: {
  email: string;
  password: string;
  device_name: string;
}) {
  const response = await api.post<ApiEnvelope<AuthResponse>>('/api/native/auth/token', payload);
  return response.data;
}

export async function registerRequest(payload: {
  username: string;
  email: string;
  password: string;
  password_confirm: string;
  device_name: string;
}) {
  const response = await api.post<ApiEnvelope<AuthResponse>>('/api/native/auth/register', payload);
  return response.data;
}

export async function meRequest() {
  const response = await api.get<ApiEnvelope<{ user: NativeUser }>>('/api/native/auth/me');
  return response.data;
}

export async function logoutRequest() {
  const response = await api.post<ApiEnvelope<Record<string, never>>>('/api/native/auth/logout');
  return response.data;
}

export async function bootstrapRequest() {
  const response = await api.get<ApiEnvelope<NativeBootstrap>>('/api/native/bootstrap');
  return response.data;
}

export async function getProfileWorkspaceRequest() {
  const response = await api.get<ApiEnvelope<ProfileWorkspace>>('/api/native/profile');
  return response.data;
}

export async function updateProfileRequest(payload: {
  username: string;
  full_name: string;
  phone: string;
  faculty_id: number | null;
  email: string;
  password?: string;
  password_confirm?: string;
  profile_photo?: {
    uri: string;
    name: string;
    mimeType: string;
  } | null;
}) {
  if (!payload.profile_photo) {
    const response = await api.post<ApiEnvelope<{ user: NativeUser }>>('/api/native/profile', {
      username: payload.username,
      full_name: payload.full_name ?? '',
      phone: payload.phone ?? '',
      faculty_id: payload.faculty_id,
      email: payload.email,
      password: payload.password ?? '',
      password_confirm: payload.password_confirm ?? '',
    });

    if (response.data.status === 'error') {
      throw new Error(
        typeof response.data.message === 'string' && response.data.message.trim() !== ''
          ? response.data.message
          : 'Profile update failed.',
      );
    }

    return response.data;
  }

  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('username', payload.username);
  formData.append('full_name', payload.full_name ?? '');
  formData.append('phone', payload.phone ?? '');
  formData.append('faculty_id', payload.faculty_id ? String(payload.faculty_id) : '');
  formData.append('email', payload.email);
  formData.append('password', payload.password ?? '');
  formData.append('password_confirm', payload.password_confirm ?? '');
  appendUploadAsset(formData, 'profile_photo', payload.profile_photo);

  const response = await fetchApi('/api/native/profile', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const rawBody = await response.text();
  const trimmedBody = rawBody.trim();
  let data: ApiEnvelope<{ user: NativeUser }> | null = null;

  if (trimmedBody !== '') {
    try {
      data = JSON.parse(trimmedBody) as ApiEnvelope<{ user: NativeUser }>;
    } catch (_error) {
      throw new Error(
        trimmedBody.startsWith('<')
          ? `The server returned an HTML response while uploading the profile photo (HTTP ${response.status}).`
          : response.ok
            ? 'The server returned an unreadable response while uploading the profile photo.'
            : trimmedBody,
      );
    }
  }

  if (!data) {
    throw new Error(
      response.ok
        ? 'The server returned an empty response while uploading the profile photo.'
        : `Profile photo upload failed (HTTP ${response.status}).`,
    );
  }

  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Profile update failed.',
    );
  }

  return data;
}

export async function getReportSnapshotRequest() {
  const response = await api.get<
    ApiEnvelope<
      {
        report?: ReportSnapshot;
        exports?: { pdf_url?: string; csv_url?: string };
      } & Partial<ReportSnapshot>
    >
  >(
    '/api/native/reports',
  );
  const payload = response.data;
  const fallbackExports = {
    pdf_url: '/api/native/reports/export/pdf',
    csv_url: '/api/native/reports/export/csv',
  };

  const report =
    payload.report ??
    (payload.reportTitle && payload.kpis
      ? {
          reportTitle: payload.reportTitle,
          scopeLabel: payload.scopeLabel ?? 'Operational Scope',
          generatedAt: payload.generatedAt ?? '',
          kpis: payload.kpis,
          assetTotals: payload.assetTotals ?? {},
          statusMap: payload.statusMap ?? {},
          monthlyTrend: payload.monthlyTrend ?? [],
          topLabs: payload.topLabs ?? [],
          facultyCounts: payload.facultyCounts ?? [],
          labs: payload.labs ?? [],
          maintenanceStatus: payload.maintenanceStatus ?? {},
          maintenanceTrend: payload.maintenanceTrend ?? [],
          topMaintenanceAssets: payload.topMaintenanceAssets ?? [],
          upcomingBookings: payload.upcomingBookings ?? [],
          role: payload.role ?? 'admin',
        }
      : null);

  if (!report) {
    throw new Error('Report data is unavailable.');
  }

  return {
    report,
    exports: {
      pdf_url: payload.exports?.pdf_url || fallbackExports.pdf_url,
      csv_url: payload.exports?.csv_url || fallbackExports.csv_url,
    },
  };
}

export async function listLabsRequest() {
  const response = await api.get<ApiEnvelope<{ labs: LabSummary[] }>>('/api/native/labs');
  return response.data;
}

export async function listFacultiesRequest() {
  const response = await api.get<ApiEnvelope<{ faculties: FacultyReference[] }>>(
    '/api/native/references/faculties',
  );
  return response.data;
}

export async function getLabRequest(labId: number) {
  const response = await api.get<ApiEnvelope<{ lab: LabDetail }>>(`/api/native/labs/${labId}`);
  return response.data;
}

export async function listRecommendedSlotsRequest(
  labId: number,
  params: {
    service_id: number;
    assets: string;
  },
) {
  const response = await api.get<ApiEnvelope<{ slots: RecommendedSlot[] }>>(
    `/api/native/labs/${labId}/recommended-slots`,
    {
      params,
    },
  );
  return response.data;
}

export async function listDaySlotsRequest(
  labId: number,
  date: string,
  params: {
    service_id: number;
    assets: string;
  },
) {
  const response = await api.get<ApiEnvelope<{ slots: DaySlot[] }>>(
    `/api/native/labs/${labId}/day/${date}`,
    {
      params,
    },
  );
  return response.data;
}

export async function checkBookingSlotRequest(payload: {
  lab_id: number;
  service_id: number;
  date: string;
  start_time: string;
  end_time: string;
  asset_selection: string;
}) {
  const body = new URLSearchParams({
    lab_id: String(payload.lab_id),
    service_id: String(payload.service_id),
    date: payload.date,
    start_time: payload.start_time,
    end_time: payload.end_time,
    asset_selection: payload.asset_selection,
  }).toString();

  const response = await fetchApi('/api/native/bookings/check-slot', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
    },
    body,
  });

  const data = (await response.json()) as { conflict: boolean; reason?: string };
  if (!response.ok) {
    throw new Error(
      typeof data.reason === 'string' && data.reason.trim() !== ''
        ? data.reason
        : 'Slot availability check failed.',
    );
  }

  return data;
}

export async function listBookingsRequest(status?: string) {
  const response = await api.get<ApiEnvelope<BookingListResponse>>('/api/native/bookings', {
    params: status ? { status } : undefined,
  });
  return response.data;
}

export async function getBookingRequest(bookingId: number) {
  const response = await api.get<ApiEnvelope<{ booking: BookingDetail }>>(
    `/api/native/bookings/${bookingId}`,
  );
  return response.data;
}

export async function cancelBookingRequest(bookingId: number) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(
    `/api/native/bookings/${bookingId}/cancel`,
  );
  return response.data;
}

export async function submitBookingRequest(payload: {
  lab_id: number;
  service_id: number;
  date: string;
  start_time: string;
  end_time: string;
  activity: string;
  supervisor_name?: string;
  supervisor_email?: string;
  supervisor_phone?: string;
  asset_selection: string;
  applicants: BookingApplicantInput[];
  pdf: {
    uri: string;
    name: string;
    mimeType: string;
  };
}) {
  const token = getApiAccessToken();
  const formData = new FormData();

  formData.append('lab_id', String(payload.lab_id));
  formData.append('service_id', String(payload.service_id));
  formData.append('date', payload.date);
  formData.append('start_time', payload.start_time);
  formData.append('end_time', payload.end_time);
  formData.append('activity', payload.activity);
  formData.append('supervisor_name', payload.supervisor_name ?? '');
  formData.append('supervisor_email', payload.supervisor_email ?? '');
  formData.append('supervisor_phone', payload.supervisor_phone ?? '');
  formData.append('asset_selection', payload.asset_selection);

  payload.applicants.forEach((applicant) => {
    formData.append('applicant_name[]', applicant.name);
    formData.append('applicant_id[]', applicant.matric_id);
    formData.append('applicant_email[]', applicant.email);
    formData.append('applicant_phone[]', applicant.phone);
    formData.append('applicant_faculty[]', String(applicant.faculty_id ?? ''));
  });

  appendUploadAsset(formData, 'pdf', payload.pdf);

  const response = await fetchApi('/api/native/bookings/submit', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<Record<string, never>>;

  if (!response.ok || data.status === 'error') {
    const message =
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Booking submission failed.';
    const error = new Error(message);
    throw error;
  }

  return data;
}

export async function listNotificationsRequest(limit = 40) {
  const response = await api.get<ApiEnvelope<NotificationListResponse>>('/api/native/notifications', {
    params: { limit },
  });
  return response.data;
}

export async function getNativePushStatusRequest() {
  const response = await api.get<ApiEnvelope<NativePushStatus>>('/api/native/push');
  return response.data;
}

export async function getAdminSettingsRequest() {
  const response = await api.get<ApiEnvelope<AdminSettingsWorkspace>>('/api/native/admin/settings');
  return response.data;
}

export async function updateAdminSettingsRequest(payload: Record<string, string>) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>('/api/native/admin/settings', payload);
  return response.data;
}

export async function updateAdminBookingSlotsRequest(
  slots: Array<{
    start: string;
    end: string;
  }>,
) {
  const response = await api.post<ApiEnvelope<{ slots: Array<{ start: string; end: string; label?: string }> }>>(
    '/api/native/admin/settings/slots',
    { slots },
  );
  return response.data;
}

export async function runAdminScheduledTasksRequest() {
  const response = await api.post<ApiEnvelope<{
    booking_reminders: number;
    maintenance_due_reminders: number;
    errors: string[];
  }>>('/api/native/admin/settings/run-scheduled-tasks');
  return response.data;
}

export async function listAdminUsersRequest(params?: {
  q?: string;
  role?: string;
  status?: string;
  page?: number;
  per_page?: number;
}) {
  const response = await api.get<ApiEnvelope<AdminUserListResponse>>('/api/native/admin/users', {
    params,
  });
  return response.data;
}

export async function getAdminUserRequest(userId: number) {
  const response = await api.get<ApiEnvelope<AdminUserDetailResponse>>(`/api/native/admin/users/${userId}`);
  return response.data;
}

export async function createAdminUserRequest(payload: {
  username: string;
  full_name: string;
  phone: string;
  faculty_id: number | null;
  email: string;
  password: string;
  password_confirm: string;
  roles: string[];
}) {
  const response = await api.post<ApiEnvelope<{ user_id: number }>>('/api/native/admin/users', payload);
  return response.data;
}

export async function updateAdminUserRequest(
  userId: number,
  payload: {
    username: string;
    full_name: string;
    phone: string;
    faculty_id: number | null;
    email: string;
    password?: string;
    password_confirm?: string;
    active: boolean;
    roles: string[];
  },
) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(`/api/native/admin/users/${userId}`, payload);
  return response.data;
}

export async function sendAdminRecoveryRequest(userId: number) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(
    `/api/native/admin/users/${userId}/send-recovery`,
  );
  return response.data;
}

export async function deleteAdminUserRequest(userId: number) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(
    `/api/native/admin/users/${userId}/delete`,
  );
  return response.data;
}

export async function listAdminLabsRequest(params?: {
  q?: string;
  pic?: string;
}) {
  const response = await api.get<ApiEnvelope<AdminLabListResponse>>('/api/native/admin/labs', {
    params,
  });
  return response.data;
}

export async function getAdminLabRequest(labId: number) {
  const response = await api.get<ApiEnvelope<AdminLabDetailResponse>>(`/api/native/admin/labs/${labId}`);
  return response.data;
}

export async function createAdminLabRequest(payload: {
  name: string;
  room: string;
  description: string;
  capacity: string;
  availability_note: string;
  safety_note: string;
  pic_name: string;
  pic_email: string;
  pic_phone: string;
  remove_image?: boolean;
  remove_pic_image?: boolean;
  image?: {
    uri: string;
    name: string;
    mimeType: string;
  } | null;
  pic_image?: {
    uri: string;
    name: string;
    mimeType: string;
  } | null;
}) {
  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('name', payload.name);
  formData.append('room', payload.room);
  formData.append('description', payload.description);
  formData.append('capacity', payload.capacity);
  formData.append('availability_note', payload.availability_note);
  formData.append('safety_note', payload.safety_note);
  formData.append('pic_name', payload.pic_name);
  formData.append('pic_email', payload.pic_email);
  formData.append('pic_phone', payload.pic_phone);
  formData.append('remove_image', payload.remove_image ? '1' : '0');
  formData.append('remove_pic_image', payload.remove_pic_image ? '1' : '0');

  if (payload.image) {
    appendUploadAsset(formData, 'image', payload.image);
  }

  if (payload.pic_image) {
    appendUploadAsset(formData, 'pic_image', payload.pic_image);
  }

  const response = await fetchApi('/api/native/admin/labs', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<{ lab: AdminLabDetailResponse['lab']; warning?: string | null }>;
  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Laboratory save failed.',
    );
  }

  return data;
}

export async function updateAdminLabRequest(
  labId: number,
  payload: Parameters<typeof createAdminLabRequest>[0],
) {
  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('name', payload.name);
  formData.append('room', payload.room);
  formData.append('description', payload.description);
  formData.append('capacity', payload.capacity);
  formData.append('availability_note', payload.availability_note);
  formData.append('safety_note', payload.safety_note);
  formData.append('pic_name', payload.pic_name);
  formData.append('pic_email', payload.pic_email);
  formData.append('pic_phone', payload.pic_phone);
  formData.append('remove_image', payload.remove_image ? '1' : '0');
  formData.append('remove_pic_image', payload.remove_pic_image ? '1' : '0');

  if (payload.image) {
    appendUploadAsset(formData, 'image', payload.image);
  }

  if (payload.pic_image) {
    appendUploadAsset(formData, 'pic_image', payload.pic_image);
  }

  const response = await fetchApi(`/api/native/admin/labs/${labId}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<{ lab: AdminLabDetailResponse['lab']; warning?: string | null }>;
  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Laboratory update failed.',
    );
  }

  return data;
}

export async function deleteAdminLabRequest(labId: number) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(`/api/native/admin/labs/${labId}/delete`);
  return response.data;
}

export async function listAdminAssetsRequest(params?: {
  q?: string;
  lab_id?: number;
  status?: string;
}) {
  const response = await api.get<ApiEnvelope<AdminAssetListResponse>>('/api/native/admin/assets', {
    params,
  });
  return response.data;
}

export async function getAdminAssetRequest(assetId: number) {
  const response = await api.get<ApiEnvelope<AdminAssetDetailResponse>>(`/api/native/admin/assets/${assetId}`);
  return response.data;
}

export async function createAdminAssetRequest(payload: {
  asset_code: string;
  name: string;
  category: string;
  brand: string;
  model: string;
  serial_number: string;
  lab_id: number;
  total_quantity: string;
  location_note: string;
  purchase_date: string;
  specifications: string;
  image?: {
    uri: string;
    name: string;
    mimeType: string;
  } | null;
}) {
  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('asset_code', payload.asset_code);
  formData.append('name', payload.name);
  formData.append('category', payload.category);
  formData.append('brand', payload.brand);
  formData.append('model', payload.model);
  formData.append('serial_number', payload.serial_number);
  formData.append('lab_id', String(payload.lab_id));
  formData.append('total_quantity', payload.total_quantity);
  formData.append('location_note', payload.location_note);
  formData.append('purchase_date', payload.purchase_date);
  formData.append('specifications', payload.specifications);

  if (payload.image) {
    appendUploadAsset(formData, 'image', payload.image);
  }

  const response = await fetchApi('/api/native/admin/assets', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<{ asset: AdminAssetDetailResponse['asset'] }>;
  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Asset save failed.',
    );
  }

  return data;
}

export async function updateAdminAssetRequest(
  assetId: number,
  payload: Parameters<typeof createAdminAssetRequest>[0],
) {
  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('asset_code', payload.asset_code);
  formData.append('name', payload.name);
  formData.append('category', payload.category);
  formData.append('brand', payload.brand);
  formData.append('model', payload.model);
  formData.append('serial_number', payload.serial_number);
  formData.append('lab_id', String(payload.lab_id));
  formData.append('total_quantity', payload.total_quantity);
  formData.append('location_note', payload.location_note);
  formData.append('purchase_date', payload.purchase_date);
  formData.append('specifications', payload.specifications);

  if (payload.image) {
    appendUploadAsset(formData, 'image', payload.image);
  }

  const response = await fetchApi(`/api/native/admin/assets/${assetId}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<{ asset: AdminAssetDetailResponse['asset'] }>;
  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Asset update failed.',
    );
  }

  return data;
}

export async function deleteAdminAssetRequest(assetId: number) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(`/api/native/admin/assets/${assetId}/delete`);
  return response.data;
}

export async function registerNativePushTokenRequest(payload: {
  expo_push_token: string;
  device_name: string;
  platform: string;
}) {
  const response = await api.post<ApiEnvelope<NativePushStatus>>('/api/native/push/register', payload);
  return response.data;
}

export async function unregisterNativePushTokenRequest(payload?: { expo_push_token?: string }) {
  const response = await api.post<ApiEnvelope<NativePushStatus>>('/api/native/push/unregister', payload ?? {});
  return response.data;
}

export async function markNotificationReadRequest(notificationId: number) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(
    `/api/native/notifications/${notificationId}/read`,
  );
  return response.data;
}

export async function markAllNotificationsReadRequest() {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(
    '/api/native/notifications/read-all',
  );
  return response.data;
}

export async function listExternalRequestsRequest() {
  const response = await api.get<ApiEnvelope<ExternalRequestListResponse>>(
    '/api/native/external-requests',
  );
  return response.data;
}

export async function listApprovalQueueRequest() {
  const response = await api.get<ApiEnvelope<ApprovalQueueResponse>>('/api/native/approvals/queue');
  return response.data;
}

export async function getApprovalQueueItemRequest(bookingId: number) {
  const response = await api.get<ApiEnvelope<{ booking: ApprovalQueueDetail }>>(
    `/api/native/approvals/queue/${bookingId}`,
  );
  return response.data;
}

export async function approveBookingRequest(bookingId: number) {
  const response = await api.post<ApiEnvelope<{ newStatus: string }>>(
    `/api/native/approvals/queue/${bookingId}/approve`,
  );
  return response.data;
}

export async function rejectBookingRequest(bookingId: number) {
  const response = await api.post<ApiEnvelope<{ newStatus: string }>>(
    `/api/native/approvals/queue/${bookingId}/reject`,
  );
  return response.data;
}

export async function getExternalRequestRequest(requestId: number) {
  const response = await api.get<ApiEnvelope<{ request: ExternalRequest }>>(
    `/api/native/external-requests/${requestId}`,
  );
  return response.data;
}

export async function createExternalRequestRequest(payload: {
  lab_id: number;
  organization_name: string;
  contact_name: string;
  contact_email: string;
  contact_phone: string;
  participant_count: number;
  preferred_date: string;
  preferred_start_time: string;
  preferred_end_time: string;
  purpose: string;
  equipment_notes: string;
}) {
  const response = await api.post<ApiEnvelope<{ request_id: number }>>(
    '/api/native/external-requests',
    payload,
  );
  return response.data;
}

export async function updateExternalRequestRequest(
  requestId: number,
  payload: {
    lab_id: number;
    organization_name: string;
    contact_name: string;
    contact_email: string;
    contact_phone: string;
    participant_count: number;
    preferred_date: string;
    preferred_start_time: string;
    preferred_end_time: string;
    purpose: string;
    equipment_notes: string;
  },
) {
  const response = await api.post<ApiEnvelope<Record<string, never>>>(
    `/api/native/external-requests/${requestId}`,
    payload,
  );
  return response.data;
}

export async function listExternalRequestReviewQueueRequest(params?: {
  q?: string;
  status?: string;
  lab_id?: number;
}) {
  const response = await api.get<ApiEnvelope<ExternalRequestReviewQueueResponse>>(
    '/api/native/external-requests/review',
    {
      params,
    },
  );
  return response.data;
}

export async function getExternalRequestReviewRequest(requestId: number) {
  const response = await api.get<ApiEnvelope<{ request: ExternalRequestReviewItem; role: 'pic' | 'manager' | 'admin' }>>(
    `/api/native/external-requests/review/${requestId}`,
  );
  return response.data;
}

export async function updateExternalRequestReviewStatusRequest(
  requestId: number,
  payload: {
    status: string;
    review_notes: string;
  },
) {
  const response = await api.post<ApiEnvelope<{ request: ExternalRequestReviewItem | null }>>(
    `/api/native/external-requests/review/${requestId}/status`,
    payload,
  );
  return response.data;
}

export async function getIssueWorkspaceRequest() {
  const response = await api.get<ApiEnvelope<IssueReportWorkspaceResponse>>('/api/native/issues');
  return response.data;
}

export async function createIssueReportRequest(payload: {
  asset_id: number;
  quantity_affected: number;
  title: string;
  priority: string;
  description: string;
  unit_reference?: string;
  report_photo?: {
    uri: string;
    name: string;
    mimeType: string;
  } | null;
}) {
  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('asset_id', String(payload.asset_id));
  formData.append('quantity_affected', String(payload.quantity_affected));
  formData.append('title', payload.title);
  formData.append('priority', payload.priority);
  formData.append('description', payload.description);
  formData.append('unit_reference', payload.unit_reference ?? '');

  if (payload.report_photo) {
    appendUploadAsset(formData, 'report_photo', payload.report_photo);
  }

  const response = await fetchApi('/api/native/issues', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<{ report_id: number }>;
  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Issue report submission failed.',
    );
  }

  return data;
}

export async function listMaintenanceRequest(params?: {
  status?: string;
  asset_id?: number;
  scope?: string;
}) {
  const response = await api.get<ApiEnvelope<MaintenanceWorkspaceResponse>>('/api/native/maintenance', {
    params,
  });
  return response.data;
}

export async function getMaintenanceRequest(maintenanceId: number) {
  const response = await api.get<ApiEnvelope<MaintenanceDetailResponse>>(
    `/api/native/maintenance/${maintenanceId}`,
  );
  return response.data;
}

export async function createMaintenanceRequest(payload: {
  asset_id: number;
  quantity_affected: number;
  unit_reference?: string;
  title: string;
  issue_type: string;
  priority: string;
  description: string;
  scheduled_for: string;
  diagnosis_notes: string;
}) {
  const response = await api.post<ApiEnvelope<{ maintenance_id: number }>>('/api/native/maintenance', payload);
  return response.data;
}

export async function updateMaintenanceRequest(
  maintenanceId: number,
  payload: {
    asset_id: number;
    quantity_affected: number;
    unit_reference?: string;
    title: string;
    issue_type: string;
    priority: string;
    description: string;
    scheduled_for?: string;
    diagnosis_notes?: string;
    work_notes?: string;
    test_notes?: string;
    resolution_notes?: string;
    transition?: string;
    completion_photo?: {
      uri: string;
      name: string;
      mimeType: string;
    } | null;
  },
) {
  const token = getApiAccessToken();
  const formData = new FormData();
  formData.append('asset_id', String(payload.asset_id));
  formData.append('quantity_affected', String(payload.quantity_affected));
  formData.append('unit_reference', payload.unit_reference ?? '');
  formData.append('title', payload.title);
  formData.append('issue_type', payload.issue_type);
  formData.append('priority', payload.priority);
  formData.append('description', payload.description);
  formData.append('scheduled_for', payload.scheduled_for ?? '');
  formData.append('diagnosis_notes', payload.diagnosis_notes ?? '');
  formData.append('work_notes', payload.work_notes ?? '');
  formData.append('test_notes', payload.test_notes ?? '');
  formData.append('resolution_notes', payload.resolution_notes ?? '');
  formData.append('transition', payload.transition ?? '');

  if (payload.completion_photo) {
    appendUploadAsset(formData, 'completion_photo', payload.completion_photo);
  }

  const response = await fetchApi(`/api/native/maintenance/${maintenanceId}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  });

  const data = (await response.json()) as ApiEnvelope<{ maintenance_id: number; new_status: string }>;
  if (!response.ok || data.status === 'error') {
    throw new Error(
      typeof data.message === 'string' && data.message.trim() !== ''
        ? data.message
        : 'Maintenance update failed.',
    );
  }

  return data;
}
