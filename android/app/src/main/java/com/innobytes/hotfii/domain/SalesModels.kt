package com.innobytes.hotfii.domain

data class SalesCatalog(
    val summary: SalesSummary,
    val transactions: List<SalesTransaction>,
    val transactionsPagination: SalesPagination,
    val voucherActivations: List<VoucherActivation>,
    val vouchersPagination: SalesPagination,
    val options: SalesOptions,
    val permissions: SalesPermissions,
)

data class SalesSummary(
    val onlineSalesKobo: Long,
    val printedVoucherSalesKobo: Long,
    val directCashSalesKobo: Long,
    val totalSalesKobo: Long,
    val voucherActivationsCount: Int,
)

data class SalesTransaction(
    val id: String,
    val reference: String,
    val saleType: String,
    val status: String,
    val grossAmountKobo: Long,
    val platformFeeKobo: Long,
    val routerName: String?,
    val customerName: String?,
    val customerContact: String?,
    val planName: String?,
    val paidAt: String?,
    val createdAt: String?,
)

data class VoucherActivation(
    val id: String,
    val serialNumber: String,
    val codeLastFour: String,
    val amountKobo: Long,
    val isComplimentary: Boolean,
    val planName: String?,
    val batchReference: String?,
    val routerName: String?,
    val customerName: String?,
    val activatedAt: String?,
    val expiresAt: String?,
)

data class SalesPagination(
    val currentPage: Int,
    val lastPage: Int,
    val perPage: Int,
    val total: Int,
)

data class SalesOptions(
    val routers: List<SalesRouterOption>,
    val cashRouters: List<SalesRouterOption>,
    val cashPlans: List<CashPlanOption>,
    val statuses: List<String>,
    val channels: List<SalesChannelOption>,
)

data class SalesRouterOption(
    val id: String,
    val name: String,
    val location: String?,
    val status: String,
)

data class CashPlanOption(val id: String, val name: String, val priceKobo: Long)
data class SalesChannelOption(val value: String, val label: String)
data class SalesPermissions(val canRecordCash: Boolean, val cashUnavailableReason: String?)

data class SalesFilters(
    val search: String = "",
    val status: String? = null,
    val channel: String? = null,
    val from: String? = null,
    val to: String? = null,
    val routerId: String? = null,
)

data class CashSaleInput(
    val accessPlanId: String,
    val networkDeviceId: String,
    val customerName: String?,
    val phone: String?,
)

data class CashSaleResult(
    val transaction: SalesTransaction,
    val credential: IssuedCredential,
)

data class IssuedCredential(val username: String?, val password: String?)

data class CustomerCatalog(
    val customers: List<CustomerSummary>,
    val pagination: SalesPagination,
    val options: CustomerOptions,
)

data class CustomerSummary(
    val id: String,
    val name: String?,
    val phone: String?,
    val type: String,
    val status: String,
    val currentPlan: String?,
    val credentialStatus: String?,
    val sessionsCount: Int,
    val transactionsCount: Int,
    val createdAt: String?,
)

data class CustomerOptions(val types: List<String>, val statuses: List<String>)
data class CustomerFilters(val search: String = "", val type: String? = null, val status: String? = null)

data class CustomerDetail(
    val customer: CustomerProfile,
    val recentSessions: List<CustomerSession>,
    val recentTransactions: List<CustomerTransaction>,
)

data class CustomerProfile(
    val id: String,
    val name: String?,
    val phone: String?,
    val email: String?,
    val type: String,
    val status: String,
    val currentPlan: String?,
    val credentialStatus: String?,
    val sessionsCount: Int,
    val transactionsCount: Int,
    val credentialsCount: Int,
    val vouchersCount: Int,
    val expiresAt: String?,
    val lastAuthenticatedAt: String?,
    val createdAt: String?,
)

data class CustomerSession(
    val id: String,
    val routerName: String?,
    val planName: String?,
    val status: String,
    val startedAt: String?,
    val stoppedAt: String?,
    val totalBytes: Long,
)

data class CustomerTransaction(
    val id: String,
    val reference: String,
    val saleType: String,
    val status: String,
    val grossAmountKobo: Long,
    val planName: String?,
    val routerName: String?,
    val createdAt: String?,
)
