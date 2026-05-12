# SLAMS Mobile Native App

This folder contains the real installed-app frontend for SLAMS Mobile.

## Architecture

- `../slams/` is the canonical CodeIgniter backend and deployment source of truth.
- `slams-mobile/` is the mobile workspace, with `native-app/` as the installed client.
- `native-app/` is an Expo React Native client.
- Authentication uses Shield access tokens through `POST /api/native/auth/token`.
- The app stores the bearer token in `expo-secure-store`.

## Standalone app behavior

- The final Android/iOS app is not intended to run through Expo Go.
- `npm start` uses an Expo development client, which is a native app build tied to this project.
- Installable standalone binaries come from:
  - Android preview APK builds
  - Android production AAB builds
  - iOS internal/TestFlight builds through EAS
- The checked-in `android/` project also supports direct local APK builds without Expo Go.

## First native slice

The current native client includes:

- token login, register, logout, and session restore
- optional biometric unlock of a saved native session
- role-aware home/dashboard summary
- laboratory list and detail
- full student/staff booking submission composer with PDF upload
- student/staff booking history and cancellation
- student/staff/PIC issue reporting
- PIC, manager, and admin approval queue with approve/reject actions
- PIC, manager, and admin external-request review queue with status updates
- PIC, manager, and admin report snapshot with protected PDF/CSV export
- technician maintenance queue, planning, transitions, and workflow logs
- external request list/create/update
- notification list, mark-read flows, and native push registration
- admin settings and booking-slot management
- admin user management with recovery-link actions
- protected PDF handoff to the device document viewer
- editable profile workflow for roles that the web system allows to self-manage

The native app still depends on the same backend and data rules as the main SLAMS web system. Push notifications require a real device plus an Expo project ID in the native environment or build config.

## Android push setup

The Android app package is `com.slams.nativeapp`. For Expo push notifications to work on Android, the native build must be connected to Firebase Cloud Messaging.

1. In Firebase, create or reuse an Android app with the package name `com.slams.nativeapp`.
2. Download `google-services.json`.
3. Put that file at `native-app/google-services.json`.
4. If you build directly with Gradle instead of letting Expo sync native config, also copy the same file to `native-app/android/app/google-services.json`.
5. Upload the Firebase service-account JSON for FCM V1 with `eas credentials` or in the Expo dashboard.
6. Rebuild and reinstall the Android app. An already-installed APK will not pick up Firebase config until it is rebuilt.

Without that Firebase setup, the notifications screen can request permission but Expo token registration will fail and native push will stay inactive.

## Local setup

1. Copy `.env.example` to `.env`.
2. Set `EXPO_PUBLIC_API_BASE_URL` to a reachable backend URL.
3. Set `EXPO_PUBLIC_EAS_PROJECT_ID` if you want to provide the Expo project ID explicitly for push registration.
4. Set `EXPO_PUBLIC_APP_VARIANT`.

For local development on the same LAN, `EXPO_PUBLIC_API_BASE_URL` is now treated as a preferred hint instead of a hard dependency. If the configured private IP is stale, the app will probe the current subnet and switch to the first backend that answers as the SLAMS native API.

Examples:

- Android emulator: `http://10.0.2.2:8080/slams/public` or your hosted HTTPS backend
- iOS simulator: `http://localhost:8080/slams/public`
- Physical device: `http://<your-lan-ip>/slams/public`

Recommended variants:

- local emulator/dev client: `development`
- internal installable QA APK/IPA: `preview`
- store/TestFlight production build: `production`

5. Start the backend from `../slams`.
6. In this folder run:

```bash
npm install
npm run android
npm start
```

`npm run android` builds and installs the native Android app on the emulator/device. `npm start` then serves Metro to the installed dev client. This flow does not require Expo Go.

## Standalone build commands

Installable Android builds:

```bash
npm run android:debug:apk
npm run install:android:debug

npm run android:release
npm run install:android:release
```

EAS standalone builds:

```bash
npm run build:android:preview
npm run build:android:production
npm run build:ios:preview
npm run build:ios:production
```

Production Android signing:

- Set `SLAMS_UPLOAD_STORE_FILE`
- Set `SLAMS_UPLOAD_STORE_PASSWORD`
- Set `SLAMS_UPLOAD_KEY_ALIAS`
- Set `SLAMS_UPLOAD_KEY_PASSWORD`

If those are not set, local `assembleRelease` still builds, but it falls back to the debug keystore and should be treated as QA-only.

## Verification

```bash
npm run typecheck
npx expo-doctor
npx expo export --platform android
```

The production build configuration now lives in `app.config.ts`, `eas.json`, and the Android Gradle release-signing block. Use [PRODUCTION_QA_CHECKLIST.md](PRODUCTION_QA_CHECKLIST.md) for the real-device release test pass.
