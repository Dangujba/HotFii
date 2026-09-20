package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.*

data class VoucherCatalogDto(
    val batches: List<VoucherBatchDto>,
    val pagination: VoucherPaginationDto,
    val options: VoucherOptionsDto,
    val permissions: VoucherPermissionsDto,
) {
    fun toDomain() = VoucherCatalog(
        batches.map(VoucherBatchDto::toSummary), pagination.toDomain(),
        options.toDomain(), permissions.toDomain(),
    )
}

data class VoucherPaginationDto(val currentPage: Int, val lastPage: Int, val perPage: Int, val total: Int) {
    fun toDomain() = VoucherPagination(currentPage, lastPage, perPage, total)
}
data class VoucherPermissionsDto(val canCreate: Boolean, val canManage: Boolean) {
    fun toDomain() = VoucherPermissions(canCreate, canManage)
}
data class VoucherOptionsDto(
    val routers: List<VoucherRouterOptionDto>,
    val plans: List<VoucherPlanOptionDto>,
    val pinLengths: List<Int>,
    val pinFormats: List<VoucherPinFormatOptionDto>,
    val statuses: List<String>,
) {
    fun toDomain() = VoucherOptions(
        routers.map(VoucherRouterOptionDto::toDomain), plans.map(VoucherPlanOptionDto::toDomain),
        pinLengths, pinFormats.map(VoucherPinFormatOptionDto::toDomain), statuses,
    )
}
data class VoucherRouterOptionDto(val id: String, val name: String, val nasIdentifier: String, val status: String) {
    fun toDomain() = VoucherRouterOption(id, name, nasIdentifier, status)
}
data class VoucherPlanOptionDto(
    val id: String, val name: String, val priceKobo: Long, val isActive: Boolean, val validity: String,
) {
    fun toDomain() = VoucherPlanOption(id, name, priceKobo, isActive, validity)
}
data class VoucherPinFormatOptionDto(val value: String, val label: String) {
    fun toDomain() = VoucherPinFormatOption(value, label)
}
data class VoucherPlanRefDto(val id: String, val name: String) {
    fun toDomain() = VoucherPlanRef(id, name)
}
data class VoucherRouterRefDto(val id: String, val name: String) {
    fun toDomain() = VoucherRouterRef(id, name)
}
data class VoucherCountsDto(val available: Int, val active: Int, val expired: Int, val revoked: Int) {
    fun toDomain() = VoucherCounts(available, active, expired, revoked)
}

data class VoucherBatchDto(
    val id: String,
    val reference: String,
    val quantity: Int,
    val pinLength: Int,
    val retailPriceKobo: Long,
    val retailValueKobo: Long,
    val status: String,
    val plan: VoucherPlanRefDto,
    val router: VoucherRouterRefDto?,
    val counts: VoucherCountsDto,
    val canEdit: Boolean,
    val canDelete: Boolean,
    val createdAt: String,
    val printedAt: String?,
    val vouchers: List<VoucherItemDto> = emptyList(),
) {
    fun toSummary() = VoucherBatchSummary(
        id, reference, quantity, pinLength, retailPriceKobo, retailValueKobo,
        status, plan.toDomain(), router?.toDomain(), counts.toDomain(),
        canEdit, canDelete, createdAt, printedAt,
    )
    fun toDetail() = VoucherBatchDetail(toSummary(), vouchers.map(VoucherItemDto::toDomain))
}

data class VoucherItemDto(
    val id: String, val serialNumber: String, val codeLastFour: String, val status: String,
    val soldAt: String?, val activatedAt: String?, val expiresAt: String?,
) {
    fun toDomain() = VoucherItem(id, serialNumber, codeLastFour, status, soldAt, activatedAt, expiresAt)
}

data class VoucherCreateRequestDto(
    val networkDeviceId: String,
    val accessPlanId: String,
    val quantity: Int,
    val retailPriceKobo: Long?,
    val pinFormat: String,
    val pinLength: Int,
    val dashedPin: Boolean,
) {
    companion object {
        fun from(input: VoucherCreateInput) = VoucherCreateRequestDto(
            input.routerId, input.planId, input.quantity, input.retailPriceKobo,
            input.pinFormat, input.pinLength, input.dashedPin,
        )
    }
}
data class VoucherEditRequestDto(val networkDeviceId: String, val accessPlanId: String, val retailPriceKobo: Long) {
    companion object {
        fun from(input: VoucherEditInput) =
            VoucherEditRequestDto(input.routerId, input.planId, input.retailPriceKobo)
    }
}
data class VoucherShareDto(
    val reference: String,
    val organizationName: String,
    val planName: String,
    val access: String,
    val validity: String,
    val coverage: String,
    val priceKobo: Long,
    val codes: List<VoucherShareCodeDto>,
) {
    fun toDomain() = VoucherShare(
        reference, organizationName, planName, access, validity,
        coverage, priceKobo, codes.map(VoucherShareCodeDto::toDomain),
    )
}
data class VoucherShareCodeDto(val id: String, val serialNumber: String, val code: String) {
    fun toDomain() = VoucherShareCode(id, serialNumber, code)
}
