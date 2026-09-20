package com.innobytes.hotfii.ui.network

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.*
import com.innobytes.hotfii.ui.NetworkUiState
import com.innobytes.hotfii.ui.components.DateFilterField
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

private enum class NetworkSection { Routers, Sessions }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NetworkScreen(
    organizationId: String?,
    organizationName: String?,
    state: NetworkUiState,
    onLoadRouters: (NetworkFilters, Int) -> Unit,
    onOpenRouter: (String) -> Unit,
    onCloseRouter: () -> Unit,
    onRunRouterTests: (String) -> Unit,
    onLoadSessions: (HotspotSessionFilters, Int) -> Unit,
    onOpenSession: (HotspotSessionRecord) -> Unit,
    onCloseSession: () -> Unit,
    onDisconnectSession: (String) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var section by rememberSaveable { mutableStateOf(NetworkSection.Routers) }
    var showFilters by rememberSaveable { mutableStateOf(false) }

    LaunchedEffect(organizationId) {
        if (organizationId != null) onLoadRouters(NetworkFilters(), 1)
    }
    LaunchedEffect(section, organizationId) {
        if (section == NetworkSection.Sessions && organizationId != null && state.sessionCatalog == null) {
            onLoadSessions(state.sessionFilters, 1)
        }
    }

    state.routerDetail?.let { detail ->
        BackHandler(onBack = onCloseRouter)
        RouterDetailScreen(
            detail,
            state.isLoading,
            state.isActionRunning,
            state.error,
            state.notice,
            onCloseRouter,
            { onOpenRouter(detail.router.summary.id) },
            { onRunRouterTests(detail.router.summary.id) },
            onFeedbackDismissed,
        )
        return
    }
    state.selectedSession?.let { session ->
        BackHandler(onBack = onCloseSession)
        SessionDetailScreen(
            session,
            state.isActionRunning,
            state.error,
            state.notice,
            onCloseSession,
            onDisconnectSession,
            onFeedbackDismissed,
        )
        return
    }
    if (showFilters) {
        if (section == NetworkSection.Routers) {
            RouterFilterDialog(state.filters, state.catalog, { showFilters = false }) {
                showFilters = false
                onLoadRouters(it, 1)
            }
        } else {
            SessionFilterDialog(state.sessionFilters, state.sessionCatalog, { showFilters = false }) {
                showFilters = false
                onLoadSessions(it, 1)
            }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Network", fontWeight = FontWeight.Bold)
                        organizationName?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    }
                },
                actions = {
                    IconButton(onClick = { showFilters = true }) { Icon(Icons.Outlined.FilterList, "Filter") }
                    IconButton(onClick = {
                        if (section == NetworkSection.Routers) {
                            onLoadRouters(state.filters, state.catalog?.pagination?.currentPage ?: 1)
                        } else {
                            onLoadSessions(state.sessionFilters, state.sessionCatalog?.pagination?.currentPage ?: 1)
                        }
                    }) { Icon(Icons.Outlined.Refresh, "Refresh") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            TabRow(section.ordinal) {
                Tab(section == NetworkSection.Routers, { section = NetworkSection.Routers }, text = { Text("Routers") })
                Tab(section == NetworkSection.Sessions, { section = NetworkSection.Sessions }, text = { Text("Sessions") })
            }
            if (state.isLoading || state.isActionRunning) LinearProgressIndicator(Modifier.fillMaxWidth())
            state.error?.let { FeedbackPanel(it, true, onFeedbackDismissed) }
            state.notice?.let { FeedbackPanel(it, false, onFeedbackDismissed) }
            if (section == NetworkSection.Routers) {
                RouterContent(state, onLoadRouters, onOpenRouter)
            } else {
                SessionContent(state, onLoadSessions, onOpenSession)
            }
        }
    }
}

