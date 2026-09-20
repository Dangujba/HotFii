package com.innobytes.hotfii.domain

data class NetworkCatalog(
    val summary: NetworkSummary,
    val routers: List<NetworkRouter>,
    val pagination: NetworkPagination,
    val options: NetworkOptions,
    val permissions: NetworkPermissions,
)

data class NetworkSummary(val total: Int, val online: Int, val offline: Int, val attention: Int)

data class NetworkRouter(
    val id: String,
    val name: String,
    val vendor: String,
    val vendorLabel: String,
    val model: String?,
    val adapter: String,
    val supportLevel: String,
    val status: String,
    val locationId: String?,
    val locationName: String?,
    val lastHeartbeatAt: String?,
    val sessionsCount: Int,
    val activeSessionsCount: Int,
)

data class NetworkRouterDetail(
    val router: NetworkRouterProfile,
    val latestTestRun: String?,
    val tests: List<NetworkTestResult>,
    val permissions: NetworkPermissions,
)

data class NetworkRouterProfile(
    val summary: NetworkRouter,
    val firmwareVersion: String?,
    val managementAddress: String?,
    val nasIdentifier: String,
    val capabilities: List<String>,
    val health: Map<String, String>,
    val certifiedAt: String?,
    val setup: NetworkSetup,
)

data class NetworkSetup(val configured: Boolean, val label: String)
data class NetworkTestResult(val key: String, val status: String, val message: String?, val checkedAt: String?)
data class NetworkPagination(val currentPage: Int, val lastPage: Int, val perPage: Int, val total: Int)
data class NetworkPermissions(val canManage: Boolean)
data class NetworkVendorOption(val value: String, val label: String)
data class NetworkLocationOption(val id: String, val name: String)

data class NetworkOptions(
    val statuses: List<String>,
    val vendors: List<NetworkVendorOption>,
    val locations: List<NetworkLocationOption>,
)

data class NetworkFilters(
    val search: String = "",
    val status: String? = null,
    val vendor: String? = null,
    val locationId: String? = null,
)

data class HotspotSessionCatalog(
    val summary: HotspotSessionSummary,
    val sessions: List<HotspotSessionRecord>,
    val pagination: NetworkPagination,
    val options: HotspotSessionOptions,
    val permissions: HotspotSessionPermissions,
)

data class HotspotSessionSummary(val live: Int, val recent: Int, val totalUsageBytes: Long)

data class HotspotSessionRecord(
    val id: String,
    val status: String,
    val username: String,
    val customerName: String?,
    val customerPhone: String?,
    val planName: String?,
    val routerId: String?,
    val routerName: String?,
    val clientName: String?,
    val macAddress: String?,
    val ipAddress: String?,
    val inputBytes: Long,
    val outputBytes: Long,
    val totalBytes: Long,
    val startedAt: String?,
    val expiresAt: String?,
    val stoppedAt: String?,
    val terminateCause: String?,
    val canDisconnect: Boolean,
)

data class HotspotSessionRouterOption(val id: String, val name: String)

data class HotspotSessionOptions(
    val statuses: List<String>,
    val routers: List<HotspotSessionRouterOption>,
)

data class HotspotSessionPermissions(val canDisconnect: Boolean)

data class HotspotSessionFilters(
    val view: String = "live",
    val search: String = "",
    val status: String? = null,
    val routerId: String? = null,
    val from: String? = null,
    val to: String? = null,
)

data class DisconnectResult(val session: HotspotSessionRecord, val message: String)
