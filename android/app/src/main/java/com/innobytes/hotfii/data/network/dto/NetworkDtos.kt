package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.*

data class NetworkCatalogDto(
    val summary: NetworkSummaryDto,
    val routers: List<NetworkRouterDto>,
    val pagination: NetworkPaginationDto,
    val options: NetworkOptionsDto,
    val permissions: NetworkPermissionsDto,
) {
    fun toDomain() = NetworkCatalog(
        summary.toDomain(),
        routers.map(NetworkRouterDto::toDomain),
        pagination.toDomain(),
        options.toDomain(),
        permissions.toDomain(),
    )
}

data class NetworkSummaryDto(val total: Int, val online: Int, val offline: Int, val attention: Int) {
    fun toDomain() = NetworkSummary(total, online, offline, attention)
}

data class NetworkRouterDto(
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
) {
    fun toDomain() = NetworkRouter(
        id, name, vendor, vendorLabel, model, adapter, supportLevel, status,
        locationId, locationName, lastHeartbeatAt, sessionsCount, activeSessionsCount,
    )
}

data class NetworkRouterDetailDto(
    val router: NetworkRouterProfileDto,
    val latestTestRun: String?,
    val tests: List<NetworkTestResultDto>,
    val permissions: NetworkPermissionsDto,
) {
    fun toDomain() = NetworkRouterDetail(
        router.toDomain(),
        latestTestRun,
        tests.map(NetworkTestResultDto::toDomain),
        permissions.toDomain(),
    )
}

data class NetworkRouterProfileDto(
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
    val firmwareVersion: String?,
    val managementAddress: String?,
    val nasIdentifier: String,
    val capabilities: List<String>,
    val health: Map<String, Any?>,
    val certifiedAt: String?,
    val setup: NetworkSetupDto,
) {
    fun toDomain() = NetworkRouterProfile(
        summary = NetworkRouter(
            id, name, vendor, vendorLabel, model, adapter, supportLevel, status,
            locationId, locationName, lastHeartbeatAt, sessionsCount, activeSessionsCount,
        ),
        firmwareVersion = firmwareVersion,
        managementAddress = managementAddress,
        nasIdentifier = nasIdentifier,
        capabilities = capabilities,
        health = health.mapValues { (_, value) -> value?.toString().orEmpty() },
        certifiedAt = certifiedAt,
        setup = setup.toDomain(),
    )
}

data class NetworkSetupDto(val configured: Boolean, val label: String) {
    fun toDomain() = NetworkSetup(configured, label)
}

data class NetworkTestResultDto(val key: String, val status: String, val message: String?, val checkedAt: String?) {
    fun toDomain() = NetworkTestResult(key, status, message, checkedAt)
}

data class NetworkPaginationDto(val currentPage: Int, val lastPage: Int, val perPage: Int, val total: Int) {
    fun toDomain() = NetworkPagination(currentPage, lastPage, perPage, total)
}

data class NetworkPermissionsDto(val canManage: Boolean) {
    fun toDomain() = NetworkPermissions(canManage)
}

data class NetworkVendorOptionDto(val value: String, val label: String) {
    fun toDomain() = NetworkVendorOption(value, label)
}

data class NetworkLocationOptionDto(val id: String, val name: String) {
    fun toDomain() = NetworkLocationOption(id, name)
}

data class NetworkOptionsDto(
    val statuses: List<String>,
    val vendors: List<NetworkVendorOptionDto>,
    val locations: List<NetworkLocationOptionDto>,
) {
    fun toDomain() = NetworkOptions(
        statuses,
        vendors.map(NetworkVendorOptionDto::toDomain),
        locations.map(NetworkLocationOptionDto::toDomain),
    )
}

data class HotspotSessionCatalogDto(
    val summary: HotspotSessionSummaryDto,
    val sessions: List<HotspotSessionRecordDto>,
    val pagination: NetworkPaginationDto,
    val options: HotspotSessionOptionsDto,
    val permissions: HotspotSessionPermissionsDto,
) {
    fun toDomain() = HotspotSessionCatalog(
        summary.toDomain(), sessions.map(HotspotSessionRecordDto::toDomain), pagination.toDomain(),
        options.toDomain(), permissions.toDomain(),
    )
}

data class HotspotSessionSummaryDto(val live: Int, val recent: Int, val totalUsageBytes: Long) {
    fun toDomain() = HotspotSessionSummary(live, recent, totalUsageBytes)
}

data class HotspotSessionRecordDto(
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
) {
    fun toDomain() = HotspotSessionRecord(
        id, status, username, customerName, customerPhone, planName, routerId, routerName,
        clientName, macAddress, ipAddress, inputBytes, outputBytes, totalBytes, startedAt,
        expiresAt, stoppedAt, terminateCause, canDisconnect,
    )
}

data class HotspotSessionRouterOptionDto(val id: String, val name: String) {
    fun toDomain() = HotspotSessionRouterOption(id, name)
}

data class HotspotSessionOptionsDto(
    val statuses: List<String>,
    val routers: List<HotspotSessionRouterOptionDto>,
) {
    fun toDomain() = HotspotSessionOptions(statuses, routers.map(HotspotSessionRouterOptionDto::toDomain))
}

data class HotspotSessionPermissionsDto(val canDisconnect: Boolean) {
    fun toDomain() = HotspotSessionPermissions(canDisconnect)
}

data class ApiMessageEnvelope<T>(val data: T, val message: String)
data class MessageDto(val message: String)
