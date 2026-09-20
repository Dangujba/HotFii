package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.AccessPlanSummary
import com.innobytes.hotfii.domain.PlanCatalog
import com.innobytes.hotfii.domain.PlanInput
import com.innobytes.hotfii.domain.PlanOption
import com.innobytes.hotfii.domain.PlanOptions
import com.innobytes.hotfii.domain.PlanPagination
import com.innobytes.hotfii.domain.PlanPermissions

data class PlanCatalogDto(
    val plans: List<AccessPlanDto>,
    val pagination: PlanPaginationDto,
    val options: PlanOptionsDto,
    val permissions: PlanPermissionsDto,
) {
    fun toDomain() = PlanCatalog(
        plans = plans.map(AccessPlanDto::toDomain),
        pagination = pagination.toDomain(),
        options = options.toDomain(),
        permissions = permissions.toDomain(),
    )
}

data class PlanPaginationDto(
    val currentPage: Int,
    val lastPage: Int,
    val perPage: Int,
    val total: Int,
) {
    fun toDomain() = PlanPagination(currentPage, lastPage, perPage, total)
}

data class PlanOptionsDto(
    val types: List<PlanOptionDto>,
    val validityModes: List<PlanOptionDto>,
    val timezone: String,
) {
    fun toDomain() = PlanOptions(
        types.map(PlanOptionDto::toDomain),
        validityModes.map(PlanOptionDto::toDomain),
        timezone,
    )
}

data class PlanOptionDto(val value: String, val label: String) {
    fun toDomain() = PlanOption(value, label)
}

data class PlanPermissionsDto(val canManage: Boolean) {
    fun toDomain() = PlanPermissions(canManage)
}

data class AccessPlanDto(
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
) {
    fun toDomain() = AccessPlanSummary(
        id, name, accessType, priceKobo, durationMinutes, dataLimitMb, dataAllowance,
        downloadKbps, uploadKbps, simultaneousUse, validityDays, validityMode,
        validityLabel, startsOnFirstUse, isActive, isUsed, canEdit, canDelete, createdAt,
    )
}

data class PlanRequestDto(
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
    val isActive: Boolean,
) {
    companion object {
        fun from(input: PlanInput) = PlanRequestDto(
            input.name,
            input.accessType,
            input.priceKobo,
            input.durationMinutes,
            input.dataLimitMb,
            input.downloadKbps,
            input.uploadKbps,
            input.simultaneousUse,
            input.validityDays,
            input.validityMode,
            input.isActive,
        )
    }
}
