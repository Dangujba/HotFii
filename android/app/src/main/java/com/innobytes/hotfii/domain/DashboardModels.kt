package com.innobytes.hotfii.domain

data class DashboardSnapshot(
    val organizationName: String,
    val currency: String,
    val routers: List<RouterSummary>,
    val selectedRouter: RouterSummary?,
    val alerts: DashboardAlerts,
    val pulse: DashboardPulse,
    val revenue: RevenueTrend,
    val fleet: List<FleetState>,
    val fleetTotal: Int,
    val sessionsToday: HourlySessions,
    val topPlans: List<PlanRevenue>,
    val topPlansDays: Int,
    val networkHealth: List<NetworkHealth>,
    val recentTransactions: List<RecentTransaction>,
    val generatedAt: String,
)

data class RouterSummary(
    val id: String,
    val name: String,
    val location: String?,
    val status: String,
)

data class DashboardAlerts(
    val billingSuspended: Boolean,
    val paymentProfileRequired: Boolean,
)

data class DashboardDelta(
    val direction: String,
    val text: String,
)

data class DashboardPulse(
    val revenueTodayKobo: Long,
    val revenueYesterdayKobo: Long,
    val revenueDelta: DashboardDelta?,
    val salesToday: Int,
    val salesYesterday: Int,
    val salesDelta: DashboardDelta?,
    val activeSessions: Int,
    val sessionsStartedToday: Int,
    val sessionsStartedYesterday: Int,
    val sessionsDelta: DashboardDelta?,
    val onlineRouters: Int,
    val totalRouters: Int,
    val availableVouchers: Int,
    val vouchersInUse: Int,
)

data class RevenueTrend(
    val labels: List<String>,
    val valuesKobo: List<Long>,
    val totalKobo: Long,
    val bestKobo: Long,
    val days: Int,
)

data class FleetState(
    val key: String,
    val label: String,
    val tone: String,
    val value: Int,
)

data class HourlySessions(
    val labels: List<String>,
    val values: List<Int>,
    val total: Int,
    val peakHour: Int?,
    val peakValue: Int?,
)

data class PlanRevenue(
    val name: String,
    val revenueKobo: Long,
)

data class NetworkHealth(
    val id: String,
    val name: String,
    val location: String,
    val vendor: String,
    val status: String,
    val lastHeartbeatAt: String?,
)

data class RecentTransaction(
    val id: String,
    val reference: String,
    val routerName: String?,
    val channel: String,
    val status: String,
    val grossAmountKobo: Long,
    val paidAt: String?,
    val createdAt: String,
)
