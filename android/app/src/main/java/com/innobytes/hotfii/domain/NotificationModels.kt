package com.innobytes.hotfii.domain

data class NotificationPreferences(
    val pushEnabled: Boolean = true,
    val routerAlerts: Boolean = true,
    val paymentAlerts: Boolean = true,
    val invoiceAlerts: Boolean = true,
    val accountAlerts: Boolean = true,
)

data class NotificationRecord(
    val id: String,
    val title: String,
    val message: String,
    val category: String,
    val url: String?,
    val isRead: Boolean,
    val createdAt: String?,
)

data class NotificationCatalog(
    val notifications: List<NotificationRecord>,
    val unreadCount: Int,
    val pagination: NetworkPagination,
    val preferences: NotificationPreferences,
    val categories: List<String>,
)
