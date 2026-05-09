export type ToneKey = 'primary' | 'success' | 'warning' | 'danger' | 'accent' | 'neutral';

export type NativeUser = {
  id: number;
  email: string;
  username: string;
  full_name: string;
  phone: string;
  faculty_id: number | null;
  profile_photo: string;
  profile_photo_url: string;
  active: boolean;
  roles: string[];
  primary_role: string;
  dashboard_path: string;
};

export type ProfileWorkspace = {
  user: NativeUser;
  editable: boolean;
  editable_reason: string | null;
  faculties: FacultyReference[];
};

export type LabSummary = {
  id: number;
  name: string;
  room: string;
  description: string;
  capacity: number;
  availability_note: string;
  safety_note: string;
  image: string;
  image_url: string;
  pic_name: string;
  pic_email: string;
  pic_phone: string;
  pic_image: string;
  pic_image_url: string;
};

export type LabAsset = {
  id: number;
  lab_service_id: number | null;
  asset_code: string;
  name: string;
  category: string;
  brand: string;
  model: string;
  serial_number: string;
  specifications: string;
  status: string;
  quantity: number;
  total_quantity: number;
  location_note: string;
  image: string;
  image_url: string;
};

export type LabService = {
  id: number;
  field_name: string;
  service_name: string;
  acceptance_criteria: string;
  calibration_status: string;
  equipment_models: string;
};

export type FacultyReference = {
  id: number;
  code: string;
  name_bm: string;
  name_en: string;
  is_fkmp: boolean;
  label: string;
};

export type LabDetail = LabSummary & {
  assets: LabAsset[];
  services: LabService[];
};

export type BookingSummary = {
  id: number;
  lab_id: number;
  service_id: number | null;
  lab_name: string;
  lab_room: string;
  service_name: string;
  date: string;
  start_time: string;
  end_time: string;
  activity: string;
  status: string;
  approved_by_pic: boolean;
  approved_by_manager: boolean;
  created_at: string;
  updated_at: string;
  can_cancel: boolean;
};

export type BookingAsset = {
  id: number;
  name: string;
  quantity_used: number;
  image: string;
  image_url: string;
};

export type BookingApplicant = {
  id: number;
  name: string;
  matric_id: string;
  email: string;
  phone: string;
  faculty: string;
};

export type BookingApplicantInput = {
  name: string;
  matric_id: string;
  email: string;
  phone: string;
  faculty_id: number | null;
};

export type BookingDetail = BookingSummary & {
  supervisor_name: string;
  supervisor_email: string;
  supervisor_phone: string;
  approval_flow: string;
  pdf_path: string;
  document_url: string;
  assets: BookingAsset[];
  applicants: BookingApplicant[];
};

export type NotificationItem = {
  id: number;
  type: string;
  title: string;
  message: string;
  link: string;
  entity_type: string;
  entity_id: number | null;
  is_read: boolean;
  created_at: string;
  updated_at: string;
};

export type ExternalRequest = {
  id: number;
  lab_id: number;
  lab_name: string;
  lab_room: string;
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
  status: string;
  status_label: string;
  review_notes: string;
  can_edit: boolean;
  created_at: string;
  updated_at: string;
};

export type ExternalRequestReviewItem = {
  id: number;
  lab_id: number;
  lab_name: string;
  lab_room: string;
  requester_name: string;
  requester_username: string;
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
  status: string;
  status_label: string;
  review_notes: string;
  reviewer_name: string;
  reviewed_at: string;
  created_at: string;
  updated_at: string;
};

export type ExternalRequestReviewQueueResponse = {
  role: 'pic' | 'manager' | 'admin';
  stats: Record<string, number>;
  status_labels: Record<string, string>;
  labs: Array<{
    id: number;
    name: string;
    room: string;
  }>;
  filters: {
    q: string;
    status: string;
    lab_id: number;
  };
  requests: ExternalRequestReviewItem[];
};

export type NativeStat = {
  id: string;
  label: string;
  value: number;
  tone: ToneKey;
};

export type NativeSummaryItem = {
  type: string;
  title: string;
  subtitle: string;
  meta: string;
};

export type NativeNavigationItem = {
  id: string;
  label: string;
};

export type NativeBootstrap = {
  user: NativeUser;
  navigation: NativeNavigationItem[];
  summary: {
    role: string;
    attention_count: number;
    attention_label: string;
    attention_meta: string;
    stats: NativeStat[];
    next_item: NativeSummaryItem | null;
    message: string;
  };
};

