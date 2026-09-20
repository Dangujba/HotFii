package com.innobytes.hotfii.ui.workspace

import androidx.compose.foundation.Image
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Router
import androidx.compose.material.icons.outlined.ShoppingBag
import androidx.compose.material.icons.outlined.WifiTethering
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.R
import com.innobytes.hotfii.domain.DashboardDelta
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.FleetState
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.RouterSummary
import com.innobytes.hotfii.domain.UserSession
import java.text.NumberFormat
import java.util.Currency
import java.util.Locale

private data class Metric(
    val label: String,
    val value: String,
    val foot: String,
    val icon: ImageVector,
    val delta: DashboardDelta? = null,
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WorkspaceScreen(
    session: UserSession,
    selectedOrganization: OrganizationSummary?,
    dashboard: DashboardSnapshot?,
    selectedRouterId: String?,
    isRefreshing: Boolean,
    error: String?,
    dashboardError: String?,
    onOrganizationSelected: (String) -> Unit,
    onRouterSelected: (String?) -> Unit,
    onRefresh: () -> Unit,
    onDashboardRefresh: () -> Unit,
    onSignOut: () -> Unit,
) {
    var showSignOutConfirmation by remember { mutableStateOf(false) }

    if (showSignOutConfirmation) {
        AlertDialog(
            onDismissRequest = { showSignOutConfirmation = false },
            title = { Text("Sign out of HotFii?") },
            text = { Text("You will need to sign in again on this device.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showSignOutConfirmation = false
                        onSignOut()
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                ) {
                    Text("Sign out")
                }
            },
            dismissButton = {
                TextButton(onClick = { showSignOutConfirmation = false }) {
                    Text("Cancel")
                }
            },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        Image(
                            painter = painterResource(R.drawable.hotfii_icon),
                            contentDescription = null,
                            modifier = Modifier.size(34.dp),
                        )
                        Text("HotFii", fontWeight = FontWeight.Bold)
                    }
                },
                actions = {
                    IconButton(onClick = onRefresh, enabled = !isRefreshing) {
                        Icon(Icons.Outlined.Refresh, contentDescription = "Refresh account and dashboard")
                    }
                    TextButton(
                        onClick = { showSignOutConfirmation = true },
                        enabled = !isRefreshing,
                        colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                    ) {
                        Text("Sign out")
                    }
                },
            )
        },
    ) { contentPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(contentPadding),
            contentAlignment = Alignment.TopCenter,
        ) {
            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth()
                    .widthIn(max = 980.dp),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(
                    start = 16.dp,
                    top = 16.dp,
                    end = 16.dp,
                    bottom = 40.dp,
                ),
                verticalArrangement = Arrangement.spacedBy(18.dp),
            ) {
                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "Good to see you, ${session.user.name.substringBefore(' ')}",
                                style = MaterialTheme.typography.headlineSmall,
                                fontWeight = FontWeight.SemiBold,
                            )
                            Text(
                                text = "Live operations overview",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        if (isRefreshing) {
                            CircularProgressIndicator(modifier = Modifier.size(24.dp), strokeWidth = 2.dp)
                        }
                    }
                }

                item {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Organization", style = MaterialTheme.typography.labelLarge)
                        OrganizationSelector(
                            organizations = session.organizations,
                            selectedOrganization = selectedOrganization,
                            onSelected = onOrganizationSelected,
                        )
                    }
                }

                if (error != null) {
                    item { ErrorPanel(error, onRefresh) }
                }

                if (dashboardError != null) {
                    item { ErrorPanel(dashboardError, onDashboardRefresh) }
                }

                if (dashboard == null && dashboardError == null) {
                    item {
                        LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                    }
                }

                dashboard?.let { data ->
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Text("Router scope", style = MaterialTheme.typography.labelLarge)
                            RouterSelector(
                                routers = data.routers,
                                selectedRouterId = selectedRouterId,
                                onSelected = onRouterSelected,
                            )
                        }
                    }

                    if (data.alerts.billingSuspended) {
                        item {
                            AlertPanel(
                                "Billing action required",
                                "An overdue invoice is restricting new sales. Open Finance on the web to settle it.",
                                isError = true,
                            )
                        }
                    } else if (data.alerts.paymentProfileRequired) {
                        item {
                            AlertPanel(
                                "Online payments are not active",
                                "Routers, vouchers, plans, and direct sales still work. Activate the payment profile to accept portal payments.",
                            )
                        }
                    }

                    item {
                        MetricGrid(metrics(data))
                    }

                    item {
                        Section(
                            eyebrow = "LAST ${data.revenue.days} DAYS",
                            title = "Revenue collected",
                            trailing = formatMoney(data.revenue.totalKobo, data.currency),
                        ) {
                            if (data.revenue.totalKobo == 0L) {
                                EmptyState("Sales will chart here as they land.")
                            } else {
                                RevenueChart(data.revenue)
                            }
                        }
                    }

                    item {
                        Section(
                            eyebrow = "RIGHT NOW",
                            title = if (selectedRouterId == null) "Router fleet" else "Router status",
                            trailing = "${data.fleetTotal} total",
                        ) {
                            FleetList(data.fleet)
                        }
                    }

                    item {
                        Section(
                            eyebrow = "LAST ${data.topPlansDays} DAYS",
                            title = "Top plans by revenue",
                        ) {
                            if (data.topPlans.isEmpty()) {
                                EmptyState("No plan sales in this period.")
                            } else {
                                data.topPlans.asReversed().forEachIndexed { index, plan ->
                                    DetailRow(plan.name, formatMoney(plan.revenueKobo, data.currency))
                                    if (index < data.topPlans.lastIndex) HorizontalDivider()
                                }
                            }
                        }
                    }

                    item {
                        Section(
                            eyebrow = "TODAY",
                            title = "Session activity",
                            trailing = data.sessionsToday.peakHour?.let { "%02d:00 peak".format(it) },
                        ) {
                            DetailRow("Sessions started", data.sessionsToday.total.toString())
                            HorizontalDivider()
                            DetailRow("Active now", data.pulse.activeSessions.toString())
                        }
                    }

                    item {
                        Text(
                            "Network health",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                    if (data.networkHealth.isEmpty()) {
                        item { EmptyState("No routers have been added yet.") }
                    } else {
                        item {
                            Column {
                                data.networkHealth.forEachIndexed { index, device ->
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Icon(
                                        Icons.Outlined.Router,
                                        contentDescription = null,
                                        tint = statusColor(device.status),
                                        modifier = Modifier.size(24.dp),
                                    )
                                    Column(modifier = Modifier.weight(1f).padding(start = 16.dp)) {
                                        Text(device.name, fontWeight = FontWeight.SemiBold)
                                        Text(
                                            "${device.location} - ${device.vendor}",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    StatusText(device.status)
                                }
                                    if (index < data.networkHealth.lastIndex) {
                                        HorizontalDivider(modifier = Modifier.padding(start = 40.dp))
                                    }
                                }
                            }
                        }
                    }

                    item {
                        Text(
                            "Recent transactions",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.SemiBold,
                        )
                    }
                    if (data.recentTransactions.isEmpty()) {
                        item { EmptyState("Transactions will appear here.") }
                    } else {
                        item {
                            Column {
                                data.recentTransactions.forEachIndexed { index, transaction ->
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Icon(
                                        Icons.Outlined.Payments,
                                        contentDescription = null,
                                        tint = MaterialTheme.colorScheme.primary,
                                        modifier = Modifier.size(24.dp),
                                    )
                                    Column(modifier = Modifier.weight(1f).padding(start = 16.dp)) {
                                        Text(transaction.reference, fontWeight = FontWeight.SemiBold)
                                        Text(
                                            transaction.routerName ?: "Unattributed router",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(
                                            formatMoney(transaction.grossAmountKobo, data.currency),
                                            fontWeight = FontWeight.Bold,
                                        )
                                        Text(
                                            transaction.channel.displayLabel(),
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                }
                                    if (index < data.recentTransactions.lastIndex) {
                                        HorizontalDivider(modifier = Modifier.padding(start = 40.dp))
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun MetricGrid(metrics: List<Metric>) {
    BoxWithConstraints {
        val columns = if (maxWidth >= 700.dp) 3 else 2
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            metrics.chunked(columns).forEach { rowMetrics ->
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    rowMetrics.forEach { metric ->
                        MetricTile(metric, Modifier.weight(1f))
                    }
                    repeat(columns - rowMetrics.size) { Spacer(Modifier.weight(1f)) }
                }
            }
        }
    }
}

@Composable
private fun MetricTile(metric: Metric, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier.height(150.dp),
        shape = RoundedCornerShape(8.dp),
        tonalElevation = 1.dp,
        border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.SpaceBetween,
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    metric.label,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Icon(metric.icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            }
            Text(
                metric.value,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                metric.delta?.let {
                    Text(
                        it.text,
                        style = MaterialTheme.typography.labelSmall,
                        color = if (it.direction == "up") MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error,
                    )
                }
                Text(
                    metric.foot,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun Section(
    eyebrow: String,
    title: String,
    trailing: String? = null,
    content: @Composable () -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.Bottom,
        ) {
            Column {
                Text(
                    eyebrow,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.primary,
                )
                Text(title, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
            }
            trailing?.let { Text(it, fontWeight = FontWeight.Bold) }
        }
        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            content()
        }
    }
}

@Composable
private fun OrganizationSelector(
    organizations: List<OrganizationSummary>,
    selectedOrganization: OrganizationSummary?,
    onSelected: (String) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    SelectorButton(
        text = selectedOrganization?.name ?: "Choose organization",
        icon = Icons.Outlined.Business,
        expanded = expanded,
        onExpand = { expanded = true },
        onDismiss = { expanded = false },
    ) {
        organizations.forEach { organization ->
            DropdownMenuItem(
                text = {
                    Column {
                        Text(organization.name)
                        Text(
                            organization.role.displayLabel(),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                onClick = {
                    expanded = false
                    onSelected(organization.id)
                },
            )
        }
    }
}

@Composable
private fun RouterSelector(
    routers: List<RouterSummary>,
    selectedRouterId: String?,
    onSelected: (String?) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    val selected = routers.firstOrNull { it.id == selectedRouterId }
    SelectorButton(
        text = selected?.name ?: "All routers",
        icon = Icons.Outlined.Router,
        expanded = expanded,
        onExpand = { expanded = true },
        onDismiss = { expanded = false },
    ) {
        DropdownMenuItem(
            text = { Text("All routers") },
            onClick = {
                expanded = false
                onSelected(null)
            },
        )
        routers.forEach { router ->
            DropdownMenuItem(
                text = {
                    Column {
                        Text(router.name)
                        Text(
                            router.location ?: router.status.displayLabel(),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                onClick = {
                    expanded = false
                    onSelected(router.id)
                },
            )
        }
    }
}

@Composable
private fun SelectorButton(
    text: String,
    icon: ImageVector,
    expanded: Boolean,
    onExpand: () -> Unit,
    onDismiss: () -> Unit,
    content: @Composable () -> Unit,
) {
    Box {
        Surface(
            onClick = onExpand,
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(8.dp),
            border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(icon, contentDescription = null)
                Text(
                    text,
                    modifier = Modifier
                        .weight(1f)
                        .padding(horizontal = 10.dp),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Icon(Icons.Outlined.ExpandMore, contentDescription = null)
            }
        }
        DropdownMenu(expanded = expanded, onDismissRequest = onDismiss) { content() }
    }
}

@Composable
private fun FleetList(states: List<FleetState>) {
    states.forEachIndexed { index, state ->
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Box(
                    Modifier
                        .size(10.dp)
                        .border(3.dp, statusColor(state.key), RoundedCornerShape(5.dp)),
                )
                Text(state.label)
            }
            Text(state.value.toString(), fontWeight = FontWeight.Bold)
        }
        if (index < states.lastIndex) HorizontalDivider()
    }
}

@Composable
private fun ErrorPanel(message: String, onRetry: () -> Unit) {
    Surface(
        color = MaterialTheme.colorScheme.errorContainer,
        contentColor = MaterialTheme.colorScheme.onErrorContainer,
        shape = RoundedCornerShape(8.dp),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(message, modifier = Modifier.weight(1f))
            TextButton(onClick = onRetry) { Text("Retry") }
        }
    }
}

@Composable
private fun AlertPanel(title: String, message: String, isError: Boolean = false) {
    val color = if (isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer
    val contentColor = if (isError) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onSecondaryContainer
    Surface(color = color, contentColor = contentColor, shape = RoundedCornerShape(8.dp)) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(title, fontWeight = FontWeight.Bold)
            Text(message, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

@Composable
private fun EmptyState(message: String) {
    Text(
        message,
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 24.dp),
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
}

@Composable
private fun DetailRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
private fun StatusText(status: String) {
    Text(
        status.displayLabel(),
        style = MaterialTheme.typography.labelMedium,
        color = statusColor(status),
        fontWeight = FontWeight.SemiBold,
    )
}

@Composable
private fun statusColor(status: String) = when (status) {
    "online" -> MaterialTheme.colorScheme.primary
    "testing", "pending" -> MaterialTheme.colorScheme.secondary
    "offline", "failed" -> MaterialTheme.colorScheme.error
    else -> MaterialTheme.colorScheme.onSurfaceVariant
}

private fun metrics(data: DashboardSnapshot): List<Metric> = listOf(
    Metric(
        "Revenue today",
        formatMoney(data.pulse.revenueTodayKobo, data.currency),
        "vs ${formatMoney(data.pulse.revenueYesterdayKobo, data.currency)} yesterday",
        Icons.Outlined.Payments,
        data.pulse.revenueDelta,
    ),
    Metric(
        "Sales today",
        data.pulse.salesToday.toString(),
        "${data.pulse.salesYesterday} yesterday",
        Icons.Outlined.ShoppingBag,
        data.pulse.salesDelta,
    ),
    Metric(
        "Active sessions",
        data.pulse.activeSessions.toString(),
        "${data.pulse.sessionsStartedToday} started today",
        Icons.Outlined.WifiTethering,
        data.pulse.sessionsDelta,
    ),
    Metric(
        "Online routers",
        data.pulse.onlineRouters.toString(),
        "of ${data.pulse.totalRouters} in scope",
        Icons.Outlined.Router,
    ),
    Metric(
        "Available vouchers",
        data.pulse.availableVouchers.toString(),
        "${data.pulse.vouchersInUse} in use",
        Icons.Outlined.ConfirmationNumber,
    ),
)

private fun formatMoney(kobo: Long, currencyCode: String): String = runCatching {
    NumberFormat.getCurrencyInstance(Locale.forLanguageTag("en-NG")).apply {
        currency = Currency.getInstance(currencyCode)
        maximumFractionDigits = if (kobo % 100 == 0L) 0 else 2
    }.format(kobo / 100.0)
}.getOrElse { "${currencyCode} ${"%,.2f".format(kobo / 100.0)}" }

private fun String.displayLabel(): String =
    replace('_', ' ').replaceFirstChar { it.titlecase(Locale.getDefault()) }
