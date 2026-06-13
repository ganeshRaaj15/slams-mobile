export function getBookingDisplayStatus(booking: { status: string; approved_by_pic: boolean }): string {
  if (booking.status === 'PENDING') {
    return booking.approved_by_pic ? 'PENDING_MANAGER' : 'PENDING_PIC';
  }
  return booking.status;
}

export function getBookingStageSubtitle(booking: { status: string; approved_by_pic: boolean }): string | null {
  if (booking.status === 'PENDING') {
    return booking.approved_by_pic
      ? 'PIC approved. Sent to the Lab Manager for final approval.'
      : 'Waiting for the lab PIC to review';
  }
  return null;
}
