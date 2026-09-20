package com.innobytes.hotfii.domain

data class PlanCatalog(
    val plans: List<AccessPlanSummary>,
    val pagination: PlanPagination,
    val options: PlanOptions,
    val permissions: PlanPermissions,
)

data class PlanPagination(
    val currentPage: Int,
    val lastPage: Int,
    val perPage: Int,
    val total: Int,
)

data class PlanOptions(
    val types: List<PlanOption>,
    val validityModes: List<PlanOption>,
    val timezone: String,
)

data class PlanOption(val value: String, val label: String)
data class PlanPermissions(val canManage: Boolean)

data class AccessPlanSummary(
    val id: String,
    val name: String,
    val accessType: String,
    val priceKobo: Long,
    val durationMinutes: Int?,
    val dataLimitMb: Long?,
    val dataAllowance: String?,
    val downloadKbps: Int?,
    val uploadKbps: Int?,
    val simultaneousUse: Int,
    val validityDays: Int?,
    val validityMode: String,
    val validityLabel: String,
    val startsOnFirstUse: Boolean,
    val isActive: Boolean,
    val isUsed: Boolean,
    val canEdit: Boolean,
    val canDelete: Boolean,
    val createdAt: String?,
)

data class PlanFilters(
    val search: String = "",
    val type: String? = null,
    val state: String? = null,
)

data class PlanInput(
    val name: String,
    val accessType: String,
    val priceKobo: Long,
    val durationMinutes: Int?,
    val dataLimitMb: Long?,
    val downloadKbps: Int?,
    val uploadKbps: Int?,
    val simultaneousUse: Int,
    val validityDays: Int?,
    val validityMode: String,
    val isActive: Boolean = true,
)
