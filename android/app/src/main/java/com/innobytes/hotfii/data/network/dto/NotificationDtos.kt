package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.NotificationCatalog
import com.innobytes.hotfii.domain.NotificationPreferences
import com.innobytes.hotfii.domain.NotificationRecord

data class NotificationPreferencesDto(
    val pushEnabled: Boolean,
    val routerAlerts: Boolean,
    val paymentAlerts: Boolean,
    val invoiceAlerts: Boolean,
    val accountAlerts: Boolean,
) {
    fun toDomain() = NotificationPreferences(pushEnabled, routerAlerts, paymentAlerts, invoiceAlerts, accountAlerts)
}

data class NotificationRecordDto(
    val id: String,
    val title: String,
    val message: String,
    val category: String,
    val url: String?,
    val isRead: Boolean,
    val createdAt: String?,
) {
    fun toDomain() = NotificationRecord(id, title, message, category, url, isRead, createdAt)
}

data class NotificationOptionsDto(val categories: List<String>)
data class NotificationCatalogDto(
    val notifications: List<NotificationRecordDto>,
    val unreadCount: Int,
    val pagination: NetworkPaginationDto,
    val preferences: NotificationPreferencesDto,
    val options: NotificationOptionsDto,
) {
    fun toDomain() = NotificationCatalog(
        notifications.map(NotificationRecordDto::toDomain),
        unreadCount,
        pagination.toDomain(),
        preferences.toDomain(),
        options.categories,
    )
}

data class NotificationPreferencesRequestDto(
    val pushEnabled: Boolean,
    val routerAlerts: Boolean,
    val paymentAlerts: Boolean,
    val invoiceAlerts: Boolean,
    val accountAlerts: Boolean,
)
data class NotificationPreferencesResponseDto(val preferences: NotificationPreferencesDto)
data class NotificationReadRequestDto(val notificationId: String? = null)
data class MobileDeviceRequestDto(
    val deviceId: String,
    val deviceName: String,
    val pushToken: String,
    val appVersion: String,
    val osVersion: String,
)
data class MobileDeviceDeleteRequestDto(val deviceId: String)