export type ReportSnapshot = {
  reportTitle: string;
  scopeLabel: string;
  generatedAt: string;
  kpis: Record<string, number | null>;
  assetTotals: Record<string, number>;
  statusMap: Record<string, number>;
  monthlyTrend: Array<{
    month: string;
    total: number;
  }>;
  topLabs: Array<{
    lab_name: string;
    total: number;
  }>;
  facultyCounts: Array<{
    faculty_name: string;
    total: number;
  }>;
  labs: Array<{
    id?: number;
    name?: string;
    room?: string;
    pic_name?: string;
    pic_email?: string;
  }>;
  maintenanceStatus: Record<string, number>;
  maintenanceTrend: Array<{
    month: string;
    total: number;
  }>;
  topMaintenanceAssets: Array<{
    asset_name: string;
    total: number;
  }>;
  upcomingBookings: Array<{
    lab_name: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    approval_flow: string;
  }>;
  role: 'pic' | 'manager' | 'admin';
};

export type AuthResponse = {
  token: string;
  user: NativeUser;
};

export type BookingListResponse = {
  stats: {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
    cancelled: number;
  };
  bookings: BookingSummary[];
};

export type RecommendedSlot = {
  date: string;
  label: string;
  start: string;
  end: string;
};

export type DaySlotAsset = {
  id: number;
  name: string;
  requested: number;
  remaining: number;
};

export type DaySlot = {
  label: string;
  start: string;
  end: string;
  can_book: boolean;
  reason: string | null;
  assets: DaySlotAsset[];
};

export type NotificationListResponse = {
  unread_count: number;
  notifications: NotificationItem[];
};

export type IssueReporterAsset = {
  id: number;
  name: string;
  asset_code: string;
  status: string;
  quantity: number;
  total_quantity: number;
  lab_name: string;
  lab_room: string;
  requires_unit_reference: boolean;
};

export type IssueReportSummary = {
  id: number;
  asset_id: number;
  asset_name: string;
  asset_code: string;
  lab_name: string;
  lab_room: string;
  title: string;
  priority: string;
  status: string;
  status_label: string;
  quantity_affected: number;
  unit_reference: string;
  created_at: string;
  updated_at: string;
  report_photo_url: string;
};

export type IssueReportWorkspaceResponse = {
  priorities: string[];
  assets: IssueReporterAsset[];
  recent_reports: IssueReportSummary[];
};

export type ExternalRequestListResponse = {
  stats: Record<string, number>;
  status_labels: Record<string, string>;
  requests: ExternalRequest[];
};

export type ApprovalQueueItem = {
  id: number;
  lab_name: string;
  lab_room: string;
  faculty_name: string;
  is_fkmp: boolean;
  date: string;
  start_time: string;
  end_time: string;
  activity: string;
  status: string;
  approval_flow: string;
  stage: string;
  pic_name: string;
  pic_email: string;
  assets: Array<{
    asset_id: number;
    name: string;
    quantity_used: number;
  }>;
};

export type ApprovalQueueResponse = {
  role: 'pic' | 'manager' | 'admin';
  stats: Record<string, number>;
  bookings: ApprovalQueueItem[];
};

export type ApprovalQueueDetail = {
  id: number;
  lab_id: number;
  lab_name: string;
  lab_room: string;
  pic_name: string;
  pic_email: string;
  pic_phone: string;
  faculty_name: string;
  is_fkmp: boolean;
  date: string;
  start_time: string;
  end_time: string;
  activity: string;
  status: string;
  approval_flow: string;
  approved_by_pic: boolean;
  approved_by_manager: boolean;
  supervisor_name: string;
  supervisor_email: string;
  supervisor_phone: string;
  stage: string;
  pdf_url: string;
  assets: Array<{
    asset_id: number;
    name: string;
    asset_code: string;
    model: string;
    quantity_used: number;
    image: string;
    image_url: string;
  }>;
  applicants: BookingApplicant[];
};

export type MaintenanceAssetOption = {
  id: number;
  name: string;
  asset_code: string;
  status: string;
  quantity: number;
  total_quantity: number;
  lab_name: string;
  requires_unit_reference: boolean;
};

export type MaintenancePrediction = {
  risk_percent: number;
  risk_band: string;
  decision?: {
    label?: string;
    priority?: string;
  };
  reasons?: string[];
};

export type MaintenanceRecordSummary = {
  id: number;
  asset_id: number;
  asset_name: string;
  asset_code: string;
  lab_name: string;
  lab_room: string;
  title: string;
  issue_type: string;
  priority: string;
  status: string;
  status_label: string;
  quantity_affected: number;
  unit_reference: string;
  scheduled_for: string;
  accepted_at: string;
  started_at: string;
  tested_at: string;
  completed_at: string;
  created_at: string;
  updated_at: string;
  is_locked: boolean;
};

