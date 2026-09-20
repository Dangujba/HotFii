# HotFii Firebase Push Setup

HotFii uses Firebase Cloud Messaging (FCM) HTTP v1. Notification history and preferences work without Firebase, but Android push delivery remains disabled until both the app and server receive the configuration below.

## 1. Firebase project

1. Create or select a Firebase project.
2. Add an Android application with package name `com.innobytes.hotfii`.
3. Enable the Firebase Cloud Messaging API (HTTP v1).
4. Create a service account with the Firebase Cloud Messaging API Admin role and download its JSON key.

Keep the service-account JSON outside this repository.

## 2. Android build

Set these Gradle properties or environment variables before building:

```text
HOTFII_FIREBASE_PROJECT_ID=
HOTFII_FIREBASE_APPLICATION_ID=
HOTFII_FIREBASE_API_KEY=
HOTFII_FIREBASE_SENDER_ID=
```

The values are shown in Firebase project settings. The app initializes Firebase programmatically, so `google-services.json` is not required.

## 3. Laravel server

Set the project ID and either a readable credentials path or a base64-encoded JSON key:

```text
FIREBASE_PROJECT_ID=
FIREBASE_CREDENTIALS=/run/secrets/firebase-service-account.json
FIREBASE_CREDENTIALS_JSON=
```

For the current container deployment, the base64 option avoids a secret-file mount:

```bash
base64 -w 0 firebase-service-account.json
```

Put that output in `FIREBASE_CREDENTIALS_JSON` in the production `.env`, then rebuild/restart the app and notification queue workers. Never put it in Git or a Gradle property.

## 4. Verification

1. Install an APK built with the four Android Firebase values.
2. Sign in and allow Android notification permission.
3. Open **More > Notifications** and enable the desired categories.
4. Confirm the `mobile_devices` record has a registration hash and a private Firebase Installation ID.
5. Trigger a test alert and confirm it appears both in notification history and the Android system tray.

Invalid or unregistered Firebase Installation IDs are cleared automatically. Signing out removes the current device registration.
