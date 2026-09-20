package com.innobytes.hotfii.ui.finance

import android.content.Intent
import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.*
import com.innobytes.hotfii.ui.FinanceUiState
import java.text.NumberFormat
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Currency
import java.util.Locale

private enum class FinanceTab { Ledger, Invoices }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FinanceScreen(
    organizationId: String?,
    organizationName: String?,
    state: FinanceUiState,
    onLoad: (FinanceFilters, Int, Int) -> Unit,
    onOpenInvoice: (String) -> Unit,
    onCloseInvoice: () -> Unit,
    onPayInvoice: (String) -> Unit,
    onCheckoutConsumed: () -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var tab by rememberSaveable { mutableStateOf(FinanceTab.Ledger) }
    var showFilters by rememberSaveable { mutableStateOf(false) }
    val context = LocalContext.current
    LaunchedEffect(organizationId) { if (organizationId != null) onLoad(FinanceFilters(), 1, 1) }
    LaunchedEffect(state.checkoutUrl) {
        state.checkoutUrl?.let { url ->
            context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            onCheckoutConsumed()
        }
    }
    BackHandler(enabled = state.selectedInvoice != null, onBack = onCloseInvoice)

    state.selectedInvoice?.let { detail ->
        InvoiceDetailScreen(
            detail = detail,
            busy = state.isActionRunning,
            error = state.error,
            notice = state.notice,
            onBack = onCloseInvoice,
            onPay = onPayInvoice,
            onFeedbackDismissed = onFeedbackDismissed,
        )
        return
    }

    Scaffold(topBar = {
        TopAppBar(
            title = { Column { Text("Finance"); organizationName?.let { Text(it, style = MaterialTheme.typography.labelMedium) } } },
            actions = {
                IconButton({ showFilters = true }) { Icon(Icons.Outlined.FilterList, "Filter finance") }
                IconButton({
                    val catalog = state.catalog
                    onLoad(state.filters, catalog?.ledgerPagination?.currentPage ?: 1, catalog?.invoicePagination?.currentPage ?: 1)
                }) { Icon(Icons.Outlined.Refresh, "Refresh finance") }
            },
        )
    }) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding)) {
            if (state.isLoading || state.isActionRunning) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            state.error?.let { item { Feedback(it, true, onFeedbackDismissed) } }
            state.notice?.let { item { Feedback(it, false, onFeedbackDismissed) } }
            val catalog = state.catalog
            if (catalog != null) {
                item {
                    Text("This month", Modifier.padding(start = 16.dp, top = 18.dp, bottom = 8.dp), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Row(Modifier.fillMaxWidth().padding(horizontal = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Metric("Sales", money(catalog.current.salesKobo), Icons.Outlined.Payments, Modifier.weight(1f))
                        Metric("Fees", money(catalog.current.feesKobo), Icons.Outlined.Percent, Modifier.weight(1f))
                    }
                    Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Metric("Accrued", money(catalog.current.accruedKobo), Icons.Outlined.Schedule, Modifier.weight(1f))
                        Metric("Collected", money(catalog.current.collectedKobo), Icons.Outlined.CheckCircle, Modifier.weight(1f))
                    }
                    ListItem(
                        headlineContent = { Text("Estimated month-end invoice") },
                        supportingContent = { Text("${catalog.plan.code.replaceFirstChar(Char::uppercase)} plan • ${catalog.plan.subscriptionStatus ?: "Not subscribed"}") },
                        trailingContent = { Text(money(catalog.current.estimatedInvoiceBalanceKobo), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold) },
                    )
                    HorizontalDivider()
                    PrimaryTabRow(selectedTabIndex = tab.ordinal) {
                        Tab(tab == FinanceTab.Ledger, { tab = FinanceTab.Ledger }, text = { Text("Charge ledger") })
                        Tab(tab == FinanceTab.Invoices, { tab = FinanceTab.Invoices }, text = { Text("Invoices") })
                    }
                }
                if (tab == FinanceTab.Ledger) {
                    if (catalog.ledger.isEmpty()) item { Empty("No charge ledger entries match these filters.") }
                    items(catalog.ledger.size) { index ->
                        val entry = catalog.ledger[index]
                        ListItem(
                            headlineContent = { Text(entry.sourceType.replace('_', ' ').replaceFirstChar(Char::uppercase)) },
                            supportingContent = { Text(listOfNotNull(entry.routerName ?: "All routers", entry.billingPeriod, shortDate(entry.createdAt)).joinToString(" • ")) },
                            overlineContent = { Status(entry.status) },
                            trailingContent = { Column(horizontalAlignment = Alignment.End) { Text(money(entry.feeAmountKobo), fontWeight = FontWeight.Bold); Text("on ${money(entry.billableSalesKobo)}", style = MaterialTheme.typography.labelSmall) } },
                        )
                        HorizontalDivider(Modifier.padding(start = 16.dp))
                    }
                    item { Pagination(catalog.ledgerPagination) { page -> onLoad(state.filters, page, catalog.invoicePagination.currentPage) } }
                } else {
                    if (catalog.invoices.isEmpty()) item { Empty("No invoices match these filters.") }
                    items(catalog.invoices.size) { index ->
                        val invoice = catalog.invoices[index]
                        ListItem(
                            headlineContent = { Text(invoice.number) },
                            supportingContent = { Text("${invoice.billingPeriod} • Due ${shortDate(invoice.dueAt) ?: "not set"}") },
                            overlineContent = { Status(if (invoice.isOverdue && invoice.status != "paid") "overdue" else invoice.status) },
                            trailingContent = { Text(money(invoice.totalKobo), fontWeight = FontWeight.Bold) },
                            modifier = Modifier.clickable { onOpenInvoice(invoice.id) },
                        )
                        HorizontalDivider(Modifier.padding(start = 16.dp))
                    }
                    item { Pagination(catalog.invoicePagination) { page -> onLoad(state.filters, catalog.ledgerPagination.currentPage, page) } }
                }
            } else if (!state.isLoading) item { Empty("Finance data is not available for this organization.") }
        }
    }
    if (showFilters) FinanceFilterDialog(state.filters, state.catalog?.options, { showFilters = false }) {
        showFilters = false
        onLoad(it, 1, 1)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable private fun InvoiceDetailScreen(
    detail: FinanceInvoiceDetail,
    busy: Boolean,
    error: String?,
    notice: String?,
    onBack: () -> Unit,
    onPay: (String) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var confirmPay by rememberSaveable { mutableStateOf(false) }
    val invoice = detail.invoice
    Scaffold(topBar = { TopAppBar(title = { Text(invoice.number) }, navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") } }) }) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding)) {
            if (busy) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            error?.let { item { Feedback(it, true, onFeedbackDismissed) } }
            notice?.let { item { Feedback(it, false, onFeedbackDismissed) } }
            item { Detail("Status", if (invoice.isOverdue && invoice.status != "paid") "Overdue" else invoice.status.replaceFirstChar(Char::uppercase)); Detail("Billing period", invoice.billingPeriod); Detail("Subtotal", money(invoice.subtotalKobo)); Detail("Total", money(invoice.totalKobo)); Detail("Due", shortDate(invoice.dueAt) ?: "Not set"); invoice.paidAt?.let { Detail("Paid", shortDate(it).orEmpty()) }; invoice.paymentMethod?.let { Detail("Payment method", it.replaceFirstChar(Char::uppercase)) } }
            if (detail.canPay && invoice.status != "paid") item { Button({ confirmPay = true }, enabled = !busy, modifier = Modifier.fillMaxWidth().padding(16.dp)) { Icon(Icons.Outlined.OpenInBrowser, null); Spacer(Modifier.width(8.dp)); Text("Pay securely with Paystack") } }
        }
    }
    if (confirmPay) AlertDialog(onDismissRequest = { confirmPay = false }, title = { Text("Pay ${invoice.number}?") }, text = { Text("HotFii will open Paystack to pay ${money(invoice.totalKobo)}. You will return here after checkout.") }, confirmButton = { TextButton({ confirmPay = false; onPay(invoice.id) }) { Text("Continue") } }, dismissButton = { TextButton({ confirmPay = false }) { Text("Cancel") } })
}

@Composable private fun FinanceFilterDialog(filters: FinanceFilters, options: FinanceOptions?, onDismiss: () -> Unit, onApply: (FinanceFilters) -> Unit) {
    var ledger by remember(filters) { mutableStateOf(filters.ledgerStatus) }
    var invoice by remember(filters) { mutableStateOf(filters.invoiceStatus) }
    var period by remember(filters) { mutableStateOf(filters.period.orEmpty()) }
    var router by remember(filters) { mutableStateOf(filters.routerId) }
    AlertDialog(onDismissRequest = onDismiss, title = { Text("Filter finance") }, text = { Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        OutlinedTextField(period, { period = it.take(7) }, label = { Text("Billing month (YYYY-MM)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
        ChoiceField("Ledger status", ledger, listOf(null to "All statuses") + options?.ledgerStatuses.orEmpty().map { it to it.replaceFirstChar(Char::uppercase) }) { ledger = it }
        ChoiceField("Invoice status", invoice, listOf(null to "All statuses") + options?.invoiceStatuses.orEmpty().map { it to it.replaceFirstChar(Char::uppercase) }) { invoice = it }
        ChoiceField("Router", router, listOf(null to "All routers") + options?.routers.orEmpty().map { it.id to it.name }) { router = it }
    } }, confirmButton = { TextButton({ onApply(FinanceFilters(ledger, period.ifEmpty { null }, invoice, router)) }) { Text("Apply") } }, dismissButton = { Row { TextButton({ onApply(FinanceFilters()) }) { Text("Clear") }; TextButton(onDismiss) { Text("Cancel") } } })
}

@Composable private fun ChoiceField(label: String, selected: String?, options: List<Pair<String?, String>>, onSelected: (String?) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Column { Text(label, style = MaterialTheme.typography.labelLarge); Box { Surface(shape = MaterialTheme.shapes.extraSmall, border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline), modifier = Modifier.fillMaxWidth().clickable { expanded = true }) { Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) { Text(options.firstOrNull { it.first == selected }?.second ?: "All", Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis); Icon(Icons.Outlined.ExpandMore, null) } }; DropdownMenu(expanded, { expanded = false }) { options.forEach { option -> DropdownMenuItem(text = { Text(option.second) }, onClick = { expanded = false; onSelected(option.first) }) } } } }
}

@Composable private fun Metric(label: String, value: String, icon: androidx.compose.ui.graphics.vector.ImageVector, modifier: Modifier) { Surface(modifier, shape = MaterialTheme.shapes.small, border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) { Column(Modifier.padding(12.dp)) { Icon(icon, null, tint = MaterialTheme.colorScheme.primary); Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis); Text(label, style = MaterialTheme.typography.labelSmall) } } }
@Composable private fun Pagination(pagination: NetworkPagination, onPage: (Int) -> Unit) { if (pagination.lastPage > 1) Row(Modifier.fillMaxWidth().padding(8.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { TextButton({ onPage(pagination.currentPage - 1) }, enabled = pagination.currentPage > 1) { Text("Previous") }; Text("Page ${pagination.currentPage} of ${pagination.lastPage}"); TextButton({ onPage(pagination.currentPage + 1) }, enabled = pagination.currentPage < pagination.lastPage) { Text("Next") } } }
@Composable private fun Status(value: String) { val color = when (value) { "paid", "collected" -> MaterialTheme.colorScheme.primary; "overdue" -> MaterialTheme.colorScheme.error; else -> MaterialTheme.colorScheme.tertiary }; Text(value.replaceFirstChar(Char::uppercase), color = color, style = MaterialTheme.typography.labelMedium) }
@Composable private fun Detail(label: String, value: String) { ListItem(headlineContent = { Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant) }, supportingContent = { Text(value, style = MaterialTheme.typography.bodyLarge) }); HorizontalDivider(Modifier.padding(start = 16.dp)) }
@Composable private fun Empty(message: String) { Text(message, Modifier.fillMaxWidth().padding(32.dp), color = MaterialTheme.colorScheme.onSurfaceVariant) }
@Composable private fun Feedback(message: String, error: Boolean, dismiss: () -> Unit) { Surface(color = if (error) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer) { Row(Modifier.fillMaxWidth().padding(start = 16.dp), verticalAlignment = Alignment.CenterVertically) { Text(message, Modifier.weight(1f)); IconButton(dismiss) { Icon(Icons.Outlined.Close, "Dismiss") } } } }
private fun money(kobo: Long) = NumberFormat.getCurrencyInstance(Locale.forLanguageTag("en-NG")).apply { currency = Currency.getInstance("NGN"); maximumFractionDigits = if (kobo % 100 == 0L) 0 else 2 }.format(kobo / 100.0)
private fun shortDate(value: String?): String? = value?.let { date -> runCatching { OffsetDateTime.parse(date).format(DateTimeFormatter.ofPattern("d MMM yyyy, HH:mm")) }.getOrElse { date } }
