# Native Production QA Checklist

This app is not fully production-ready until these checks are run on a real Android device and a production backend.

## Release configuration

- `EXPO_PUBLIC_API_BASE_URL` points to the production HTTPS backend.
- `EXPO_PUBLIC_EAS_PROJECT_ID` is set for the Expo project used for push notifications.
- `eas.json` is present and the intended build profile is selected.
- The backend accepts token-authenticated requests over HTTPS.

## Authentication

- Sign in with `student`, `external`, `pic`, `manager`, `admin`, and `technician` accounts.
- Force an expired token and confirm the app returns to the login screen cleanly.
- Confirm sign-out revokes the device session and clears native push registration.

## Student and staff flows

- Browse laboratories and open a lab detail page.
- Create a booking with a required PDF attachment.
- Verify slot conflict feedback.
- Verify pending, approved, rejected, cancelled, and completed bookings render correctly.
- Open the protected booking PDF from booking history.
- Submit an issue report with and without a photo.

## External flow

- Create a new external request.
- Edit a request in `submitted` and `needs_information` states.
- Verify status tracking updates after reviewer changes.

## PIC, manager, and admin operational flows

- Open approval queue and approve a booking.
- Reject a booking and confirm downstream status updates.
- Open external-request review queue and move requests between statuses.
- Open report snapshot and export PDF/CSV.
- Confirm push notifications deep-link into approvals and external requests correctly.

## Technician flow

- Create a maintenance case.
- Progress it through `scheduled`, `in_progress`, `testing`, and `completed`.
- Confirm unit-quantity validation and asset availability updates.

## Admin-native flow

- Open admin workspace.
- Update managed settings.
- Update booking slots with valid and invalid overlaps.
- Create a user, edit a user, send a recovery link, and delete a safe-to-delete user.

## Device behavior

- Confirm notifications permission prompt behavior.
- Confirm native push delivery on a real device.
- Confirm offline/poor-network behavior for login, list views, and form submissions.
- Confirm protected PDF export opens a device viewer.
- Confirm dark mode and light mode both render correctly.

## Build verification

- Run `npm run typecheck`.
- Run `npx expo-doctor`.
- Run `npx expo export --platform android`.
- Build an APK or AAB using EAS and install it on a real device.
