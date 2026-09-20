package com.innobytes.hotfii.ui.notifications

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.NotificationPreferences
import com.innobytes.hotfii.domain.NotificationRecord
import com.innobytes.hotfii.ui.NotificationUiState
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationScreen(
    organizationId: String?,
    organizationName: String?,
    state: NotificationUiState,
    onBack: () -> Unit,
    onLoad: (String?, Int) -> Unit,
    onUpdatePreferences: (NotificationPreferences) -> Unit,
    onMarkRead: (String?) -> Unit,
    onFeedbackDismissed: () -> Unit,
    onRequestPermission: () -> Unit,
    pushAvailable: Boolean,
) {
    BackHandler(onBack = onBack)
    LaunchedEffect(organizationId) {
        if (organizationId != null) {
            if (pushAvailable) onRequestPermission()
            onLoad(null, 1)
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Notifications")
                        organizationName?.let { Text(it, style = MaterialTheme.typography.labelMedium) }
                    }
                },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") } },
                actions = {
                    IconButton(
                        onClick = { onMarkRead(null) },
                        enabled = !state.isActionRunning && (state.catalog?.unreadCount ?: 0) > 0,
                    ) { Icon(Icons.Outlined.DoneAll, "Mark all read") }
                    IconButton(
                        onClick = { onLoad(state.category, state.catalog?.pagination?.currentPage ?: 1) },
                        enabled = !state.isLoading,
                    ) { Icon(Icons.Outlined.Refresh, "Refresh") }
                },
            )
        },
    ) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding), contentPadding = PaddingValues(bottom = 28.dp)) {
            if (state.isLoading || state.isActionRunning) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            state.error?.let { item { Feedback(it, true, onFeedbackDismissed) } }
            state.notice?.let { item { Feedback(it, false, onFeedbackDismissed) } }

            state.catalog?.let { catalog ->
                item { SectionLabel("Push alerts") }
                item {
                    PreferenceRow(
                        "Allow push notifications",
                        if (pushAvailable) "Receive selected alerts on this device" else "Push setup is unavailable in this build",
                        Icons.Outlined.NotificationsActive,
                        catalog.preferences.pushEnabled,
                        pushAvailable && !state.isActionRunning,
                    ) { onUpdatePreferences(catalog.preferences.copy(pushEnabled = it)) }
                }
                item { HorizontalDivider(Modifier.padding(start = 56.dp)) }
                item {
                    PreferenceRow("Router status", "Offline and network attention alerts", Icons.Outlined.Router, catalog.preferences.routerAlerts, catalog.preferences.pushEnabled && !state.isActionRunning) {
                        onUpdatePreferences(catalog.preferences.copy(routerAlerts = it))
                    }
                }
                item { HorizontalDivider(Modifier.padding(start = 56.dp)) }
                item {
                    PreferenceRow("Payments", "Confirmed hotspot payments", Icons.Outlined.Payments, catalog.preferences.paymentAlerts, catalog.preferences.pushEnabled && !state.isActionRunning) {
                        onUpdatePreferences(catalog.preferences.copy(paymentAlerts = it))
                    }
                }
                item { HorizontalDivider(Modifier.padding(start = 56.dp)) }
                item {
                    PreferenceRow("Invoices", "New, overdue, and settled invoices", Icons.Outlined.ReceiptLong, catalog.preferences.invoiceAlerts, catalog.preferences.pushEnabled && !state.isActionRunning) {
                        onUpdatePreferences(catalog.preferences.copy(invoiceAlerts = it))
                    }
                }
                item { HorizontalDivider(Modifier.padding(start = 56.dp)) }
                item {
                    PreferenceRow("Account", "Important account and access changes", Icons.Outlined.AdminPanelSettings, catalog.preferences.accountAlerts, catalog.preferences.pushEnabled && !state.isActionRunning) {
                        onUpdatePreferences(catalog.preferences.copy(accountAlerts = it))
                    }
                }

                item { SectionLabel("History") }
                item {
                    Row(
                        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 12.dp, vertical = 6.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        FilterChip(selected = state.category == null, onClick = { onLoad(null, 1) }, label = { Text("All") })
                        catalog.categories.forEach { category ->
                            FilterChip(
                                selected = state.category == category,
                                onClick = { onLoad(category, 1) },
                                label = { Text(category.replaceFirstChar(Char::uppercase)) },
                            )
                        }
                    }
                }

                if (catalog.notifications.isEmpty() && !state.isLoading) {
                    item { Text("No notifications match this view.", Modifier.padding(32.dp), color = MaterialTheme.colorScheme.onSurfaceVariant) }
                } else {
                    catalog.notifications.forEach { notification ->
                        item(key = notification.id) {
                            NotificationRow(notification) { if (!notification.isRead) onMarkRead(notification.id) }
                        }
                    }
                }

                if (catalog.pagination.lastPage > 1) {
                    item {
                        Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            IconButton(
                                onClick = { onLoad(state.category, catalog.pagination.currentPage - 1) },
                                enabled = catalog.pagination.currentPage > 1 && !state.isLoading,
                            ) { Icon(Icons.Outlined.ChevronLeft, "Previous page") }
                            Text("${catalog.pagination.currentPage} of ${catalog.pagination.lastPage}", Modifier.weight(1f), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                            IconButton(
                                onClick = { onLoad(state.category, catalog.pagination.currentPage + 1) },
                                enabled = catalog.pagination.currentPage < catalog.pagination.lastPage && !state.isLoading,
                            ) { Icon(Icons.Outlined.ChevronRight, "Next page") }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun PreferenceRow(title: String, subtitle: String, icon: ImageVector, checked: Boolean, enabled: Boolean, onChange: (Boolean) -> Unit) {
    ListItem(
        headlineContent = { Text(title) },
        supportingContent = { Text(subtitle) },
        leadingContent = { Icon(icon, null, tint = MaterialTheme.colorScheme.primary) },
        trailingContent = { Switch(checked = checked, onCheckedChange = onChange, enabled = enabled) },
    )
}

@Composable
private fun NotificationRow(notification: NotificationRecord, onClick: () -> Unit) {
    Surface(
        color = if (notification.isRead) MaterialTheme.colorScheme.surface else MaterialTheme.colorScheme.secondaryContainer.copy(alpha = 0.35f),
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
    ) {
        ListItem(
            colors = ListItemDefaults.colors(containerColor = androidx.compose.ui.graphics.Color.Transparent),
            headlineContent = { Text(notification.title, fontWeight = if (notification.isRead) FontWeight.Normal else FontWeight.Bold) },
            supportingContent = {
                Column {
                    Text(notification.message)
                    notification.createdAt?.let { Text(formatDate(it), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                }
            },
            leadingContent = { Icon(categoryIcon(notification.category), null, tint = MaterialTheme.colorScheme.primary) },
            trailingContent = { if (!notification.isRead) Icon(Icons.Outlined.Circle, "Unread", Modifier.size(10.dp), tint = MaterialTheme.colorScheme.primary) },
        )
    }
    HorizontalDivider(Modifier.padding(start = 56.dp))
}

private fun categoryIcon(category: String): ImageVector = when (category) {
    "router" -> Icons.Outlined.Router
    "payment" -> Icons.Outlined.Payments
    "invoice" -> Icons.Outlined.ReceiptLong
    else -> Icons.Outlined.AdminPanelSettings
}

@Composable private fun SectionLabel(text: String) {
    Text(text, Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 22.dp, bottom = 8.dp), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
}

@Composable private fun Feedback(message: String, error: Boolean, dismiss: () -> Unit) {
    Surface(color = if (error) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer) {
        Row(Modifier.fillMaxWidth().padding(start = 16.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(message, Modifier.weight(1f))
            IconButton(dismiss) { Icon(Icons.Outlined.Close, "Dismiss") }
        }
    }
}

private fun formatDate(value: String): String = runCatching {
    OffsetDateTime.parse(value).format(DateTimeFormatter.ofPattern("d MMM, HH:mm"))
}.getOrDefault(value)
