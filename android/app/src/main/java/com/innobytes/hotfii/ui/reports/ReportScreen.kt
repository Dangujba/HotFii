package com.innobytes.hotfii.ui.reports

import android.content.Intent
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.automirrored.outlined.ReceiptLong
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
import androidx.core.content.FileProvider
import com.innobytes.hotfii.domain.*
import com.innobytes.hotfii.ui.ReportUiState
import com.innobytes.hotfii.ui.components.DateFilterField
import java.io.File
import java.text.NumberFormat
import java.util.Currency
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ReportScreen(
    organizationId: String?,
    organizationName: String?,
    state: ReportUiState,
    onBack: () -> Unit,
    onLoad: (ReportFilters) -> Unit,
    onExport: (String) -> Unit,
    onExportConsumed: () -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var showFilters by rememberSaveable { mutableStateOf(false) }
    val context = LocalContext.current
    BackHandler(onBack = onBack)

    LaunchedEffect(organizationId) { if (organizationId != null) onLoad(ReportFilters()) }
    LaunchedEffect(state.pendingExport) {
        state.pendingExport?.let { export ->
            val file = File(export.path)
            val uri = FileProvider.getUriForFile(context, context.packageName + ".files", file)
            context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
                type = export.mimeType
                putExtra(Intent.EXTRA_STREAM, uri)
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            }, "Share HotFii report"))
            onExportConsumed()
        }
    }

    Scaffold(topBar = {
        TopAppBar(
            title = { Column { Text("Reports"); organizationName?.let { Text(it, style = MaterialTheme.typography.labelMedium) } } },
            navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, "Back") } },
            actions = {
                IconButton({ showFilters = true }) { Icon(Icons.Outlined.FilterList, "Filter reports") }
                IconButton({ onExport("csv") }, enabled = !state.isActionRunning) { Icon(Icons.Outlined.TableView, "Share CSV") }
                IconButton({ onExport("pdf") }, enabled = !state.isActionRunning) { Icon(Icons.Outlined.PictureAsPdf, "Share PDF") }
            },
        )
    }) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding)) {
            if (state.isLoading || state.isActionRunning) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
            state.error?.let { item { Feedback(it, true, onFeedbackDismissed) } }
            state.notice?.let { item { Feedback(it, false, onFeedbackDismissed) } }
            val report = state.report
            if (report != null) {
                item {
                    Text("${report.from} to ${report.to}", Modifier.padding(16.dp), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Row(Modifier.fillMaxWidth().padding(horizontal = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Metric("Sales", report.summary.sales.toString(), Icons.AutoMirrored.Outlined.ReceiptLong, Modifier.weight(1f))
                        Metric("Revenue", money(report.summary.grossKobo), Icons.Outlined.Payments, Modifier.weight(1f))
                    }
                    Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Metric("Sessions", report.usage.sessions.toString(), Icons.Outlined.Wifi, Modifier.weight(1f))
                        Metric("Usage", dataSize(report.usage.bytes), Icons.Outlined.DataUsage, Modifier.weight(1f))
                    }
                }
                item { ChartSection("Sales trend", "Online, voucher, and direct cash revenue") { SalesTrendChart(report.salesTrend) } }
                item { ChartSection("Sales channels", "Revenue by payment channel") { ChannelChart(report.channels) } }
                item {
                    report.channels.forEach { channel ->
                        ListItem(headlineContent = { Text(channel.label) }, supportingContent = { Text("${channel.sales} sales") }, trailingContent = { Text(money(channel.totalKobo), fontWeight = FontWeight.SemiBold) })
                        HorizontalDivider(Modifier.padding(start = 16.dp))
                    }
                }
                item { ChartSection("Top plans", "Highest-grossing access plans") { PlanChart(report.topPlans) } }
                item {
                    report.topPlans.forEach { plan ->
                        ListItem(headlineContent = { Text(plan.name) }, supportingContent = { Text("${plan.sales} sales") }, trailingContent = { Text(money(plan.totalKobo), fontWeight = FontWeight.SemiBold) })
                        HorizontalDivider(Modifier.padding(start = 16.dp))
                    }
                }
                item { ChartSection("Session activity", "Sessions started during the period") { UsageChart(report.usageTrend) } }
            } else if (!state.isLoading) item { Text("No report data is available.", Modifier.padding(32.dp)) }
        }
    }
    if (showFilters) ReportFilterDialog(state.filters, state.report?.routers.orEmpty(), { showFilters = false }) {
        showFilters = false
        onLoad(it)
    }
}

