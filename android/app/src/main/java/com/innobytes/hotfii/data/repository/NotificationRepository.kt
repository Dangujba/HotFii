package com.innobytes.hotfii.data.repository

import android.os.Build
import com.google.gson.Gson
import com.innobytes.hotfii.BuildConfig
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.MobileDeviceDeleteRequestDto
import com.innobytes.hotfii.data.network.dto.MobileDeviceRequestDto
import com.innobytes.hotfii.data.network.dto.NotificationPreferencesRequestDto
import com.innobytes.hotfii.data.network.dto.NotificationReadRequestDto
import com.innobytes.hotfii.data.security.SecureSessionStore
import com.innobytes.hotfii.domain.NotificationCatalog
import com.innobytes.hotfii.domain.NotificationPreferences
import java.io.IOException
import retrofit2.HttpException

interface NotificationRepository {
    suspend fun notifications(organizationId: String, category: String?, page: Int): NotificationCatalog
    suspend fun updatePreferences(organizationId: String, preferences: NotificationPreferences): NotificationPreferences
    suspend fun markRead(organizationId: String, notificationId: String? = null)
    suspend fun registerDevice(pushToken: String)
    suspend fun unregisterDevice()
}

class DefaultNotificationRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
    private val sessionStore: SecureSessionStore,
) : NotificationRepository {
    override suspend fun notifications(organizationId: String, category: String?, page: Int) = request {
        api.notifications(organizationId, category, page).data.toDomain()
    }

    override suspend fun updatePreferences(organizationId: String, preferences: NotificationPreferences) = request {
        api.updateNotificationPreferences(
            organizationId,
            NotificationPreferencesRequestDto(
                preferences.pushEnabled,
                preferences.routerAlerts,
                preferences.paymentAlerts,
                preferences.invoiceAlerts,
                preferences.accountAlerts,
            ),
        ).data.preferences.toDomain()
    }

    override suspend fun markRead(organizationId: String, notificationId: String?) = request {
        api.readNotifications(organizationId, NotificationReadRequestDto(notificationId))
        Unit
    }

    override suspend fun registerDevice(pushToken: String) = request {
        val response = api.registerDevice(
            MobileDeviceRequestDto(
                deviceId = sessionStore.deviceId(),
                deviceName = listOf(Build.MANUFACTURER, Build.MODEL)
                    .filter(String::isNotBlank)
                    .joinToString(" ")
                    .ifBlank { "Android" },
                pushToken = pushToken,
                appVersion = BuildConfig.VERSION_NAME,
                osVersion = Build.VERSION.RELEASE,
            ),
        )
        if (!response.isSuccessful) throw HttpException(response)
    }

    override suspend fun unregisterDevice() = request {
        api.unregisterDevice(MobileDeviceDeleteRequestDto(sessionStore.deviceId()))
    }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        val body = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        throw NetworkException(
            body?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: body?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again." else "The request could not be completed.",
            error,
        )
    } catch (error: IOException) {
        throw NetworkException("HotFii could not be reached. Check your connection and try again.", error)
    }
}
