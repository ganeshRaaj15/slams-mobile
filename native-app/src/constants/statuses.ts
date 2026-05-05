export const BOOKING_STATUS_LABELS: Record<string, string> = {
  PENDING: 'Pending',
  APPROVED: 'Approved',
  REJECTED: 'Rejected',
  CANCELLED: 'Cancelled',
};

export const EXTERNAL_REQUEST_STATUS_LABELS: Record<string, string> = {
  submitted: 'Submitted',
  under_review: 'Under Review',
  needs_information: 'Needs Information',
  approved_for_scheduling: 'Approved For Scheduling',
  rejected: 'Rejected',
  completed: 'Completed',
};

export const MAINTENANCE_STATUS_LABELS: Record<string, string> = {
  reported: 'Reported',
  scheduled: 'Scheduled',
  in_progress: 'Repair In Progress',
  testing: 'Testing And Verification',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

export const ASSET_STATUS_LABELS: Record<string, string> = {
  available: 'Available',
  maintenance: 'Maintenance',
  faulty: 'Faulty',
};