@Composable private fun ChartSection(title: String, subtitle: String, chart: @Composable () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(top = 20.dp)) {
        Text(title, Modifier.padding(horizontal = 16.dp), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        Text(subtitle, Modifier.padding(horizontal = 16.dp), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        chart()
        HorizontalDivider()
    }
}

@Composable private fun Metric(label: String, value: String, icon: androidx.compose.ui.graphics.vector.ImageVector, modifier: Modifier) {
    Surface(modifier, shape = MaterialTheme.shapes.small, border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.padding(12.dp)) { Icon(icon, null, tint = MaterialTheme.colorScheme.primary); Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis); Text(label, style = MaterialTheme.typography.labelSmall) }
    }
}

@Composable private fun ReportFilterDialog(filters: ReportFilters, routers: List<FinanceRouterOption>, onDismiss: () -> Unit, onApply: (ReportFilters) -> Unit) {
    var from by remember(filters) { mutableStateOf(filters.from.orEmpty()) }
    var to by remember(filters) { mutableStateOf(filters.to.orEmpty()) }
    var router by remember(filters) { mutableStateOf(filters.routerId) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter report") },
        text = { Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            DateFilterField("From", from) { from = it }
            DateFilterField("To", to) { to = it }
            ChoiceField("Router", router, listOf(null to "All routers") + routers.map { it.id to it.name }) { router = it }
        } },
        confirmButton = { TextButton({ onApply(ReportFilters(from.ifEmpty { null }, to.ifEmpty { null }, router)) }) { Text("Apply") } },
        dismissButton = { Row { TextButton({ onApply(ReportFilters()) }) { Text("Clear") }; TextButton(onDismiss) { Text("Cancel") } } },
    )
}

@Composable private fun ChoiceField(label: String, selected: String?, options: List<Pair<String?, String>>, onSelected: (String?) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Column { Text(label, style = MaterialTheme.typography.labelLarge); Box {
        Surface(shape = MaterialTheme.shapes.extraSmall, border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline), modifier = Modifier.fillMaxWidth().clickable { expanded = true }) {
            Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) { Text(options.firstOrNull { it.first == selected }?.second ?: "All", Modifier.weight(1f)); Icon(Icons.Outlined.ExpandMore, null) }
        }
        DropdownMenu(expanded, { expanded = false }) { options.forEach { option -> DropdownMenuItem(text = { Text(option.second) }, onClick = { expanded = false; onSelected(option.first) }) } }
    } }
}

@Composable private fun Feedback(message: String, error: Boolean, dismiss: () -> Unit) {
    Surface(color = if (error) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer) { Row(Modifier.fillMaxWidth().padding(start = 16.dp), verticalAlignment = Alignment.CenterVertically) { Text(message, Modifier.weight(1f)); IconButton(dismiss) { Icon(Icons.Outlined.Close, "Dismiss") } } }
}

private fun money(kobo: Long) = NumberFormat.getCurrencyInstance(Locale.forLanguageTag("en-NG")).apply { currency = Currency.getInstance("NGN"); maximumFractionDigits = if (kobo % 100 == 0L) 0 else 2 }.format(kobo / 100.0)
private fun dataSize(bytes: Long) = when { bytes >= 1_073_741_824 -> String.format(Locale.US, "%.1f GB", bytes / 1_073_741_824.0); bytes >= 1_048_576 -> String.format(Locale.US, "%.1f MB", bytes / 1_048_576.0); bytes >= 1024 -> String.format(Locale.US, "%.1f KB", bytes / 1024.0); else -> "$bytes B" }
