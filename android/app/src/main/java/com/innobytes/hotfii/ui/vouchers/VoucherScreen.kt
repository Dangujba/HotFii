package com.innobytes.hotfii.ui.vouchers

import android.content.Intent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.*
import com.innobytes.hotfii.ui.VoucherUiState
import java.text.NumberFormat
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Currency
import java.util.Locale
import kotlin.math.roundToLong

@Composable
fun VoucherScreen(
    organizationId: String?,
    organizationName: String?,
    state: VoucherUiState,
    onLoad: (VoucherFilters, Int) -> Unit,
    onOpen: (String) -> Unit,
    onClose: () -> Unit,
    onCreate: (VoucherCreateInput) -> Unit,
    onUpdate: (String, VoucherEditInput) -> Unit,
    onDelete: (String) -> Unit,
    onShare: (String) -> Unit,
    onShareConsumed: () -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    val context = LocalContext.current
    LaunchedEffect(organizationId) {
        if (organizationId != null) onLoad(VoucherFilters(), 1)
    }
    LaunchedEffect(state.pendingShareText) {
        val text = state.pendingShareText ?: return@LaunchedEffect
        context.startActivity(
            Intent.createChooser(
                Intent(Intent.ACTION_SEND).apply {
                    type = "text/plain"
                    putExtra(Intent.EXTRA_TEXT, text)
                },
                "Share voucher codes",
            ),
        )
        onShareConsumed()
    }

    if (state.detail == null) {
        VoucherList(
            organizationName, state, onLoad, onOpen, onCreate, onFeedbackDismissed,
        )
    } else {
        VoucherDetail(
            state.detail, state.catalog?.options, state, onClose, onUpdate,
            onDelete, onShare, onFeedbackDismissed,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VoucherList(
    organizationName: String?,
    state: VoucherUiState,
    onLoad: (VoucherFilters, Int) -> Unit,
    onOpen: (String) -> Unit,
    onCreate: (VoucherCreateInput) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var showCreate by rememberSaveable { mutableStateOf(false) }
    var draft by remember(state.filters) { mutableStateOf(state.filters) }
    val catalog = state.catalog

    if (showCreate && catalog != null) {
        CreateVoucherDialog(
            options = catalog.options,
            isBusy = state.isActionRunning,
            onDismiss = { showCreate = false },
            onCreate = {
                showCreate = false
                onCreate(it)
            },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Vouchers", fontWeight = FontWeight.Bold)
                        Text(
                            organizationName ?: "Choose an organization",
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                actions = {
                    IconButton(
                        onClick = { onLoad(state.filters, catalog?.pagination?.currentPage ?: 1) },
                        enabled = !state.isLoading,
                    ) { Icon(Icons.Outlined.Refresh, contentDescription = "Refresh voucher batches") }
                    if (catalog?.permissions?.canCreate == true) {
                        IconButton(onClick = { showCreate = true }) {
                            Icon(Icons.Outlined.Add, contentDescription = "Generate voucher batch")
                        }
                    }
                },
            )
        },
    ) { padding ->
        LazyColumn(
            modifier = Modifier.fillMaxSize().padding(padding),
            contentPadding = PaddingValues(16.dp, 12.dp, 16.dp, 32.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("Voucher batches", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
                    Text(
                        "Generate printed access codes and follow each activation.",
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            item {
                OutlinedTextField(
                    value = draft.search,
                    onValueChange = { draft = draft.copy(search = it) },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Batch reference") },
                    leadingIcon = { Icon(Icons.Outlined.Search, contentDescription = null) },
                    singleLine = true,
                    keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(imeAction = ImeAction.Search),
                    keyboardActions = androidx.compose.foundation.text.KeyboardActions(onSearch = { onLoad(draft, 1) }),
                )
            }
            catalog?.let { data ->
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        ChoiceField(
                            "Status",
                            draft.status?.displayLabel() ?: "Any status",
                            listOf("Any status" to null) + data.options.statuses.map { it.displayLabel() to it },
                        ) { draft = draft.copy(status = it) }
                        ChoiceField(
                            "Plan",
                            data.options.plans.firstOrNull { it.id == draft.planId }?.name ?: "All plans",
                            listOf("All plans" to null) + data.options.plans.map { it.name to it.id },
                        ) { draft = draft.copy(planId = it) }
                        ChoiceField(
                            "Router coverage",
                            data.options.routers.firstOrNull { it.id == draft.routerId }?.name ?: "All coverage",
                            listOf("All coverage" to null) + data.options.routers.map { it.name to it.id },
                        ) { draft = draft.copy(routerId = it) }
                        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            Button(onClick = { onLoad(draft, 1) }, enabled = !state.isLoading) {
                                Icon(Icons.Outlined.FilterAlt, contentDescription = null)
                                Text("Apply", modifier = Modifier.padding(start = 8.dp))
                            }
                            TextButton(onClick = {
                                draft = VoucherFilters()
                                onLoad(VoucherFilters(), 1)
                            }) { Text("Reset") }
                        }
                    }
                }
            }
            feedbackItems(state.error, state.notice, onFeedbackDismissed)
            if (state.isLoading) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            if (catalog != null && !state.isLoading) {
                item {
                    Text(
                        "${catalog.pagination.total} batches",
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (catalog.batches.isEmpty()) {
                    item { EmptyPanel("No voucher batches match these filters.") }
                } else {
                    items(catalog.batches, key = { it.id }) { batch ->
                        BatchRow(batch) { onOpen(batch.id) }
                    }
                }
                if (catalog.pagination.lastPage > 1) {
                    item {
                        Row(
                            Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            IconButton(
                                onClick = { onLoad(state.filters, catalog.pagination.currentPage - 1) },
                                enabled = catalog.pagination.currentPage > 1 && !state.isLoading,
                            ) { Icon(Icons.Outlined.ChevronLeft, contentDescription = "Previous page") }
                            Text("Page ${catalog.pagination.currentPage} of ${catalog.pagination.lastPage}")
                            IconButton(
                                onClick = { onLoad(state.filters, catalog.pagination.currentPage + 1) },
                                enabled = catalog.pagination.currentPage < catalog.pagination.lastPage && !state.isLoading,
                            ) { Icon(Icons.Outlined.ChevronRight, contentDescription = "Next page") }
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun VoucherDetail(
    detail: VoucherBatchDetail,
    options: VoucherOptions?,
    state: VoucherUiState,
    onBack: () -> Unit,
    onUpdate: (String, VoucherEditInput) -> Unit,
    onDelete: (String) -> Unit,
    onShare: (String) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    val batch = detail.summary
    var showEdit by rememberSaveable(batch.id) { mutableStateOf(false) }
    var showDelete by rememberSaveable(batch.id) { mutableStateOf(false) }
    if (showEdit && options != null) {
        EditVoucherDialog(batch, options, state.isActionRunning, { showEdit = false }) {
            showEdit = false
            onUpdate(batch.id, it)
        }
    }
    if (showDelete) {
        AlertDialog(
            onDismissRequest = { showDelete = false },
            title = { Text("Delete ${batch.reference}?") },
            text = { Text("Every unused code in this batch will permanently stop working.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showDelete = false
                        onDelete(batch.id)
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                ) { Text("Delete batch") }
            },
            dismissButton = { TextButton(onClick = { showDelete = false }) { Text("Cancel") } },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(batch.reference, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        Text(batch.status.displayLabel(), style = MaterialTheme.typography.labelMedium)
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Back") }
                },
                actions = {
                    IconButton(onClick = { onShare(batch.id) }, enabled = !state.isActionRunning) {
                        Icon(Icons.Outlined.Share, contentDescription = "Share voucher codes")
                    }
                    if (batch.canEdit) IconButton(onClick = { showEdit = true }) {
                        Icon(Icons.Outlined.Edit, contentDescription = "Edit unused batch")
                    }
                    if (batch.canDelete) IconButton(onClick = { showDelete = true }) {
                        Icon(Icons.Outlined.Delete, contentDescription = "Delete unused batch")
                    }
                },
            )
        },
    ) { padding ->
        LazyColumn(
            Modifier.fillMaxSize().padding(padding),
            contentPadding = PaddingValues(16.dp, 12.dp, 16.dp, 32.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            feedbackItems(state.error, state.notice, onFeedbackDismissed)
            if (state.isLoading || state.isActionRunning) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            item {
                Surface(shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
                    Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        DetailLine("Plan", batch.plan.name)
                        DetailLine("Coverage", batch.router?.name ?: "All routers")
                        DetailLine("Quantity", batch.quantity.toString())
                        DetailLine("PIN length", batch.pinLength.toString())
                        DetailLine("Per voucher", money(batch.retailPriceKobo))
                        DetailLine("Batch value", money(batch.retailValueKobo))
                        DetailLine("Created", dateLabel(batch.createdAt))
                    }
                }
            }
            item {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    CountTile("Available", batch.counts.available, Modifier.weight(1f))
                    CountTile("Active", batch.counts.active, Modifier.weight(1f))
                    CountTile("Expired", batch.counts.expired, Modifier.weight(1f))
                }
            }
            item {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text("Voucher status", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
                    Text("Full codes are revealed only when you share this batch.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            items(detail.vouchers, key = { it.id }) { voucher ->
                Surface(shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
                    Row(
                        Modifier.fillMaxWidth().padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Column(Modifier.weight(1f)) {
                            Text("Voucher ending ${voucher.codeLastFour}", fontWeight = FontWeight.SemiBold)
                            Text(voucher.serialNumber, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        StatusPill(voucher.status)
                    }
                }
            }
        }
    }
}

@Composable
private fun BatchRow(batch: VoucherBatchSummary, onClick: () -> Unit) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(8.dp),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant),
        tonalElevation = 1.dp,
    ) {
        Column(Modifier.fillMaxWidth().padding(15.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(batch.reference, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                StatusPill(batch.status)
            }
            Text(
                "${batch.plan.name} - ${batch.router?.name ?: "All routers"}",
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("${batch.quantity} vouchers")
                Text(money(batch.retailValueKobo), fontWeight = FontWeight.SemiBold)
            }
            Text(
                "${batch.counts.available} available - ${batch.counts.active} active - ${batch.counts.expired} expired",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun CreateVoucherDialog(
    options: VoucherOptions,
    isBusy: Boolean,
    onDismiss: () -> Unit,
    onCreate: (VoucherCreateInput) -> Unit,
) {
    val activePlans = options.plans.filter { it.isActive }
    var routerId by remember { mutableStateOf("all") }
    var planId by remember { mutableStateOf(activePlans.firstOrNull()?.id) }
    var quantity by remember { mutableStateOf("20") }
    var price by remember { mutableStateOf("") }
    var pinFormat by remember { mutableStateOf(options.pinFormats.firstOrNull()?.value ?: "numbers") }
    var pinLength by remember { mutableStateOf(options.pinLengths.lastOrNull() ?: 12) }
    var dashed by remember { mutableStateOf(true) }
    var formError by remember { mutableStateOf<String?>(null) }

    VoucherFormDialog(
        title = "Generate voucher batch",
        confirmLabel = "Generate",
        isBusy = isBusy,
        onDismiss = onDismiss,
        onConfirm = {
            val count = quantity.toIntOrNull()
            val priceKobo = price.toDoubleOrNull()?.let { (it * 100).roundToLong() }
            val plan = activePlans.firstOrNull { it.id == planId }
            formError = when {
                plan == null -> "Choose an active paid plan."
                count == null || count !in 1..5000 -> "Quantity must be between 1 and 5,000."
                price.isNotBlank() && priceKobo == null -> "Enter a valid retail price."
                priceKobo != null && priceKobo < plan.priceKobo -> "Price cannot be below the plan price."
                else -> null
            }
            if (formError == null) {
                onCreate(
                    VoucherCreateInput(
                        routerId, planId!!, count!!, priceKobo,
                        pinFormat, pinLength, dashed,
                    ),
                )
            }
        },
    ) {
        formError?.let { FormError(it) }
        ChoiceField(
            "Router / coverage",
            options.routers.firstOrNull { it.id == routerId }?.name ?: "All routers",
            listOf("All routers" to "all") + options.routers.map { it.name to it.id },
        ) { routerId = it }
        ChoiceField(
            "Access plan",
            activePlans.firstOrNull { it.id == planId }?.let { "${it.name} - ${money(it.priceKobo)}" } ?: "Choose plan",
            activePlans.map { "${it.name} - ${money(it.priceKobo)}" to it.id },
        ) { planId = it }
        OutlinedTextField(
            value = quantity,
            onValueChange = { quantity = it.filter(Char::isDigit).take(4) },
            label = { Text("Quantity") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        OutlinedTextField(
            value = price,
            onValueChange = { price = it.filter { char -> char.isDigit() || char == '.' } },
            label = { Text("Retail price per voucher (NGN)") },
            supportingText = { Text("Leave blank to use the plan price") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        ChoiceField(
            "PIN characters",
            options.pinFormats.firstOrNull { it.value == pinFormat }?.label ?: pinFormat,
            options.pinFormats.map { it.label to it.value },
        ) { pinFormat = it }
        ChoiceField(
            "PIN length",
            pinLength.toString(),
            options.pinLengths.map { it.toString() to it },
        ) { pinLength = it }
        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text("Group PIN with dashes")
                Text("Example: 1234-5678", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Switch(checked = dashed, onCheckedChange = { dashed = it })
        }
    }
}

@Composable
private fun EditVoucherDialog(
    batch: VoucherBatchSummary,
    options: VoucherOptions,
    isBusy: Boolean,
    onDismiss: () -> Unit,
    onUpdate: (VoucherEditInput) -> Unit,
) {
    var routerId by remember { mutableStateOf(batch.router?.id ?: "all") }
    var planId by remember { mutableStateOf(batch.plan.id) }
    var price by remember { mutableStateOf("%.2f".format(Locale.US, batch.retailPriceKobo / 100.0)) }
    var formError by remember { mutableStateOf<String?>(null) }

    VoucherFormDialog(
        title = "Edit ${batch.reference}",
        confirmLabel = "Save",
        isBusy = isBusy,
        onDismiss = onDismiss,
        onConfirm = {
            val priceKobo = price.toDoubleOrNull()?.let { (it * 100).roundToLong() }
            val plan = options.plans.firstOrNull { it.id == planId }
            formError = when {
                plan == null -> "Choose a paid plan."
                priceKobo == null || priceKobo < 100 -> "Enter a valid retail price."
                priceKobo < plan.priceKobo &&
                    !(plan.id == batch.plan.id && priceKobo == batch.retailPriceKobo) ->
                    "Price cannot be below the plan price."
                else -> null
            }
            if (formError == null) onUpdate(VoucherEditInput(routerId, planId, priceKobo!!))
        },
    ) {
        Text("Existing PINs, serial numbers, and quantity stay unchanged.", color = MaterialTheme.colorScheme.onSurfaceVariant)
        formError?.let { FormError(it) }
        ChoiceField(
            "Router / coverage",
            options.routers.firstOrNull { it.id == routerId }?.name ?: "All routers",
            listOf("All routers" to "all") + options.routers.map { it.name to it.id },
        ) { routerId = it }
        ChoiceField(
            "Access plan",
            options.plans.firstOrNull { it.id == planId }?.name ?: "Choose plan",
            options.plans.map { (if (it.isActive) it.name else "${it.name} (inactive)") to it.id },
        ) { planId = it }
        OutlinedTextField(
            value = price,
            onValueChange = { price = it.filter { char -> char.isDigit() || char == '.' } },
            label = { Text("Retail price per voucher (NGN)") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
    }
}

@Composable
private fun VoucherFormDialog(
    title: String,
    confirmLabel: String,
    isBusy: Boolean,
    onDismiss: () -> Unit,
    onConfirm: () -> Unit,
    content: @Composable () -> Unit,
) {
    AlertDialog(
        onDismissRequest = { if (!isBusy) onDismiss() },
        title = { Text(title) },
        text = {
            Column(
                Modifier.fillMaxWidth().heightIn(max = 540.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) { content() }
        },
        confirmButton = {
            Button(onClick = onConfirm, enabled = !isBusy) {
                if (isBusy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                else Text(confirmLabel)
            }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !isBusy) { Text("Cancel") } },
    )
}

@Composable
private fun <T> ChoiceField(
    label: String,
    selectedLabel: String,
    options: List<Pair<String, T>>,
    onSelected: (T) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge)
        Box {
            Surface(
                onClick = { expanded = true },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(8.dp),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
            ) {
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(selectedLabel, Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Icon(Icons.Outlined.ExpandMore, contentDescription = null)
                }
            }
            DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
                options.forEach { (optionLabel, value) ->
                    DropdownMenuItem(
                        text = { Text(optionLabel) },
                        onClick = {
                            expanded = false
                            onSelected(value)
                        },
                    )
                }
            }
        }
    }
}

private fun androidx.compose.foundation.lazy.LazyListScope.feedbackItems(
    error: String?,
    notice: String?,
    onDismiss: () -> Unit,
) {
    if (error != null) item { FeedbackPanel(error, true, onDismiss) }
    if (notice != null) item { FeedbackPanel(notice, false, onDismiss) }
}

@Composable
private fun FeedbackPanel(message: String, isError: Boolean, onDismiss: () -> Unit) {
    Surface(
        color = if (isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer,
        contentColor = if (isError) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onSecondaryContainer,
        shape = RoundedCornerShape(8.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(start = 14.dp, top = 8.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(message, Modifier.weight(1f))
            IconButton(onClick = onDismiss) { Icon(Icons.Outlined.Close, contentDescription = "Dismiss message") }
        }
    }
}

@Composable
private fun FormError(message: String) {
    Surface(color = MaterialTheme.colorScheme.errorContainer, shape = RoundedCornerShape(8.dp)) {
        Text(message, Modifier.fillMaxWidth().padding(12.dp), color = MaterialTheme.colorScheme.onErrorContainer)
    }
}

@Composable
private fun EmptyPanel(message: String) {
    Surface(shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Text(message, Modifier.fillMaxWidth().padding(28.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun StatusPill(status: String) {
    Surface(
        color = when (status) {
            "active" -> MaterialTheme.colorScheme.primaryContainer
            "expired", "revoked" -> MaterialTheme.colorScheme.errorContainer
            else -> MaterialTheme.colorScheme.secondaryContainer
        },
        shape = RoundedCornerShape(8.dp),
    ) {
        Text(
            status.displayLabel(),
            Modifier.padding(horizontal = 9.dp, vertical = 5.dp),
            style = MaterialTheme.typography.labelMedium,
        )
    }
}

@Composable
private fun CountTile(label: String, count: Int, modifier: Modifier) {
    Surface(modifier, shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.padding(12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(count.toString(), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(label, style = MaterialTheme.typography.labelSmall, maxLines = 1)
        }
    }
}

@Composable
private fun DetailLine(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.size(12.dp))
        Text(value, fontWeight = FontWeight.SemiBold, maxLines = 2)
    }
}

private fun money(kobo: Long): String = NumberFormat.getCurrencyInstance(Locale.forLanguageTag("en-NG")).apply {
    currency = Currency.getInstance("NGN")
    maximumFractionDigits = if (kobo % 100 == 0L) 0 else 2
}.format(kobo / 100.0)

private fun dateLabel(value: String): String = runCatching {
    OffsetDateTime.parse(value).format(DateTimeFormatter.ofPattern("d MMM yyyy, HH:mm"))
}.getOrDefault(value)

private fun String.displayLabel(): String =
    replace('_', ' ').replaceFirstChar { it.titlecase(Locale.getDefault()) }
