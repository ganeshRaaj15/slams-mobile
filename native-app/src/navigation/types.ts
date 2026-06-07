export type RootStackParamList = {
  Auth: undefined;
  Register: undefined;
  Main: undefined;
  LabDetail: { labId: number };
  BookingDetail: { bookingId: number };
  BookingEdit: { bookingId: number };
  BookingComposer: {
    labId: number;
    preselectedServiceId?: number;
    preselectedAssetId?: number;
    preselectedAssetQty?: number;
    preselectedDate?: string;
    preselectedStartTime?: string;
    preselectedEndTime?: string;
    source?: 'manual' | 'qr';
  };
  ApprovalDetail: { bookingId: number };
  ExternalRequestReviewDetail: { requestId: number };
  MaintenanceForm: { maintenanceId?: number; assetId?: number };
  Reservations: undefined;
  ReservationForm: { reservationId?: number };
  RequestForm: {
    labId?: number;
    requestId?: number;
    preselectedDate?: string;
    preselectedStartTime?: string;
    preselectedEndTime?: string;
  };
  Reports: undefined;
  AdminWorkspace: undefined;
  AdminUsers: undefined;
  AdminUserEditor: { userId?: number };
  AdminSettings: undefined;
  AdminLabs: undefined;
  AdminLabEditor: { labId?: number };
  AdminAssets: undefined;
  AdminAssetEditor: { assetId?: number };
};

export type MainTabParamList = {
  Home: undefined;
  Labs: undefined;
  Bookings: undefined;
  Approvals: undefined;
  Issues: undefined;
  Maintenance: undefined;
  Reservations: undefined;
  Requests: undefined;
  Reports: undefined;
  AdminWorkspace: undefined;
  Notifications: undefined;
  Profile: undefined;
};
