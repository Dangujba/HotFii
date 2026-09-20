package com.innobytes.hotfii.ui.reports

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.ReportChannel
import com.innobytes.hotfii.domain.ReportPlan
import com.innobytes.hotfii.domain.ReportSalesTrend
import com.innobytes.hotfii.domain.ReportUsageTrend
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
private fun ChartHost(producer: CartesianChartModelProducer, columns: Boolean, modifier: Modifier) {
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
        modifier = modifier.fillMaxWidth().height(220.dp),
    )
}
