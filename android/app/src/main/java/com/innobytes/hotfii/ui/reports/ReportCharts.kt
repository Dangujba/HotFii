package com.innobytes.hotfii.ui.reports

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.ReportChannel
import com.innobytes.hotfii.domain.ReportPlan
import com.innobytes.hotfii.domain.ReportSalesTrend
import com.innobytes.hotfii.domain.ReportUsageTrend
import com.innobytes.hotfii.domain.ReportVoucherStatus
import com.patrykandpatrick.vico.compose.cartesian.CartesianChartHost
import com.patrykandpatrick.vico.compose.cartesian.axis.HorizontalAxis
import com.patrykandpatrick.vico.compose.cartesian.axis.VerticalAxis
import com.patrykandpatrick.vico.compose.cartesian.data.CartesianChartModelProducer
import com.patrykandpatrick.vico.compose.cartesian.data.columnModel
import com.patrykandpatrick.vico.compose.cartesian.data.lineModel
import com.patrykandpatrick.vico.compose.cartesian.layer.rememberColumnCartesianLayer
import com.patrykandpatrick.vico.compose.cartesian.layer.rememberLineCartesianLayer
import com.patrykandpatrick.vico.compose.cartesian.rememberCartesianChart
import com.patrykandpatrick.vico.compose.cartesian.rememberVicoScrollState
import ir.ehsannarmani.compose_charts.PieChart
import ir.ehsannarmani.compose_charts.models.Pie

@Composable
fun SalesTrendChart(trend: ReportSalesTrend, modifier: Modifier = Modifier) {
    val producer = remember { CartesianChartModelProducer() }
    LaunchedEffect(trend) {
        producer.runTransaction {
            lineModel {
                series(trend.onlineKobo.map { it / 100.0 })
                series(trend.voucherKobo.map { it / 100.0 })
                series(trend.cashKobo.map { it / 100.0 })
            }
        }
    }
    ChartHost(producer, false, modifier)
}

@Composable
fun ChannelChart(channels: List<ReportChannel>, modifier: Modifier = Modifier) {
    val producer = remember { CartesianChartModelProducer() }
    LaunchedEffect(channels) {
        producer.runTransaction { columnModel { series(channels.map { it.totalKobo / 100.0 }.ifEmpty { listOf(0) }) } }
    }
    ChartHost(producer, true, modifier)
}

@Composable
fun PlanChart(plans: List<ReportPlan>, modifier: Modifier = Modifier) {
    val producer = remember { CartesianChartModelProducer() }
    LaunchedEffect(plans) {
        producer.runTransaction { columnModel { series(plans.map { it.totalKobo / 100.0 }.ifEmpty { listOf(0) }) } }
    }
    ChartHost(producer, true, modifier)
}

@Composable
fun UsageChart(trend: ReportUsageTrend, modifier: Modifier = Modifier) {
    val producer = remember { CartesianChartModelProducer() }
    LaunchedEffect(trend) {
        producer.runTransaction { lineModel { series(trend.sessions) } }
    }
    ChartHost(producer, false, modifier)
}

@Composable
fun VoucherStatusChart(status: ReportVoucherStatus, modifier: Modifier = Modifier) {
    val colors = listOf(
        MaterialTheme.colorScheme.tertiary,
        MaterialTheme.colorScheme.primary,
        MaterialTheme.colorScheme.error,
        MaterialTheme.colorScheme.outline,
    )
    val entries = listOf(
        Triple("Unused", status.unused, colors[0]),
        Triple("In use", status.active, colors[1]),
        Triple("Expired", status.expired, colors[2]),
        Triple("Revoked", status.revoked, colors[3]),
    )
    val slices = entries
        .filter { it.second > 0 }
        .map { (label, count, color) -> Pie(label = label, data = count.toDouble(), color = color, selectedColor = color) }

    Column(
        modifier = modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        if (slices.isEmpty()) {
            Box(Modifier.fillMaxWidth().height(180.dp), contentAlignment = Alignment.Center) {
                Text("No vouchers yet", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        } else {
            Box(Modifier.fillMaxWidth().height(220.dp).clipToBounds(), contentAlignment = Alignment.Center) {
                PieChart(
                    modifier = Modifier.size(210.dp),
                    data = slices,
                    onPieClick = {},
                    selectedScale = 1f,
                    spaceDegree = 3f,
                    style = Pie.Style.Stroke(width = 64.dp),
                )
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(status.total.toString(), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                    Text("Total", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        StatusLegendRow(entries.take(2))
        StatusLegendRow(entries.drop(2))
    }
}

@Composable
private fun StatusLegendRow(entries: List<Triple<String, Int, Color>>) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        entries.forEach { (label, count, color) ->
            Row(
                modifier = Modifier.weight(1f),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Box(Modifier.size(10.dp).background(color, CircleShape))
                Column(Modifier.weight(1f)) {
                    Text(label, style = MaterialTheme.typography.labelMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Text(count.toString(), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun ChartHost(producer: CartesianChartModelProducer, columns: Boolean, modifier: Modifier) {
    Box(modifier.fillMaxWidth().height(220.dp).padding(horizontal = 8.dp).clipToBounds()) {
        CartesianChartHost(
            chart = if (columns) {
                rememberCartesianChart(
                    rememberColumnCartesianLayer(),
                    startAxis = VerticalAxis.rememberStart(),
                    bottomAxis = HorizontalAxis.rememberBottom(),
                )
            } else {
                rememberCartesianChart(
                    rememberLineCartesianLayer(),
                    startAxis = VerticalAxis.rememberStart(),
                    bottomAxis = HorizontalAxis.rememberBottom(),
                )
            },
            modelProducer = producer,
            scrollState = rememberVicoScrollState(scrollEnabled = false),
            modifier = Modifier.matchParentSize(),
        )
    }
}
