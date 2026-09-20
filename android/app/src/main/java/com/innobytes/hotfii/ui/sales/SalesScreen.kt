package com.innobytes.hotfii.ui.sales

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.*
import com.innobytes.hotfii.ui.SalesUiState
import java.text.NumberFormat
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Currency
import java.util.Locale

private enum class SalesSection { Activity, Customers }
private enum class ActivityList { Transactions, Vouchers }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SalesScreen(
    organizationId: String?,
    organizationName: String?,
    state: SalesUiState,
    onLoad: (SalesFilters, Int, Int) -> Unit,
    onLoadCustomers: (CustomerFilters, Int) -> Unit,
    onOpenCustomer: (String) -> Unit,
    onCloseCustomer: () -> Unit,
    onRecordCash: (CashSaleInput) -> Unit,
    onFeedbackDismissed: () -> Unit,
    onCredentialConsumed: () -> Unit,
) {
    var section by rememberSaveable { mutableStateOf(SalesSection.Activity) }
    var list by rememberSaveable { mutableStateOf(ActivityList.Transactions) }
    var salesFilters by rememberSaveable { mutableStateOf(false) }
    var customerFilters by rememberSaveable { mutableStateOf(false) }
    var cashForm by rememberSaveable { mutableStateOf(false) }

    LaunchedEffect(organizationId) {
        if (organizationId != null) onLoad(SalesFilters(), 1, 1)
    }
    LaunchedEffect(section, organizationId) {
        if (section == SalesSection.Customers && organizationId != null && state.customerCatalog == null) {
            onLoadCustomers(state.customerFilters, 1)
        }
    }
    state.customerDetail?.let {
        BackHandler(onBack = onCloseCustomer)
        CustomerDetailScreen(it, state.isLoading, onCloseCustomer)
        return
    }
    state.issuedCredential?.let { CredentialDialog(it, onCredentialConsumed) }
    if (cashForm) {
        CashSaleDialog(
            state.catalog?.options?.cashPlans.orEmpty(),
            state.catalog?.options?.cashRouters.orEmpty(),
            state.isActionRunning,
            { cashForm = false },
        ) {
            onRecordCash(it)
            cashForm = false
        }
    }
    if (salesFilters) {
        SalesFilterDialog(state.filters, state.catalog, { salesFilters = false }) {
            salesFilters = false
            onLoad(it, 1, 1)
        }
    }
    if (customerFilters) {
        CustomerFilterDialog(state.customerFilters, state.customerCatalog, { customerFilters = false }) {
            customerFilters = false
            onLoadCustomers(it, 1)
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Sales", fontWeight = FontWeight.Bold)
                        organizationName?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    }
                },
                actions = {
                    IconButton(onClick = {
                        if (section == SalesSection.Activity) salesFilters = true else customerFilters = true
                    }) { Icon(Icons.Outlined.FilterList, "Filter") }
                    if (section == SalesSection.Activity && state.catalog?.permissions?.canRecordCash == true) {
                        IconButton(onClick = { cashForm = true }) { Icon(Icons.Outlined.AddCard, "Record direct cash sale") }
                    }
                    IconButton(onClick = {
                        if (section == SalesSection.Activity) {
                            onLoad(state.filters, state.catalog?.transactionsPagination?.currentPage ?: 1, state.catalog?.vouchersPagination?.currentPage ?: 1)
                        } else {
                            onLoadCustomers(state.customerFilters, state.customerCatalog?.pagination?.currentPage ?: 1)
                        }
                    }) { Icon(Icons.Outlined.Refresh, "Refresh") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            TabRow(section.ordinal) {
                Tab(section == SalesSection.Activity, { section = SalesSection.Activity }, text = { Text("Activity") })
                Tab(section == SalesSection.Customers, { section = SalesSection.Customers }, text = { Text("Customers") })
            }
            if (state.isLoading || state.isActionRunning) LinearProgressIndicator(Modifier.fillMaxWidth())
            state.error?.let { FeedbackPanel(it, true, onFeedbackDismissed) }
            state.notice?.takeIf { state.issuedCredential == null }?.let { FeedbackPanel(it, false, onFeedbackDismissed) }
            if (section == SalesSection.Activity) {
                ActivityContent(state, list, { list = it }, onLoad) { cashForm = true }
            } else {
                CustomerContent(state, onLoadCustomers, onOpenCustomer)
            }
        }
    }
}

