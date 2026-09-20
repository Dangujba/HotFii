package com.innobytes.hotfii.ui.plans

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.ExpandMore
import androidx.compose.material.icons.outlined.FilterList
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.AccessPlanSummary
import com.innobytes.hotfii.domain.PlanFilters
import com.innobytes.hotfii.domain.PlanInput
import com.innobytes.hotfii.domain.PlanOption
import com.innobytes.hotfii.ui.PlanUiState
import java.math.BigDecimal
import java.math.RoundingMode
import java.text.NumberFormat
import java.util.Currency
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PlanScreen(
    organizationId: String?,
    organizationName: String?,
    state: PlanUiState,
    onLoad: (PlanFilters, Int) -> Unit,
    onCreate: (PlanInput) -> Unit,
    onUpdate: (AccessPlanSummary, PlanInput) -> Unit,
    onDelete: (AccessPlanSummary) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var showFilters by rememberSaveable { mutableStateOf(false) }
    var showCreate by rememberSaveable { mutableStateOf(false) }
    var editingPlan by remember { mutableStateOf<AccessPlanSummary?>(null) }
    var deletingPlan by remember { mutableStateOf<AccessPlanSummary?>(null) }
    val catalog = state.catalog

    LaunchedEffect(organizationId) {
        if (organizationId != null) onLoad(PlanFilters(), 1)
    }

    if (showFilters) {
        PlanFilterDialog(
            filters = state.filters,
            types = catalog?.options?.types.orEmpty(),
            onDismiss = { showFilters = false },
            onApply = {
                showFilters = false
                onLoad(it, 1)
            },
        )
    }
    if (showCreate) {
        PlanFormDialog(
            title = "Create access plan",
            plan = null,
            types = catalog?.options?.types.orEmpty(),
            validityModes = catalog?.options?.validityModes.orEmpty(),
            timezone = catalog?.options?.timezone,
            isBusy = state.isActionRunning,
            onDismiss = { showCreate = false },
            onSubmit = {
                showCreate = false
                onCreate(it)
            },
        )
    }
    editingPlan?.let { plan ->
        PlanFormDialog(
            title = "Edit " + plan.name,
            plan = plan,
            types = catalog?.options?.types.orEmpty(),
            validityModes = catalog?.options?.validityModes.orEmpty(),
            timezone = catalog?.options?.timezone,
            isBusy = state.isActionRunning,
            onDismiss = { editingPlan = null },
            onSubmit = {
                editingPlan = null
                onUpdate(plan, it)
            },
        )
    }
    deletingPlan?.let { plan ->
        AlertDialog(
            onDismissRequest = { deletingPlan = null },
            title = { Text("Delete " + plan.name + "?") },
            text = { Text("This unused access plan will be permanently removed.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        deletingPlan = null
                        onDelete(plan)
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                ) { Text("Delete plan") }
            },
            dismissButton = { TextButton(onClick = { deletingPlan = null }) { Text("Cancel") } },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Plans", fontWeight = FontWeight.Bold)
                        organizationName?.let {
                            Text(
                                it,
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                },
                actions = {
                    IconButton(onClick = { showFilters = true }) {
                        Icon(Icons.Outlined.FilterList, contentDescription = "Filter plans")
                    }
                    IconButton(onClick = {
                        onLoad(state.filters, catalog?.pagination?.currentPage ?: 1)
                    }) {
                        Icon(Icons.Outlined.Refresh, contentDescription = "Refresh plans")
                    }
                },
            )
        },
        floatingActionButton = {
            if (catalog?.permissions?.canManage == true) {
                FloatingActionButton(onClick = { showCreate = true }) {
                    Icon(Icons.Outlined.Add, contentDescription = "Create plan")
                }
            }
        },
    ) { padding ->
        Box(
            Modifier.fillMaxSize().padding(padding),
            contentAlignment = Alignment.TopCenter,
        ) {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().widthIn(max = 760.dp),
                contentPadding = PaddingValues(bottom = 96.dp),
            ) {
                if (state.isLoading || state.isActionRunning) {
                    item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
                }
                state.error?.let { message ->
                    item { FeedbackPanel(message, true, onFeedbackDismissed) }
                }
                state.notice?.let { message ->
                    item { FeedbackPanel(message, false, onFeedbackDismissed) }
                }
                if (state.filters != PlanFilters()) {
                    item {
                        ListItem(
                            headlineContent = { Text("Filtered plans") },
                            supportingContent = { Text(filterSummary(state.filters)) },
                            trailingContent = {
                                TextButton(onClick = { onLoad(PlanFilters(), 1) }) { Text("Clear") }
                            },
                        )
                        HorizontalDivider()
                    }
                }
                when {
                    catalog == null && state.isLoading -> item {
                        Box(
                            Modifier.fillParentMaxSize(),
                            contentAlignment = Alignment.Center,
                        ) { CircularProgressIndicator() }
                    }
                    catalog != null && catalog.plans.isEmpty() -> item {
                        Text(
                            if (state.filters == PlanFilters()) "No access plans yet." else "No plans match these filters.",
                            modifier = Modifier.fillMaxWidth().padding(32.dp),
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    catalog != null -> {
                        items(catalog.plans.size, key = { catalog.plans[it].id }) { index ->
                            val plan = catalog.plans[index]
                            PlanRow(
                                plan = plan,
                                onEdit = { editingPlan = plan },
                                onDelete = { deletingPlan = plan },
                            )
                            if (index < catalog.plans.lastIndex) {
                                HorizontalDivider(modifier = Modifier.padding(start = 72.dp))
                            }
                        }
                        if (catalog.pagination.lastPage > 1) {
                            item {
                                HorizontalDivider()
                                Row(
                                    Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    TextButton(
                                        onClick = { onLoad(state.filters, catalog.pagination.currentPage - 1) },
                                        enabled = catalog.pagination.currentPage > 1 && !state.isLoading,
                                    ) { Text("Previous") }
                                    Text(
                                        "Page " + catalog.pagination.currentPage + " of " + catalog.pagination.lastPage,
                                        style = MaterialTheme.typography.labelLarge,
                                    )
                                    TextButton(
                                        onClick = { onLoad(state.filters, catalog.pagination.currentPage + 1) },
                                        enabled = catalog.pagination.currentPage < catalog.pagination.lastPage && !state.isLoading,
                                    ) { Text("Next") }
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
private fun PlanRow(
    plan: AccessPlanSummary,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
) {
    ListItem(
        headlineContent = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    plan.name,
                    modifier = Modifier.weight(1f),
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    if (plan.isActive) "Active" else "Inactive",
                    style = MaterialTheme.typography.labelMedium,
                    color = if (plan.isActive) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        },
        supportingContent = {
            Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(plan.accessType.replaceFirstChar(Char::uppercase) + " | " + money(plan.priceKobo))
                Text(
                    allowance(plan),
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(
                    plan.validityLabel,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    style = MaterialTheme.typography.bodySmall,
                )
            }
        },
        leadingContent = {
            Surface(
                shape = CircleShape,
                color = MaterialTheme.colorScheme.secondaryContainer,
                modifier = Modifier.size(44.dp),
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(Icons.Outlined.Schedule, contentDescription = null)
                }
            }
        },
        trailingContent = {
            if (plan.canEdit) {
                Row {
                    IconButton(onClick = onEdit) {
                        Icon(Icons.Outlined.Edit, contentDescription = "Edit " + plan.name)
                    }
                    IconButton(onClick = onDelete, enabled = plan.canDelete) {
                        Icon(
                            Icons.Outlined.Delete,
                            contentDescription = if (plan.canDelete) {
                                "Delete " + plan.name
                            } else {
                                "Used plan cannot be deleted"
                            },
                        )
                    }
                }
            }
        },
        modifier = if (plan.canEdit) Modifier.clickable(onClick = onEdit) else Modifier,
    )
}

@Composable
private fun PlanFilterDialog(
    filters: PlanFilters,
    types: List<PlanOption>,
    onDismiss: () -> Unit,
    onApply: (PlanFilters) -> Unit,
) {
    var search by remember(filters) { mutableStateOf(filters.search) }
    var type by remember(filters) { mutableStateOf(filters.type) }
    var state by remember(filters) { mutableStateOf(filters.state) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter plans") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                OutlinedTextField(
                    value = search,
                    onValueChange = { search = it },
                    label = { Text("Plan name") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                ChoiceField(
                    label = "Access type",
                    selected = type,
                    options = listOf(null to "All types") + types.map { it.value to it.label },
                    onSelected = { type = it },
                    enabled = true,
                )
                ChoiceField(
                    label = "Status",
                    selected = state,
                    options = listOf(null to "Active and inactive", "active" to "Active", "inactive" to "Inactive"),
                    onSelected = { state = it },
                    enabled = true,
                )
            }
        },
        confirmButton = {
            TextButton(onClick = { onApply(PlanFilters(search.trim(), type, state)) }) { Text("Apply") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@Composable
private fun PlanFormDialog(
    title: String,
    plan: AccessPlanSummary?,
    types: List<PlanOption>,
    validityModes: List<PlanOption>,
    timezone: String?,
    isBusy: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (PlanInput) -> Unit,
) {
    var name by remember(plan?.id) { mutableStateOf(plan?.name.orEmpty()) }
    var accessType by remember(plan?.id) { mutableStateOf(plan?.accessType ?: "paid") }
    var price by remember(plan?.id) {
        mutableStateOf(plan?.priceKobo?.let { BigDecimal(it).movePointLeft(2).stripTrailingZeros().toPlainString() }.orEmpty())
    }
    var minutes by remember(plan?.id) { mutableStateOf(plan?.durationMinutes?.toString().orEmpty()) }
    var dataMb by remember(plan?.id) { mutableStateOf(plan?.dataLimitMb?.toString().orEmpty()) }
    var download by remember(plan?.id) { mutableStateOf(plan?.downloadKbps?.toString().orEmpty()) }
    var upload by remember(plan?.id) { mutableStateOf(plan?.uploadKbps?.toString().orEmpty()) }
    var devices by remember(plan?.id) { mutableStateOf((plan?.simultaneousUse ?: 1).toString()) }
    var validityDays by remember(plan?.id) { mutableStateOf(plan?.validityDays?.toString().orEmpty()) }
    var validityMode by remember(plan?.id) { mutableStateOf(plan?.validityMode ?: "midnight") }
    var isActive by remember(plan?.id) { mutableStateOf(plan?.isActive ?: true) }
    var error by remember(plan?.id) { mutableStateOf<String?>(null) }
    val technicalEnabled = plan?.isUsed != true

    fun submit() {
        val priceKobo = runCatching {
            price.toBigDecimal().movePointRight(2).setScale(0, RoundingMode.UNNECESSARY).longValueExact()
        }.getOrNull()
        val duration = optionalInt(minutes)
        val data = optionalLong(dataMb)
        val down = optionalInt(download)
        val up = optionalInt(upload)
        val deviceCount = devices.toIntOrNull()
        val days = optionalInt(validityDays)
        error = when {
            name.isBlank() -> "Enter a plan name."
            priceKobo == null -> "Enter a valid price with no more than two decimal places."
            accessType == "paid" && priceKobo < 100 -> "A paid plan must cost at least N1."
            priceKobo < 0 -> "Price cannot be negative."
            minutes.isNotBlank() && (duration == null || duration < 1) -> "Minutes must be at least 1."
            dataMb.isNotBlank() && (data == null || data < 1) -> "Data must be at least 1 MB."
            download.isNotBlank() && (down == null || down < 64) -> "Download speed must be at least 64 Kbps."
            upload.isNotBlank() && (up == null || up < 64) -> "Upload speed must be at least 64 Kbps."
            deviceCount == null || deviceCount !in 1..20 -> "Devices must be between 1 and 20."
            validityDays.isNotBlank() && (days == null || days < 1) -> "Validity must be at least 1 day."
            else -> null
        }
        if (error == null) {
            onSubmit(
                PlanInput(
                    name.trim(),
                    accessType,
                    requireNotNull(priceKobo),
                    duration,
                    data,
                    down,
                    up,
                    requireNotNull(deviceCount),
                    days,
                    validityMode,
                    isActive,
                )
            )
        }
    }

    AlertDialog(
        onDismissRequest = { if (!isBusy) onDismiss() },
        title = { Text(title) },
        text = {
            Column(
                Modifier.heightIn(max = 560.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                error?.let {
                    Surface(color = MaterialTheme.colorScheme.errorContainer) {
                        Text(it, Modifier.fillMaxWidth().padding(12.dp), color = MaterialTheme.colorScheme.onErrorContainer)
                    }
                }
                if (plan?.isUsed == true) {
                    Surface(color = MaterialTheme.colorScheme.secondaryContainer) {
                        Text(
                            "This plan has been issued. Only its name, future price, and active status can change.",
                            Modifier.fillMaxWidth().padding(12.dp),
                        )
                    }
                }
                OutlinedTextField(
                    value = name,
                    onValueChange = { name = it; error = null },
                    label = { Text("Plan name") },
                    singleLine = true,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                )
                ChoiceField(
                    label = "Access type",
                    selected = accessType,
                    options = (types.ifEmpty {
                        listOf(PlanOption("paid", "Paid"), PlanOption("free", "Free"), PlanOption("internal", "Internal"))
                    }).map { it.value to it.label },
                    onSelected = { accessType = requireNotNull(it); error = null },
                    enabled = technicalEnabled && !isBusy,
                )
                NumericField("Price (NGN)", price, { price = it; error = null }, !isBusy, decimal = true)
                NumericField("Minutes", minutes, { minutes = it; error = null }, technicalEnabled && !isBusy)
                NumericField("Data (MB)", dataMb, { dataMb = it; error = null }, technicalEnabled && !isBusy)
                NumericField("Download Kbps", download, { download = it; error = null }, technicalEnabled && !isBusy)
                NumericField("Upload Kbps", upload, { upload = it; error = null }, technicalEnabled && !isBusy)
                NumericField("Devices", devices, { devices = it; error = null }, technicalEnabled && !isBusy)
                NumericField("Validity days", validityDays, { validityDays = it; error = null }, technicalEnabled && !isBusy)
                ChoiceField(
                    label = "Validity expiry",
                    selected = validityMode,
                    options = (validityModes.ifEmpty {
                        listOf(
                            PlanOption("midnight", "Midnight (calendar days)"),
                            PlanOption("rolling", "Full 24-hour days"),
                        )
                    }).map { it.value to it.label },
                    onSelected = { validityMode = requireNotNull(it); error = null },
                    enabled = technicalEnabled && !isBusy,
                )
                timezone?.let {
                    Text("Timezone: $it", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                if (plan != null) {
                    Row(
                        Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column {
                            Text("Active", style = MaterialTheme.typography.labelLarge)
                            Text(
                                "Available for new access",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        Switch(checked = isActive, onCheckedChange = { isActive = it }, enabled = !isBusy)
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = ::submit, enabled = !isBusy) {
                if (isBusy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                else Text(if (plan == null) "Create" else "Save")
            }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !isBusy) { Text("Cancel") } },
    )
}

@Composable
private fun NumericField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    enabled: Boolean,
    decimal: Boolean = false,
) {
    OutlinedTextField(
        value = value,
        onValueChange = { next ->
            if (next.isEmpty() || next.all { it.isDigit() || (decimal && it == '.') }) onValueChange(next)
        },
        label = { Text(label) },
        singleLine = true,
        enabled = enabled,
        keyboardOptions = KeyboardOptions(keyboardType = if (decimal) KeyboardType.Decimal else KeyboardType.Number),
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun ChoiceField(
    label: String,
    selected: String?,
    options: List<Pair<String?, String>>,
    onSelected: (String?) -> Unit,
    enabled: Boolean,
) {
    var expanded by remember { mutableStateOf(false) }
    val selectedLabel = options.firstOrNull { it.first == selected }?.second ?: options.firstOrNull()?.second.orEmpty()
    Column {
        Text(label, style = MaterialTheme.typography.labelLarge)
        Box {
            Surface(
                tonalElevation = 0.dp,
                shape = MaterialTheme.shapes.extraSmall,
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                modifier = Modifier.fillMaxWidth().clickable(enabled = enabled) { expanded = true },
            ) {
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(selectedLabel, Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Icon(Icons.Outlined.ExpandMore, contentDescription = null)
                }
            }
            DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
                options.forEach { option ->
                    DropdownMenuItem(
                        text = { Text(option.second) },
                        leadingIcon = if (option.first == selected) {
                            { Icon(Icons.Outlined.Check, contentDescription = null) }
                        } else null,
                        onClick = {
                            onSelected(option.first)
                            expanded = false
                        },
                    )
                }
            }
        }
    }
}

@Composable
private fun FeedbackPanel(message: String, isError: Boolean, onDismiss: () -> Unit) {
    val background = if (isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer
    val content = if (isError) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onSecondaryContainer
    Surface(color = background) {
        Row(
            Modifier.fillMaxWidth().padding(start = 16.dp, end = 8.dp, top = 8.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(message, Modifier.weight(1f), color = content)
            TextButton(onClick = onDismiss) { Text("Dismiss") }
        }
    }
}

private fun filterSummary(filters: PlanFilters): String = listOfNotNull(
    filters.search.takeIf(String::isNotBlank)?.let { "Name: $it" },
    filters.type?.let { "Type: " + it.replaceFirstChar(Char::uppercase) },
    filters.state?.let { "Status: " + it.replaceFirstChar(Char::uppercase) },
).joinToString(" | ")

private fun allowance(plan: AccessPlanSummary): String {
    val access = listOfNotNull(
        plan.durationMinutes?.let { "$it min" },
        plan.dataAllowance,
    ).ifEmpty { listOf("Unlimited") }.joinToString(" | ")
    val speed = if (plan.downloadKbps != null || plan.uploadKbps != null) {
        val down = plan.downloadKbps ?: plan.uploadKbps
        val up = plan.uploadKbps ?: plan.downloadKbps
        " | " + formatMbps(down) + " / " + formatMbps(up) + " Mbps"
    } else {
        ""
    }
    return access + speed + " | " + plan.simultaneousUse + " device" + if (plan.simultaneousUse == 1) "" else "s"
}

private fun formatMbps(kbps: Int?): String = String.format(Locale.US, "%.1f", (kbps ?: 0) / 1000.0)

private fun money(kobo: Long): String = NumberFormat.getCurrencyInstance(Locale.forLanguageTag("en-NG")).apply {
    currency = Currency.getInstance("NGN")
    maximumFractionDigits = if (kobo % 100 == 0L) 0 else 2
}.format(kobo / 100.0)

private fun optionalInt(value: String): Int? = value.takeIf(String::isNotBlank)?.toIntOrNull()
private fun optionalLong(value: String): Long? = value.takeIf(String::isNotBlank)?.toLongOrNull()