@Composable
private fun RouterContent(
    state: NetworkUiState,
    onLoad: (NetworkFilters, Int) -> Unit,
    onOpen: (String) -> Unit,
) {
    val catalog = state.catalog
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (catalog == null) {
            if (!state.isLoading) item { EmptyRow("No router data loaded.") }
        } else {
            item {
                Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Metric("Online", catalog.summary.online.toString(), Icons.Outlined.Wifi, Modifier.weight(1f))
                    Metric("Offline", catalog.summary.offline.toString(), Icons.Outlined.WifiOff, Modifier.weight(1f))
                    Metric("Attention", catalog.summary.attention.toString(), Icons.Outlined.WarningAmber, Modifier.weight(1f))
                }
            }
            if (catalog.routers.isEmpty()) item { EmptyRow("No routers match this view.") }
            itemsIndexed(catalog.routers, key = { _, router -> router.id }) { index, router ->
                RouterRow(router) { onOpen(router.id) }
                if (index < catalog.routers.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
            }
            item { PaginationRow(catalog.pagination) { onLoad(state.filters, it) } }
        }
    }
}

@Composable
private fun RouterRow(router: NetworkRouter, onClick: () -> Unit) {
    ListItem(
        headlineContent = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(router.name, Modifier.weight(1f), fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                StatusText(router.status)
            }
        },
        supportingContent = {
            Column {
                Text(listOfNotNull(router.vendorLabel, router.model).joinToString(" | "))
                Text(
                    listOfNotNull(router.locationName, router.activeSessionsCount.toString() + " live", heartbeat(router.lastHeartbeatAt)).joinToString(" | "),
                    style = MaterialTheme.typography.bodySmall,
                )
            }
        },
        leadingContent = { CircleIcon(Icons.Outlined.Router) },
        trailingContent = { Icon(Icons.Outlined.ChevronRight, null) },
        modifier = Modifier.clickable(onClick = onClick),
    )
}

@Composable
private fun SessionContent(
    state: NetworkUiState,
    onLoad: (HotspotSessionFilters, Int) -> Unit,
    onOpen: (HotspotSessionRecord) -> Unit,
) {
    val catalog = state.sessionCatalog
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (catalog == null) {
            if (!state.isLoading) item { EmptyRow("No session data loaded.") }
        } else {
            item {
                Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Metric("Live", catalog.summary.live.toString(), Icons.Outlined.CellWifi, Modifier.weight(1f))
                    Metric("Recent", catalog.summary.recent.toString(), Icons.Outlined.History, Modifier.weight(1f))
                    Metric("Usage", dataSize(catalog.summary.totalUsageBytes), Icons.Outlined.DataUsage, Modifier.weight(1f))
                }
            }
            item {
                val live = state.sessionFilters.view == "live"
                TabRow(if (live) 0 else 1) {
                    Tab(live, { onLoad(state.sessionFilters.copy(view = "live", status = null), 1) }, text = { Text("Live") })
                    Tab(!live, { onLoad(state.sessionFilters.copy(view = "recent", status = null), 1) }, text = { Text("Recent") })
                }
            }
            if (catalog.sessions.isEmpty()) item { EmptyRow("No sessions match this view.") }
            itemsIndexed(catalog.sessions, key = { _, session -> session.id }) { index, session ->
                SessionRow(session) { onOpen(session) }
                if (index < catalog.sessions.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
            }
            item { PaginationRow(catalog.pagination) { onLoad(state.sessionFilters, it) } }
        }
    }
}