@Composable
private fun ActivityContent(
    state: SalesUiState,
    selected: ActivityList,
    onSelected: (ActivityList) -> Unit,
    onLoad: (SalesFilters, Int, Int) -> Unit,
    onRecordCash: () -> Unit,
) {
    val catalog = state.catalog
    LazyColumn(Modifier.fillMaxSize().widthIn(max = 760.dp), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (catalog == null) {
            if (!state.isLoading) item { EmptyRow("No sales activity loaded.") }
        } else {
            item { SummaryGrid(catalog) }
            if (catalog.permissions.canRecordCash) {
                item {
                    ListItem(
                        headlineContent = { Text("Record direct cash activation", fontWeight = FontWeight.SemiBold) },
                        supportingContent = { Text("Issue access and record the sale automatically") },
                        leadingContent = { Icon(Icons.Outlined.AddCard, null) },
                        trailingContent = { Icon(Icons.Outlined.ChevronRight, null) },
                        modifier = Modifier.clickable(onClick = onRecordCash),
                    )
                    HorizontalDivider(modifier = Modifier.padding(start = 56.dp))
                }
            } else {
                catalog.permissions.cashUnavailableReason?.let { reason ->
                    item { Surface(color = MaterialTheme.colorScheme.secondaryContainer) { Text(reason, Modifier.fillMaxWidth().padding(16.dp)) } }
                }
            }
            item {
                TabRow(selected.ordinal) {
                    Tab(selected == ActivityList.Transactions, { onSelected(ActivityList.Transactions) }, text = { Text("Transactions (" + catalog.transactionsPagination.total + ")") })
                    Tab(selected == ActivityList.Vouchers, { onSelected(ActivityList.Vouchers) }, text = { Text("Activations (" + catalog.vouchersPagination.total + ")") })
                }
            }
            if (selected == ActivityList.Transactions) {
                if (catalog.transactions.isEmpty()) item { EmptyRow("No transactions match this view.") }
                itemsIndexed(catalog.transactions, key = { _, it -> it.id }) { index, transaction ->
                    TransactionRow(transaction)
                    if (index < catalog.transactions.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
                }
                item { PaginationRow(catalog.transactionsPagination) { onLoad(state.filters, it, catalog.vouchersPagination.currentPage) } }
            } else {
                if (catalog.voucherActivations.isEmpty()) item { EmptyRow("No voucher activations match this view.") }
                itemsIndexed(catalog.voucherActivations, key = { _, it -> it.id }) { index, voucher ->
                    VoucherRow(voucher)
                    if (index < catalog.voucherActivations.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
                }
                item { PaginationRow(catalog.vouchersPagination) { onLoad(state.filters, catalog.transactionsPagination.currentPage, it) } }
            }
        }
    }
}

@Composable
private fun SummaryGrid(catalog: SalesCatalog) {
    Column(Modifier.fillMaxWidth().padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Metric("Online", money(catalog.summary.onlineSalesKobo), Icons.Outlined.CreditCard, Modifier.weight(1f))
            Metric("Printed vouchers", money(catalog.summary.printedVoucherSalesKobo), Icons.Outlined.ConfirmationNumber, Modifier.weight(1f))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Metric("Direct cash", money(catalog.summary.directCashSalesKobo), Icons.Outlined.AttachMoney, Modifier.weight(1f))
            Metric("Total sales", money(catalog.summary.totalSalesKobo), Icons.Outlined.Storefront, Modifier.weight(1f))
        }
        Metric("Voucher activations", catalog.summary.voucherActivationsCount.toString() + " pieces", Icons.Outlined.ConfirmationNumber, Modifier.fillMaxWidth())
    }
}

@Composable
private fun Metric(label: String, value: String, icon: ImageVector, modifier: Modifier) {
    Surface(modifier, shape = MaterialTheme.shapes.small, border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Row(Modifier.fillMaxWidth().padding(14.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Icon(icon, null, tint = MaterialTheme.colorScheme.primary)
            Column {
                Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun TransactionRow(transaction: SalesTransaction) {
    val label = if (transaction.saleType == "voucher") "Printed voucher" else if (transaction.saleType == "cash") "Direct cash" else "Online"
    ListItem(
        headlineContent = {
            Row {
                Text(label, Modifier.weight(1f), fontWeight = FontWeight.SemiBold)
                Text(money(transaction.grossAmountKobo), fontWeight = FontWeight.Bold)
            }
        },
        supportingContent = {
            Column {
                Text(transaction.planName ?: transaction.reference, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(listOfNotNull(transaction.routerName, transaction.customerName ?: transaction.customerContact, shortDate(transaction.paidAt ?: transaction.createdAt)).joinToString(" | "), style = MaterialTheme.typography.bodySmall, maxLines = 2)
                Text(transaction.status.replaceFirstChar(Char::uppercase) + " | HotFii fee " + money(transaction.platformFeeKobo), style = MaterialTheme.typography.labelSmall)
            }
        },
        leadingContent = { CircleIcon(if (transaction.saleType == "voucher") Icons.Outlined.ConfirmationNumber else if (transaction.saleType == "cash") Icons.Outlined.AttachMoney else Icons.Outlined.CreditCard) },
    )
}

@Composable
private fun VoucherRow(voucher: VoucherActivation) {
    ListItem(
        headlineContent = {
            Row {
                Text("Voucher •••• " + voucher.codeLastFour, Modifier.weight(1f), fontWeight = FontWeight.SemiBold)
                Text(if (voucher.isComplimentary) "Free" else money(voucher.amountKobo), fontWeight = FontWeight.Bold)
            }
        },
        supportingContent = {
            Column {
                Text(voucher.planName ?: voucher.batchReference ?: voucher.serialNumber)
                Text(listOfNotNull(voucher.routerName ?: "Unattributed router", voucher.customerName, shortDate(voucher.activatedAt)).joinToString(" | "), style = MaterialTheme.typography.bodySmall)
            }
        },
        leadingContent = { CircleIcon(Icons.Outlined.ConfirmationNumber) },
    )
}

@Composable
private fun CustomerContent(state: SalesUiState, onLoad: (CustomerFilters, Int) -> Unit, onOpen: (String) -> Unit) {
    val catalog = state.customerCatalog
    LazyColumn(Modifier.fillMaxSize().widthIn(max = 760.dp), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (catalog == null) {
            if (!state.isLoading) item { EmptyRow("No customer data loaded.") }
        } else {
            if (catalog.customers.isEmpty()) item { EmptyRow("No customers match this view.") }
            itemsIndexed(catalog.customers, key = { _, it -> it.id }) { index, customer ->
                CustomerRow(customer) { onOpen(customer.id) }
                if (index < catalog.customers.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
            }
            item { PaginationRow(catalog.pagination) { onLoad(state.customerFilters, it) } }
        }
    }
}

@Composable
private fun CustomerRow(customer: CustomerSummary, onClick: () -> Unit) {
    ListItem(
        headlineContent = { Text(customer.name ?: customer.phone ?: "Walk-in customer", fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis) },
        supportingContent = {
            Column {
                Text(listOfNotNull(customer.phone, customer.currentPlan).joinToString(" | ").ifBlank { customer.type.replaceFirstChar(Char::uppercase) })
                Text(customer.sessionsCount.toString() + " sessions | " + customer.transactionsCount + " sales | " + customer.status.replaceFirstChar(Char::uppercase), style = MaterialTheme.typography.bodySmall)
            }
        },
        leadingContent = { CircleIcon(Icons.Outlined.Person) },
        trailingContent = { Icon(Icons.Outlined.ChevronRight, null) },
        modifier = Modifier.clickable(onClick = onClick),
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CustomerDetailScreen(detail: CustomerDetail, isLoading: Boolean, onBack: () -> Unit) {
    val customer = detail.customer
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(customer.name ?: customer.phone ?: "Customer", fontWeight = FontWeight.Bold) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Outlined.ArrowBack, "Back") } },
            )
        },
    ) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding), contentPadding = PaddingValues(bottom = 28.dp)) {
            if (isLoading) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            item {
                ListItem(
                    headlineContent = { Text(customer.name ?: "Walk-in customer", fontWeight = FontWeight.SemiBold) },
                    supportingContent = { Text(listOfNotNull(customer.phone, customer.email, customer.currentPlan).joinToString("\n").ifBlank { "No contact details" }) },
                    leadingContent = { CircleIcon(Icons.Outlined.Person) },
                    trailingContent = { Text(customer.status.replaceFirstChar(Char::uppercase), color = MaterialTheme.colorScheme.primary) },
                )
            }
            item {
                Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Metric("Sales", customer.transactionsCount.toString(), Icons.Outlined.Storefront, Modifier.weight(1f))
                    Metric("Sessions", customer.sessionsCount.toString(), Icons.Outlined.Router, Modifier.weight(1f))
                }
            }
            item { SectionLabel("Recent sessions") }
            if (detail.recentSessions.isEmpty()) item { EmptyRow("No sessions yet.") }
            itemsIndexed(detail.recentSessions, key = { _, it -> it.id }) { index, session ->
                ListItem(
                    headlineContent = { Text(session.planName ?: "Access session", fontWeight = FontWeight.SemiBold) },
                    supportingContent = { Text(listOfNotNull(session.routerName, shortDate(session.startedAt), dataSize(session.totalBytes)).joinToString(" | ")) },
                    trailingContent = { Text(session.status.replaceFirstChar(Char::uppercase), style = MaterialTheme.typography.labelMedium) },
                    leadingContent = { CircleIcon(Icons.Outlined.Router) },
                )
                if (index < detail.recentSessions.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
            }
            item { SectionLabel("Recent sales") }
            if (detail.recentTransactions.isEmpty()) item { EmptyRow("No sales yet.") }
            itemsIndexed(detail.recentTransactions, key = { _, it -> it.id }) { index, transaction ->
                ListItem(
                    headlineContent = {
                        Row {
                            Text(transaction.planName ?: transaction.reference, Modifier.weight(1f), fontWeight = FontWeight.SemiBold)
                            Text(money(transaction.grossAmountKobo), fontWeight = FontWeight.Bold)
                        }
                    },
                    supportingContent = { Text(listOfNotNull(transaction.routerName, shortDate(transaction.createdAt)).joinToString(" | ")) },
                    leadingContent = { CircleIcon(Icons.Outlined.Storefront) },
                )
                if (index < detail.recentTransactions.lastIndex) HorizontalDivider(modifier = Modifier.padding(start = 64.dp))
            }
        }
    }
}

@Composable
private fun CashSaleDialog(
    plans: List<CashPlanOption>,
    routers: List<SalesRouterOption>,
    isBusy: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (CashSaleInput) -> Unit,
) {
    var planId by remember(plans) { mutableStateOf(plans.firstOrNull()?.id) }
    var routerId by remember(routers) { mutableStateOf(routers.firstOrNull()?.id) }
    var name by rememberSaveable { mutableStateOf("") }
    var phone by rememberSaveable { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = { if (!isBusy) onDismiss() },
        title = { Text("Record direct cash activation") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                ChoiceField("Plan", planId, plans.map { it.id to (it.name + " | " + money(it.priceKobo)) }, { planId = it }, !isBusy)
                ChoiceField("Router", routerId, routers.map { it.id to listOfNotNull(it.name, it.location).joinToString(" | ") }, { routerId = it }, !isBusy)
                OutlinedTextField(name, { name = it }, label = { Text("Customer name (optional)") }, singleLine = true, enabled = !isBusy, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(
                    phone,
                    { phone = it },
                    label = { Text("Phone (optional)") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                    singleLine = true,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                )
                Text("This records a paid sale, issues access immediately, and accrues the HotFii fee.", style = MaterialTheme.typography.bodySmall)
            }
        },
        confirmButton = {
            TextButton(
                enabled = !isBusy && planId != null && routerId != null,
                onClick = { onSubmit(CashSaleInput(requireNotNull(planId), requireNotNull(routerId), name.trim().ifEmpty { null }, phone.trim().ifEmpty { null })) },
            ) {
                if (isBusy) CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp) else Text("Record & activate")
            }
        },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !isBusy) { Text("Cancel") } },
    )
}

