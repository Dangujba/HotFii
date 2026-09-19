package com.innobytes.hotfii.domain

data class VoucherCatalog(
    val batches: List<VoucherBatchSummary>,
    val pagination: VoucherPagination,
    val options: VoucherOptions,
    val permissions: VoucherPermissions,
)

data class VoucherPagination(val currentPage: Int, val lastPage: Int, val perPage: Int, val total: Int)
data class VoucherPermissions(val canCreate: Boolean, val canManage: Boolean)

data class VoucherOptions(
    val routers: List<VoucherRouterOption>,
    val plans: List<VoucherPlanOption>,
    val pinLengths: List<Int>,
    val pinFormats: List<VoucherPinFormatOption>,
    val statuses: List<String>,
)

data class VoucherRouterOption(val id: String, val name: String, val nasIdentifier: String, val status: String)
data class VoucherPlanOption(
    val id: String,
    val name: String,
    val priceKobo: Long,
    val isActive: Boolean,
    val validity: String,
)
data class VoucherPinFormatOption(val value: String, val label: String)
data class VoucherPlanRef(val id: String, val name: String)
data class VoucherRouterRef(val id: String, val name: String)
data class VoucherCounts(val available: Int, val active: Int, val expired: Int, val revoked: Int)

data class VoucherBatchSummary(
    val id: String,
    val reference: String,
    val quantity: Int,
    val pinLength: Int,
    val retailPriceKobo: Long,
    val retailValueKobo: Long,
    val status: String,
    val plan: VoucherPlanRef,
    val router: VoucherRouterRef?,
    val counts: VoucherCounts,
    val canEdit: Boolean,
    val canDelete: Boolean,
    val createdAt: String,
    val printedAt: String?,
)

data class VoucherBatchDetail(val summary: VoucherBatchSummary, val vouchers: List<VoucherItem>)
data class VoucherItem(
    val id: String,
    val serialNumber: String,
    val codeLastFour: String,
    val status: String,
    val soldAt: String?,
    val activatedAt: String?,
    val expiresAt: String?,
)

data class VoucherFilters(
    val search: String = "",
    val status: String? = null,
    val planId: String? = null,
    val routerId: String? = null,
)

data class VoucherCreateInput(
    val routerId: String,
    val planId: String,
    val quantity: Int,
    val retailPriceKobo: Long?,
    val pinFormat: String,
    val pinLength: Int,
    val dashedPin: Boolean,
)

data class VoucherEditInput(val routerId: String, val planId: String, val retailPriceKobo: Long)

data class VoucherShare(val reference: String, val codes: List<VoucherShareCode>) {
    fun asPlainText(): String = buildString {
        appendLine("HotFii vouchers - $reference")
        codes.forEach { code -> appendLine("${code.serialNumber}: ${code.code}") }
    }.trim()
}

data class VoucherShareCode(val id: String, val serialNumber: String, val code: String)
