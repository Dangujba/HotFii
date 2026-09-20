package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.DashboardAlerts
import com.innobytes.hotfii.domain.DashboardDelta
import com.innobytes.hotfii.domain.DashboardPulse
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.FleetState
import com.innobytes.hotfii.domain.HourlySessions
import com.innobytes.hotfii.domain.NetworkHealth
import com.innobytes.hotfii.domain.PlanRevenue
import com.innobytes.hotfii.domain.RecentTransaction
import com.innobytes.hotfii.domain.RevenueTrend
import com.innobytes.hotfii.domain.RouterSummary

data class DashboardDto(
    val organization: DashboardOrganizationDto,
    val scope: DashboardScopeDto,
    val alerts: DashboardAlertsDto,
    val pulse: DashboardPulseDto,
    val revenue: RevenueTrendDto,
    val fleet: FleetDto,
    val sessionsToday: HourlySessionsDto,
    val topPlans: TopPlansDto,
    val networkHealth: List<NetworkHealthDto>,
    val recentTransactions: List<RecentTransactionDto>,
    val generatedAt: String,
) {
    fun toDomain() = DashboardSnapshot(
        organizationName = organization.name,
        currency = organization.currency,
        routers = scope.routers.map(RouterDto::toDomain),
        selectedRouter = scope.router?.toDomain(),
        alerts = alerts.toDomain(),
        pulse = pulse.toDomain(),
        revenue = revenue.toDomain(),
        fleet = fleet.rows.map(FleetStateDto::toDomain),
        fleetTotal = fleet.total,
        sessionsToday = sessionsToday.toDomain(),
        topPlans = topPlans.items.map(PlanRevenueDto::toDomain),
        topPlansDays = topPlans.days,
        networkHealth = networkHealth.map(NetworkHealthDto::toDomain),
        recentTransactions = recentTransactions.map(RecentTransactionDto::toDomain),
        generatedAt = generatedAt,
    )
}

data class DashboardOrganizationDto(
    val id: String,
    val name: String,
    val currency: String,
    val timezone: String,
    val status: String,
    val mode: String,
)

data class DashboardScopeDto(val router: RouterDto?, val routers: List<RouterDto>)

data class RouterDto(val id: String, val name: String, val location: String?, val status: String) {
    fun toDomain() = RouterSummary(id, name, location, status)
}

data class DashboardAlertsDto(val billingSuspended: Boolean, val paymentProfileRequired: Boolean) {
    fun toDomain() = DashboardAlerts(billingSuspended, paymentProfileRequired)
}

data class DashboardDeltaDto(val direction: String, val text: String) {
    fun toDomain() = DashboardDelta(direction, text)
}

data class DashboardPulseDto(
    val revenueTodayKobo: Long,
    val revenueYesterdayKobo: Long,
    val revenueDelta: DashboardDeltaDto?,
    val salesToday: Int,
    val salesYesterday: Int,
    val salesDelta: DashboardDeltaDto?,
    val activeSessions: Int,
    val sessionsStartedToday: Int,
    val sessionsStartedYesterday: Int,
    val sessionsDelta: DashboardDeltaDto?,
    val onlineRouters: Int,
    val totalRouters: Int,
    val availableVouchers: Int,
    val vouchersInUse: Int,
) {
    fun toDomain() = DashboardPulse(
        revenueTodayKobo,
        revenueYesterdayKobo,
        revenueDelta?.toDomain(),
        salesToday,
        salesYesterday,
        salesDelta?.toDomain(),
        activeSessions,
        sessionsStartedToday,
        sessionsStartedYesterday,
        sessionsDelta?.toDomain(),
        onlineRouters,
        totalRouters,
        availableVouchers,
        vouchersInUse,
    )
}

data class RevenueTrendDto(
    val labels: List<String>,
    val valuesKobo: List<Long>,
    val totalKobo: Long,
    val bestKobo: Long,
    val days: Int,
) {
    fun toDomain() = RevenueTrend(labels, valuesKobo, totalKobo, bestKobo, days)
}

data class FleetDto(val rows: List<FleetStateDto>, val total: Int)

data class FleetStateDto(val key: String, val label: String, val tone: String, val value: Int) {
    fun toDomain() = FleetState(key, label, tone, value)
}

data class HourlySessionsDto(
    val labels: List<String>,
    val values: List<Int>,
    val total: Int,
    val peak: HourlyPeakDto?,
) {
    fun toDomain() = HourlySessions(labels, values, total, peak?.hour, peak?.value)
}

data class HourlyPeakDto(val hour: Int, val value: Int)

data class TopPlansDto(val items: List<PlanRevenueDto>, val days: Int)

data class PlanRevenueDto(val name: String, val revenueKobo: Long) {
    fun toDomain() = PlanRevenue(name, revenueKobo)
}

data class NetworkHealthDto(
    val id: String,
    val name: String,
    val location: String,
    val vendor: String,
    val status: String,
    val lastHeartbeatAt: String?,
) {
    fun toDomain() = NetworkHealth(id, name, location, vendor, status, lastHeartbeatAt)
}

data class RecentTransactionDto(
    val id: String,
    val reference: String,
    val routerName: String?,
    val channel: String,
    val status: String,
    val grossAmountKobo: Long,
    val paidAt: String?,
    val createdAt: String,
) {
    fun toDomain() = RecentTransaction(id, reference, routerName, channel, status, grossAmountKobo, paidAt, createdAt)
}