@Composable
private fun SessionRow(session: HotspotSessionRecord, onClick: () -> Unit) {
    ListItem(
        headlineContent = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(session.customerName ?: session.username, Modifier.weight(1f), fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                StatusText(session.status)
            }
        },
        supportingContent = {
            Column {
                Text(listOfNotNull(session.planName, session.routerName).joinToString(" | "))
                Text(
                    listOfNotNull(session.clientName ?: session.macAddress, session.ipAddress, dataSize(session.totalBytes), shortDate(session.startedAt)).joinToString(" | "),
                    style = MaterialTheme.typography.bodySmall,
                    maxLines = 2,
                )
            }
        },
        leadingContent = { CircleIcon(if (session.status == "active") Icons.Outlined.WifiTethering else Icons.Outlined.History) },
        trailingContent = { Icon(Icons.Outlined.ChevronRight, null) },
        modifier = Modifier.clickable(onClick = onClick),
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun RouterDetailScreen(
    detail: NetworkRouterDetail,
    isLoading: Boolean,
    isActionRunning: Boolean,
    error: String?,
    notice: String?,
    onBack: () -> Unit,
    onRefresh: () -> Unit,
    onRunTests: () -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    val router = detail.router
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(router.summary.name, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") } },
                actions = {
                    if (detail.permissions.canManage) {
                        IconButton(onClick = onRunTests, enabled = !isActionRunning) { Icon(Icons.Outlined.FactCheck, "Run readiness tests") }
                    }
                    IconButton(onClick = onRefresh) { Icon(Icons.Outlined.Refresh, "Refresh") }
                },
            )
        },
    ) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding), contentPadding = PaddingValues(bottom = 28.dp)) {
            if (isLoading || isActionRunning) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            error?.let { item { FeedbackPanel(it, true, onFeedbackDismissed) } }
            notice?.let { item { FeedbackPanel(it, false, onFeedbackDismissed) } }
            item {
                ListItem(
                    headlineContent = { Text(router.summary.vendorLabel + (router.summary.model?.let { " | $it" } ?: ""), fontWeight = FontWeight.SemiBold) },
                    supportingContent = { Text(listOfNotNull(router.summary.locationName, router.firmwareVersion, router.managementAddress).joinToString("\n")) },
                    leadingContent = { CircleIcon(Icons.Outlined.Router) },
                    trailingContent = { StatusText(router.summary.status) },
                )
            }
            item {
                Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Metric("Live", router.summary.activeSessionsCount.toString(), Icons.Outlined.CellWifi, Modifier.weight(1f))
                    Metric("All sessions", router.summary.sessionsCount.toString(), Icons.Outlined.History, Modifier.weight(1f))
                }
            }
            item { SectionLabel("Setup") }
            item { DetailRow("State", router.setup.label) }
            item { DetailRow("NAS identifier", router.nasIdentifier) }
            item { DetailRow("Adapter", router.summary.adapter) }
            item { DetailRow("Support", router.summary.supportLevel.replaceFirstChar(Char::uppercase)) }
            item { DetailRow("Last heartbeat", heartbeat(router.summary.lastHeartbeatAt) ?: "Never") }
            if (router.capabilities.isNotEmpty()) item { DetailRow("Capabilities", router.capabilities.joinToString(", ") { it.replace('_', ' ') }) }
            if (router.health.isNotEmpty()) {
                item { SectionLabel("Health") }
                router.health.forEach { (key, value) -> item { DetailRow(key.replace('_', ' ').replaceFirstChar(Char::uppercase), value) } }
            }
            item { SectionLabel("Readiness tests") }
            if (detail.tests.isEmpty()) item { EmptyRow("No readiness test run yet.") }
            itemsIndexed(detail.tests, key = { _, test -> test.key }) { index, test ->
                ListItem(
                    headlineContent = { Text(test.key.replace('_', ' ').replaceFirstChar(Char::uppercase), fontWeight = FontWeight.SemiBold) },
                    supportingContent = { Text(listOfNotNull(test.message, shortDate(test.checkedAt)).joinToString(" | ")) },
                    leadingContent = { TestIcon(test.status) },
                    trailingContent = { StatusText(test.status) },
                )
                if (index < detail.tests.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SessionDetailScreen(
    session: HotspotSessionRecord,
    isActionRunning: Boolean,
    error: String?,
    notice: String?,
    onBack: () -> Unit,
    onDisconnect: (String) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var confirmDisconnect by rememberSaveable { mutableStateOf(false) }
    if (confirmDisconnect) {
        AlertDialog(
            onDismissRequest = { if (!isActionRunning) confirmDisconnect = false },
            title = { Text("Disconnect this session?") },
            text = { Text("HotFii will ask ${session.routerName ?: "the router"} to disconnect ${session.customerName ?: session.username}. Remaining plan time is not refunded.") },
            confirmButton = {
                TextButton(onClick = { confirmDisconnect = false; onDisconnect(session.id) }, enabled = !isActionRunning) {
                    Text("Disconnect", color = MaterialTheme.colorScheme.error)
                }
            },
            dismissButton = { TextButton(onClick = { confirmDisconnect = false }, enabled = !isActionRunning) { Text("Cancel") } },
        )
    }
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(session.customerName ?: session.username, fontWeight = FontWeight.Bold) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") } },
            )
        },
    ) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding), contentPadding = PaddingValues(bottom = 28.dp)) {
            if (isActionRunning) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            error?.let { item { FeedbackPanel(it, true, onFeedbackDismissed) } }
            notice?.let { item { FeedbackPanel(it, false, onFeedbackDismissed) } }
            item {
                ListItem(
                    headlineContent = { Text(session.planName ?: "Access session", fontWeight = FontWeight.SemiBold) },
                    supportingContent = { Text(listOfNotNull(session.routerName, session.clientName).joinToString(" | ")) },
                    leadingContent = { CircleIcon(Icons.Outlined.WifiTethering) },
                    trailingContent = { StatusText(session.status) },
                )
            }
            item { SectionLabel("Connection") }
            item { DetailRow("Username", session.username) }
            session.customerPhone?.let { item { DetailRow("Phone", it) } }
            item { DetailRow("Router", session.routerName ?: "Unknown") }
            item { DetailRow("Device", session.clientName ?: "Unknown") }
            item { DetailRow("MAC address", session.macAddress ?: "Unknown") }
            item { DetailRow("IP address", session.ipAddress ?: "Unknown") }
            item { SectionLabel("Usage and time") }
            item { DetailRow("Downloaded", dataSize(session.outputBytes)) }
            item { DetailRow("Uploaded", dataSize(session.inputBytes)) }
            item { DetailRow("Total usage", dataSize(session.totalBytes)) }
            item { DetailRow("Started", shortDate(session.startedAt) ?: "Unknown") }
            session.expiresAt?.let { item { DetailRow("Expires", shortDate(it) ?: it) } }
            session.stoppedAt?.let { item { DetailRow("Stopped", shortDate(it) ?: it) } }
            session.terminateCause?.let { item { DetailRow("Ended by", it.replace('-', ' ')) } }
            if (session.canDisconnect) {
                item {
                    Button(
                        onClick = { confirmDisconnect = true },
                        enabled = !isActionRunning,
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error),
                        modifier = Modifier.fillMaxWidth().padding(16.dp),
                    ) {
                        Icon(Icons.Outlined.LinkOff, null)
                        Spacer(Modifier.width(8.dp))
                        Text("Disconnect session")
                    }
                }
            }
        }
    }
}

