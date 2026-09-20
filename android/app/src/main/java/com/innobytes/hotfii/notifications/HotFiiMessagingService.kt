package com.innobytes.hotfii.notifications

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.innobytes.hotfii.HotFiiApplication

class HotFiiMessagingService : FirebaseMessagingService() {
    override fun onRegistered(installationId: String) {
        (application as HotFiiApplication).container.pushNotificationManager.registerToken(installationId)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        (application as HotFiiApplication).container.pushNotificationManager.show(message)
    }
}