export type MaintenanceLogItem = {
  id: number;
  from_status: string;
  to_status: string;
  notes: string;
  changed_by: string;
  created_at: string;
};

export type MaintenanceRecordDetail = MaintenanceRecordSummary & {
  description: string;
  asset_status_before: string;
  asset_status_after: string;
  diagnosis_notes: string;
  work_notes: string;
  test_notes: string;
  resolution_notes: string;
  reported_by_name: string;
  technician_name: string;
  report_photo_url: string;
  completion_photo_url: string;
  logs: MaintenanceLogItem[];
  next_statuses: string[];
  asset_prediction: MaintenancePrediction | null;
};

export type MaintenanceWorkspaceResponse = {
  stats: {
    assigned: number;
    open_total: number;
    testing: number;
  };
  status_labels: Record<string, string>;
  issue_types: string[];
  priorities: string[];
  assets: MaintenanceAssetOption[];
  records: MaintenanceRecordSummary[];
};

export type MaintenanceDetailResponse = {
  record: MaintenanceRecordDetail;
  status_labels: Record<string, string>;
  issue_types: string[];
  priorities: string[];
  assets: MaintenanceAssetOption[];
};

export type NativePushStatus = {
  active_tokens: number;
  devices: Array<{
    id: number;
    platform: string;
    device_name: string;
    is_active: boolean;
    last_used_at: string;
    last_error_at: string;
    last_error_message: string;
    updated_at: string;
  }>;
};

export type AdminSettingsWorkspace = {
  settings: Record<
    string,
    {
      label: string;
      value: string;
      type: string;
      hint: string | null;
    }
  >;
  booking_slots: Array<{
    start: string;
    end: string;
    label?: string;
  }>;
};

export type AdminUserRecord = {
  id: number;
  username: string;
  email: string;
  roles: string[];
  active: boolean;
  full_name: string;
  phone: string;
  faculty_id: number | null;
};

export type AdminUserListResponse = {
  users: AdminUserRecord[];
  filters: {
    q: string;
    role: string;
    status: string;
    per_page: number;
    page: number;
  };
  all_roles: string[];
  faculties: FacultyReference[];
  stats: {
    total: number;
    active: number;
  };
  pagination: {
    total: number;
    page: number;
    per_page: number;
    page_count: number;
  };
};

export type AdminUserDetailResponse = {
  user: {
    id: number;
    username: string;
    full_name: string;
    phone: string;
    faculty_id: number | null;
    active: boolean;
    email: string;
    roles: string[];
  };
  all_roles: string[];
  faculties: FacultyReference[];
};

export type AdminLabRecord = {
  id: number;
  name: string;
  room: string;
  description: string;
  capacity: number;
  availability_note: string;
  safety_note: string;
  pic_name: string;
  pic_email: string;
  pic_phone: string;
  image: string;
  pic_image: string;
  image_url: string;
  pic_image_url: string;
  asset_total: number;
  assets_in_maintenance: number;
  faulty_assets: number;
  pic_account_linked: boolean;
  pic_account_has_role: boolean;
};

export type AdminLabListResponse = {
  labs: AdminLabRecord[];
  filters: {
    q: string;
    pic: string;
  };
  stats: {
    total_labs: number;
    assigned_pic: number;
    unassigned_pic: number;
  };
};

export type AdminLabDetailResponse = {
  lab: AdminLabRecord;
};

export type AdminAssetMaintenanceSummary = {
  id: number;
  title: string;
  status: string;
  status_label: string;
  priority: string;
  issue_type: string;
  quantity_affected: number;
  reported_by_name: string;
  technician_name: string;
  created_at: string;
  completed_at: string;
};

export type AdminAssetRecord = {
  id: number;
  lab_id: number;
  lab_name: string;
  lab_room: string;
  asset_code: string;
  name: string;
  category: string;
  brand: string;
  model: string;
  serial_number: string;
  specifications: string;
  status: string;
  location_note: string;
  purchase_date: string;
  quantity: number;
  total_quantity: number;
  maintenance_quantity: number;
  image: string;
  image_url: string;
  maintenance_total: number;
  maintenance_open: number;
  last_completed_at: string;
  last_reported_at: string;
};

export type AdminAssetListResponse = {
  assets: AdminAssetRecord[];
  labs: Array<{
    id: number;
    name: string;
    room: string;
    label: string;
  }>;
  filters: {
    q: string;
    lab_id: number;
    status: string;
  };
  status_options: string[];
};

export type AdminAssetDetailResponse = {
  asset: AdminAssetRecord;
  maintenance_history: AdminAssetMaintenanceSummary[];
  labs: Array<{
    id: number;
    name: string;
    room: string;
    label: string;
  }>;
};
