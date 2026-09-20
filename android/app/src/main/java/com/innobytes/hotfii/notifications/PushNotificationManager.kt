package com.innobytes.hotfii.notifications

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.RemoteMessage
import com.innobytes.hotfii.BuildConfig
import com.innobytes.hotfii.MainActivity
import com.innobytes.hotfii.R
import com.innobytes.hotfii.data.repository.NotificationRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

class PushNotificationManager(
    private val context: Context,
    private val repository: NotificationRepository,
) {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    fun initialize() {
        createChannels()
        if (!configured() || FirebaseApp.getApps(context).isNotEmpty()) return
        FirebaseApp.initializeApp(
            context,
            FirebaseOptions.Builder()
                .setProjectId(BuildConfig.FIREBASE_PROJECT_ID)
                .setApplicationId(BuildConfig.FIREBASE_APPLICATION_ID)
                .setApiKey(BuildConfig.FIREBASE_API_KEY)
                .setGcmSenderId(BuildConfig.FIREBASE_SENDER_ID)
                .build(),
        )
    }

    fun syncRegistration() {
        if (!configured() || FirebaseApp.getApps(context).isEmpty()) return
        FirebaseMessaging.getInstance().register()
            .addOnFailureListener { Log.w(TAG, "Push registration could not be synchronized.", it) }
    }

    fun registerToken(token: String) {
        scope.launch {
            runCatching { repository.registerDevice(token) }
                .onFailure { Log.w(TAG, "Push token could not be registered.", it) }
        }
    }

    fun show(message: RemoteMessage) {
        val category = message.data["category"] ?: "account"
        val title = message.notification?.title ?: message.data["title"] ?: "HotFii"
        val body = message.notification?.body ?: message.data["message"] ?: return
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra(EXTRA_SCREEN, message.data["screen"] ?: "notifications")
        }
        val pendingIntent = PendingIntent.getActivity(
            context,
            message.messageId?.hashCode() ?: System.currentTimeMillis().toInt(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU
            && ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            return
        }
        NotificationManagerCompat.from(context).notify(
            message.messageId?.hashCode() ?: System.currentTimeMillis().toInt(),
            NotificationCompat.Builder(context, "hotfii_$category")
                .setSmallIcon(R.drawable.hotfii_icon)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(body))
                .setAutoCancel(true)
                .setContentIntent(pendingIntent)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .build(),
        )
    }

    fun configured(): Boolean = listOf(
        BuildConfig.FIREBASE_PROJECT_ID,
        BuildConfig.FIREBASE_APPLICATION_ID,
        BuildConfig.FIREBASE_API_KEY,
        BuildConfig.FIREBASE_SENDER_ID,
    ).all(String::isNotBlank)

    private fun createChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(NotificationManager::class.java)
        manager.createNotificationChannels(
            listOf(
                NotificationChannel("hotfii_router", "Router alerts", NotificationManager.IMPORTANCE_HIGH),
                NotificationChannel("hotfii_payment", "Payment alerts", NotificationManager.IMPORTANCE_DEFAULT),
                NotificationChannel("hotfii_invoice", "Invoice alerts", NotificationManager.IMPORTANCE_HIGH),
                NotificationChannel("hotfii_account", "Account alerts", NotificationManager.IMPORTANCE_HIGH),
            ),
        )
    }

    companion object {
        const val EXTRA_SCREEN = "hotfii_notification_screen"
        private const val TAG = "HotFiiPush"
    }
}