@Composable
private fun RouterFilterDialog(
    filters: NetworkFilters,
    catalog: NetworkCatalog?,
    onDismiss: () -> Unit,
    onApply: (NetworkFilters) -> Unit,
) {
    var search by remember(filters) { mutableStateOf(filters.search) }
    var status by remember(filters) { mutableStateOf(filters.status) }
    var vendor by remember(filters) { mutableStateOf(filters.vendor) }
    var location by remember(filters) { mutableStateOf(filters.locationId) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter routers") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(search, { search = it }, label = { Text("Name, model, or address") }, leadingIcon = { Icon(Icons.Outlined.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth())
                ChoiceField("Status", status, listOf(null to "Any status") + catalog?.options?.statuses.orEmpty().map { it to it.replaceFirstChar(Char::uppercase) }, { status = it })
                ChoiceField("Vendor", vendor, listOf(null to "All vendors") + catalog?.options?.vendors.orEmpty().map { it.value to it.label }, { vendor = it })
                ChoiceField("Location", location, listOf(null to "All locations") + catalog?.options?.locations.orEmpty().map { it.id to it.name }, { location = it })
            }
        },
        confirmButton = { TextButton(onClick = { onApply(NetworkFilters(search.trim(), status, vendor, location)) }) { Text("Apply") } },
        dismissButton = { Row { TextButton(onClick = { onApply(NetworkFilters()) }) { Text("Clear") }; TextButton(onClick = onDismiss) { Text("Cancel") } } },
    )
}

