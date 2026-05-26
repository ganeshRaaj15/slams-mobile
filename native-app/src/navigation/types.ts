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
    source?: 'manual' | 'qr';
  };
  ApprovalDetail: { bookingId: number };
  ExternalRequestReviewDetail: { requestId: number };
  MaintenanceForm: { maintenanceId?: number };
  RequestForm: { labId?: number; requestId?: number };
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
  Requests: undefined;
  Reports: undefined;
  AdminWorkspace: undefined;
  Notifications: undefined;
  Profile: undefined;
};
