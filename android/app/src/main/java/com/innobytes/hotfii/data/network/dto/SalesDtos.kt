package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.CashPlanOption
import com.innobytes.hotfii.domain.CashSaleInput
import com.innobytes.hotfii.domain.CashSaleResult
import com.innobytes.hotfii.domain.CustomerCatalog
import com.innobytes.hotfii.domain.CustomerDetail
import com.innobytes.hotfii.domain.CustomerFilters
import com.innobytes.hotfii.domain.CustomerOptions
import com.innobytes.hotfii.domain.CustomerProfile
import com.innobytes.hotfii.domain.CustomerSession
import com.innobytes.hotfii.domain.CustomerSummary
import com.innobytes.hotfii.domain.CustomerTransaction
import com.innobytes.hotfii.domain.IssuedCredential
import com.innobytes.hotfii.domain.SalesCatalog
import com.innobytes.hotfii.domain.SalesChannelOption
import com.innobytes.hotfii.domain.SalesOptions
import com.innobytes.hotfii.domain.SalesPagination
import com.innobytes.hotfii.domain.SalesPermissions
import com.innobytes.hotfii.domain.SalesRouterOption
import com.innobytes.hotfii.domain.SalesSummary
import com.innobytes.hotfii.domain.SalesTransaction
import com.innobytes.hotfii.domain.VoucherActivation

data class SalesCatalogDto(
    val summary: SalesSummaryDto,
    val transactions: List<SalesTransactionDto>,
    val transactionsPagination: SalesPaginationDto,
    val voucherActivations: List<VoucherActivationDto>,
    val vouchersPagination: SalesPaginationDto,
    val options: SalesOptionsDto,
    val permissions: SalesPermissionsDto,
) {
    fun toDomain() = SalesCatalog(
        summary.toDomain(),
        transactions.map(SalesTransactionDto::toDomain),
        transactionsPagination.toDomain(),
        voucherActivations.map(VoucherActivationDto::toDomain),
        vouchersPagination.toDomain(),
        options.toDomain(),
        permissions.toDomain(),
    )
}

data class SalesSummaryDto(
    val onlineSalesKobo: Long,
    val printedVoucherSalesKobo: Long,
    val directCashSalesKobo: Long,
    val totalSalesKobo: Long,
    val voucherActivationsCount: Int,
) {
    fun toDomain() = SalesSummary(
        onlineSalesKobo,
        printedVoucherSalesKobo,
        directCashSalesKobo,
        totalSalesKobo,
        voucherActivationsCount,
    )
}

data class SalesTransactionDto(
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
) {
    fun toDomain() = SalesTransaction(
        id, reference, saleType, status, grossAmountKobo, platformFeeKobo,
        routerName, customerName, customerContact, planName, paidAt, createdAt,
    )
}

data class VoucherActivationDto(
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
) {
    fun toDomain() = VoucherActivation(
        id, serialNumber, codeLastFour, amountKobo, isComplimentary, planName,
        batchReference, routerName, customerName, activatedAt, expiresAt,
    )
}

data class SalesPaginationDto(val currentPage: Int, val lastPage: Int, val perPage: Int, val total: Int) {
    fun toDomain() = SalesPagination(currentPage, lastPage, perPage, total)
}

data class SalesOptionsDto(
    val routers: List<SalesRouterOptionDto>,
    val cashRouters: List<SalesRouterOptionDto>,
    val cashPlans: List<CashPlanOptionDto>,
    val statuses: List<String>,
    val channels: List<SalesChannelOptionDto>,
) {
    fun toDomain() = SalesOptions(
        routers.map(SalesRouterOptionDto::toDomain),
        cashRouters.map(SalesRouterOptionDto::toDomain),
        cashPlans.map(CashPlanOptionDto::toDomain),
        statuses,
        channels.map(SalesChannelOptionDto::toDomain),
    )
}

data class SalesRouterOptionDto(val id: String, val name: String, val location: String?, val status: String) {
    fun toDomain() = SalesRouterOption(id, name, location, status)
}

data class CashPlanOptionDto(val id: String, val name: String, val priceKobo: Long) {
    fun toDomain() = CashPlanOption(id, name, priceKobo)
}

data class SalesChannelOptionDto(val value: String, val label: String) {
    fun toDomain() = SalesChannelOption(value, label)
}

data class SalesPermissionsDto(val canRecordCash: Boolean, val cashUnavailableReason: String?) {
    fun toDomain() = SalesPermissions(canRecordCash, cashUnavailableReason)
}

data class CashSaleRequestDto(
    val requestId: String,
    val accessPlanId: String,
    val networkDeviceId: String,
    val customerName: String?,
    val phone: String?,
) {
    companion object {
        fun from(input: CashSaleInput) = CashSaleRequestDto(
            input.requestId,
            input.accessPlanId,
            input.networkDeviceId,
            input.customerName,
            input.phone,
        )
    }
}

data class CashSaleResultDto(val transaction: SalesTransactionDto, val credential: IssuedCredentialDto) {
    fun toDomain() = CashSaleResult(transaction.toDomain(), credential.toDomain())
}

data class IssuedCredentialDto(val username: String?, val password: String?) {
    fun toDomain() = IssuedCredential(username, password)
}

data class CustomerCatalogDto(
    val customers: List<CustomerSummaryDto>,
    val pagination: SalesPaginationDto,
    val options: CustomerOptionsDto,
) {
    fun toDomain() = CustomerCatalog(
        customers.map(CustomerSummaryDto::toDomain),
        pagination.toDomain(),
        options.toDomain(),
    )
}

data class CustomerSummaryDto(
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
) {
    fun toDomain() = CustomerSummary(
        id, name, phone, type, status, currentPlan, credentialStatus,
        sessionsCount, transactionsCount, createdAt,
    )
}

data class CustomerOptionsDto(val types: List<String>, val statuses: List<String>) {
    fun toDomain() = CustomerOptions(types, statuses)
}

data class CustomerDetailDto(
    val customer: CustomerProfileDto,
    val recentSessions: List<CustomerSessionDto>,
    val recentTransactions: List<CustomerTransactionDto>,
) {
    fun toDomain() = CustomerDetail(
        customer.toDomain(),
        recentSessions.map(CustomerSessionDto::toDomain),
        recentTransactions.map(CustomerTransactionDto::toDomain),
    )
}

data class CustomerProfileDto(
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
) {
    fun toDomain() = CustomerProfile(
        id, name, phone, email, type, status, currentPlan, credentialStatus,
        sessionsCount, transactionsCount, credentialsCount, vouchersCount,
        expiresAt, lastAuthenticatedAt, createdAt,
    )
}

data class CustomerSessionDto(
    val id: String,
    val routerName: String?,
    val planName: String?,
    val status: String,
    val startedAt: String?,
    val stoppedAt: String?,
    val totalBytes: Long,
) {
    fun toDomain() = CustomerSession(id, routerName, planName, status, startedAt, stoppedAt, totalBytes)
}

data class CustomerTransactionDto(
    val id: String,
    val reference: String,
    val saleType: String,
    val status: String,
    val grossAmountKobo: Long,
    val planName: String?,
    val routerName: String?,
    val createdAt: String?,
) {
    fun toDomain() = CustomerTransaction(
        id, reference, saleType, status, grossAmountKobo, planName, routerName, createdAt,
    )
}