@Composable
private fun SessionFilterDialog(
    filters: HotspotSessionFilters,
    catalog: HotspotSessionCatalog?,
    onDismiss: () -> Unit,
    onApply: (HotspotSessionFilters) -> Unit,
) {
    var search by remember(filters) { mutableStateOf(filters.search) }
    var status by remember(filters) { mutableStateOf(filters.status) }
    var router by remember(filters) { mutableStateOf(filters.routerId) }
    var from by remember(filters) { mutableStateOf(filters.from.orEmpty()) }
    var to by remember(filters) { mutableStateOf(filters.to.orEmpty()) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter sessions") },
        text = {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                item { OutlinedTextField(search, { search = it }, label = { Text("Customer, username, MAC, or IP") }, leadingIcon = { Icon(Icons.Outlined.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth()) }
                item { ChoiceField("Status", status, listOf(null to "Any status") + catalog?.options?.statuses.orEmpty().map { it to it.replace('_', ' ').replaceFirstChar(Char::uppercase) }, { status = it }) }
                item { ChoiceField("Router", router, listOf(null to "All routers") + catalog?.options?.routers.orEmpty().map { it.id to it.name }, { router = it }) }
                item { DateFilterField("From", from) { selected -> from = selected; if (selected.isNotEmpty() && to.isNotEmpty() && selected > to) to = selected } }
                item { DateFilterField("To", to) { selected -> to = selected; if (selected.isNotEmpty() && from.isNotEmpty() && selected < from) from = selected } }
            }
        },
        confirmButton = { TextButton(onClick = { onApply(filters.copy(search = search.trim(), status = status, routerId = router, from = from.ifEmpty { null }, to = to.ifEmpty { null })) }) { Text("Apply") } },
        dismissButton = { Row { TextButton(onClick = { onApply(HotspotSessionFilters(view = filters.view)) }) { Text("Clear") }; TextButton(onClick = onDismiss) { Text("Cancel") } } },
    )
}

@Composable
private fun ChoiceField(
    label: String,
    selected: String?,
    options: List<Pair<String?, String>>,
    onSelected: (String?) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedLabel = options.firstOrNull { it.first == selected }?.second ?: options.firstOrNull()?.second.orEmpty()
    Column {
        Text(label, style = MaterialTheme.typography.labelLarge)
        Box {
            Surface(
                shape = MaterialTheme.shapes.extraSmall,
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                modifier = Modifier.fillMaxWidth().clickable { expanded = true },
            ) {
                Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text(selectedLabel, Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Icon(Icons.Outlined.ExpandMore, null)
                }
            }
            DropdownMenu(expanded, { expanded = false }) {
                options.forEach { option ->
                    DropdownMenuItem(
                        text = { Text(option.second) },
                        leadingIcon = if (option.first == selected) ({ Icon(Icons.Outlined.Check, null) }) else null,
                        onClick = { onSelected(option.first); expanded = false },
                    )
                }
            }
        }
    }
}

@Composable
private fun Metric(label: String, value: String, icon: ImageVector, modifier: Modifier) {
    Surface(modifier, shape = MaterialTheme.shapes.small, border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.fillMaxWidth().padding(12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(icon, null, tint = MaterialTheme.colorScheme.primary)
            Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, maxLines = 1)
            Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
        }
    }
}