@Composable
private fun CredentialDialog(credential: IssuedCredential, onDismiss: () -> Unit) {
    val clipboard = LocalClipboardManager.current
    val value = credential.username.orEmpty() + " / " + credential.password.orEmpty()
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Access activated") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("Give these one-time credentials to the customer now.")
                SelectionContainer {
                    Text("Username: " + credential.username.orEmpty() + "\nPassword: " + credential.password.orEmpty(), fontWeight = FontWeight.SemiBold)
                }
            }
        },
        confirmButton = { TextButton(onClick = { clipboard.setText(AnnotatedString(value)); onDismiss() }) { Text("Copy & close") } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Close") } },
    )
}

@Composable
private fun SalesFilterDialog(
    filters: SalesFilters,
    catalog: SalesCatalog?,
    onDismiss: () -> Unit,
    onApply: (SalesFilters) -> Unit,
) {
    var search by remember(filters) { mutableStateOf(filters.search) }
    var status by remember(filters) { mutableStateOf(filters.status) }
    var channel by remember(filters) { mutableStateOf(filters.channel) }
    var router by remember(filters) { mutableStateOf(filters.routerId) }
    var from by remember(filters) { mutableStateOf(filters.from.orEmpty()) }
    var to by remember(filters) { mutableStateOf(filters.to.orEmpty()) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter sales") },
        text = {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                item { OutlinedTextField(search, { search = it }, label = { Text("Reference or customer") }, leadingIcon = { Icon(Icons.Outlined.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth()) }
                item { ChoiceField("Channel", channel, listOf(null to "All channels") + catalog?.options?.channels.orEmpty().map { it.value to it.label }, { channel = it }, true) }
                item { ChoiceField("Status", status, listOf(null to "Any status") + catalog?.options?.statuses.orEmpty().map { it to it.replaceFirstChar(Char::uppercase) }, { status = it }, true) }
                item { ChoiceField("Router", router, listOf(null to "All routers") + catalog?.options?.routers.orEmpty().map { it.id to it.name }, { router = it }, true) }
                item { OutlinedTextField(from, { from = it }, label = { Text("From (YYYY-MM-DD)") }, singleLine = true, modifier = Modifier.fillMaxWidth()) }
                item { OutlinedTextField(to, { to = it }, label = { Text("To (YYYY-MM-DD)") }, singleLine = true, modifier = Modifier.fillMaxWidth()) }
            }
        },
        confirmButton = { TextButton(onClick = { onApply(SalesFilters(search.trim(), status, channel, from.trim().ifEmpty { null }, to.trim().ifEmpty { null }, router)) }) { Text("Apply") } },
        dismissButton = {
            Row {
                TextButton(onClick = { onApply(SalesFilters()) }) { Text("Clear") }
                TextButton(onClick = onDismiss) { Text("Cancel") }
            }
        },
    )
}

@Composable
private fun CustomerFilterDialog(
    filters: CustomerFilters,
    catalog: CustomerCatalog?,
    onDismiss: () -> Unit,
    onApply: (CustomerFilters) -> Unit,
) {
    var search by remember(filters) { mutableStateOf(filters.search) }
    var type by remember(filters) { mutableStateOf(filters.type) }
    var status by remember(filters) { mutableStateOf(filters.status) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter customers") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(search, { search = it }, label = { Text("Name, phone, or email") }, leadingIcon = { Icon(Icons.Outlined.Search, null) }, singleLine = true, modifier = Modifier.fillMaxWidth())
                ChoiceField("Type", type, listOf(null to "All types") + catalog?.options?.types.orEmpty().map { it to it.replaceFirstChar(Char::uppercase) }, { type = it }, true)
                ChoiceField("Status", status, listOf(null to "Any status") + catalog?.options?.statuses.orEmpty().map { it to it.replaceFirstChar(Char::uppercase) }, { status = it }, true)
            }
        },
        confirmButton = { TextButton(onClick = { onApply(CustomerFilters(search.trim(), type, status)) }) { Text("Apply") } },
        dismissButton = {
            Row {
                TextButton(onClick = { onApply(CustomerFilters()) }) { Text("Clear") }
                TextButton(onClick = onDismiss) { Text("Cancel") }
            }
        },
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
                shape = MaterialTheme.shapes.extraSmall,
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
                modifier = Modifier.fillMaxWidth().clickable(enabled = enabled) { expanded = true },
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
private fun PaginationRow(pagination: SalesPagination, onPage: (Int) -> Unit) {
    if (pagination.lastPage <= 1) return
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        TextButton({ onPage(pagination.currentPage - 1) }, enabled = pagination.currentPage > 1) { Text("Previous") }
        Text("Page " + pagination.currentPage + " of " + pagination.lastPage, style = MaterialTheme.typography.labelLarge)
        TextButton({ onPage(pagination.currentPage + 1) }, enabled = pagination.currentPage < pagination.lastPage) { Text("Next") }
    }
}