@Composable
private fun StatusText(status: String) {
    val color = when (status) {
        "online", "active", "passed" -> MaterialTheme.colorScheme.primary
        "failed", "offline" -> MaterialTheme.colorScheme.error
        else -> MaterialTheme.colorScheme.tertiary
    }
    Text(status.replace('_', ' ').replaceFirstChar(Char::uppercase), style = MaterialTheme.typography.labelMedium, color = color)
}

@Composable
private fun CircleIcon(icon: ImageVector) {
    Surface(shape = CircleShape, color = MaterialTheme.colorScheme.secondaryContainer, modifier = Modifier.size(42.dp)) {
        Box(contentAlignment = Alignment.Center) { Icon(icon, null) }
    }
}

@Composable
private fun TestIcon(status: String) {
    val icon = when (status) {
        "passed" -> Icons.Outlined.CheckCircle
        "failed" -> Icons.Outlined.ErrorOutline
        else -> Icons.Outlined.HourglassTop
    }
    val color: Color = when (status) {
        "passed" -> MaterialTheme.colorScheme.primary
        "failed" -> MaterialTheme.colorScheme.error
        else -> MaterialTheme.colorScheme.tertiary
    }
    Icon(icon, null, tint = color, modifier = Modifier.size(36.dp))
}

@Composable
private fun DetailRow(label: String, value: String) {
    ListItem(
        headlineContent = { Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant) },
        supportingContent = { Text(value, style = MaterialTheme.typography.bodyLarge) },
    )
    HorizontalDivider(modifier = Modifier.padding(start = 16.dp))
}

@Composable
private fun SectionLabel(text: String) {
    Text(text, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 22.dp, bottom = 8.dp))
}

@Composable
private fun PaginationRow(pagination: NetworkPagination, onPage: (Int) -> Unit) {
    if (pagination.lastPage <= 1) return
    Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        TextButton({ onPage(pagination.currentPage - 1) }, enabled = pagination.currentPage > 1) { Text("Previous") }
        Text("Page ${pagination.currentPage} of ${pagination.lastPage}", style = MaterialTheme.typography.labelLarge)
        TextButton({ onPage(pagination.currentPage + 1) }, enabled = pagination.currentPage < pagination.lastPage) { Text("Next") }
    }
}

@Composable
private fun FeedbackPanel(message: String, isError: Boolean, onDismiss: () -> Unit) {
    Surface(color = if (isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer) {
        Row(Modifier.fillMaxWidth().padding(start = 16.dp, top = 8.dp, bottom = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(message, Modifier.weight(1f))
            IconButton(onClick = onDismiss) { Icon(Icons.Outlined.Close, "Dismiss") }
        }
    }
}

@Composable
private fun EmptyRow(message: String) {
    Text(message, Modifier.fillMaxWidth().padding(32.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
}

private fun heartbeat(value: String?): String? = shortDate(value)?.let { "Heartbeat $it" }

private fun shortDate(value: String?): String? {
    if (value == null) return null
    return runCatching { OffsetDateTime.parse(value).format(DateTimeFormatter.ofPattern("d MMM, HH:mm")) }.getOrElse { value }
}

private fun dataSize(bytes: Long): String = when {
    bytes >= 1_073_741_824 -> String.format(Locale.US, "%.1f GB", bytes / 1_073_741_824.0)
    bytes >= 1_048_576 -> String.format(Locale.US, "%.1f MB", bytes / 1_048_576.0)
    bytes >= 1024 -> String.format(Locale.US, "%.1f KB", bytes / 1024.0)
    else -> "$bytes B"
}