@Composable
private fun CircleIcon(icon: ImageVector) {
    Surface(shape = CircleShape, color = MaterialTheme.colorScheme.secondaryContainer, modifier = Modifier.size(42.dp)) {
        Box(contentAlignment = Alignment.Center) { Icon(icon, null) }
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

@Composable
private fun SectionLabel(text: String) {
    Text(text, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 22.dp, bottom = 8.dp))
}

private fun money(kobo: Long): String = NumberFormat.getCurrencyInstance(Locale.forLanguageTag("en-NG")).apply {
    currency = Currency.getInstance("NGN")
    maximumFractionDigits = if (kobo % 100 == 0L) 0 else 2
}.format(kobo / 100.0)

private fun shortDate(value: String?): String? {
    if (value == null) return null
    return runCatching { OffsetDateTime.parse(value).format(DateTimeFormatter.ofPattern("d MMM, HH:mm")) }.getOrElse { value }
}

private fun dataSize(bytes: Long): String = when {
    bytes >= 1_073_741_824 -> String.format(Locale.US, "%.1f GB", bytes / 1_073_741_824.0)
    bytes >= 1_048_576 -> String.format(Locale.US, "%.1f MB", bytes / 1_048_576.0)
    bytes >= 1024 -> String.format(Locale.US, "%.1f KB", bytes / 1024.0)
    else -> bytes.toString() + " B"
}
