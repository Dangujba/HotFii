package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.*

data class FinanceCatalogDto(val current: FinanceCurrentDto, val plan: FinancePlanDto, val ledger: List<FeeLedgerRecordDto>, val ledgerPagination: NetworkPaginationDto, val invoices: List<FinanceInvoiceDto>, val invoicePagination: NetworkPaginationDto, val options: FinanceOptionsDto, val permissions: FinancePermissionsDto) {
    fun toDomain() = FinanceCatalog(current.toDomain(), plan.toDomain(), ledger.map(FeeLedgerRecordDto::toDomain), ledgerPagination.toDomain(), invoices.map(FinanceInvoiceDto::toDomain), invoicePagination.toDomain(), options.toDomain(), permissions.toDomain())
}
data class FinanceCurrentDto(val sales: Long, val fees: Long, val accrued: Long, val collected: Long, val estimatedMonthEndFee: Long, val estimatedInvoiceBalance: Long) {
    fun toDomain() = FinanceCurrent(sales, fees, accrued, collected, estimatedMonthEndFee, estimatedInvoiceBalance)
}
data class FinancePlanDto(val code: String, val subscriptionStatus: String?) { fun toDomain() = FinancePlan(code, subscriptionStatus) }
data class FeeLedgerRecordDto(val id: String, val billingPeriod: String, val routerId: String?, val routerName: String?, val sourceType: String, val sourceId: String?, val billableSalesKobo: Long, val feeAmountKobo: Long, val status: String, val createdAt: String?) {
    fun toDomain() = FeeLedgerRecord(id, billingPeriod, routerId, routerName, sourceType, sourceId, billableSalesKobo, feeAmountKobo, status, createdAt)
}
data class FinanceInvoiceDto(val id: String, val number: String, val billingPeriod: String, val subtotalKobo: Long, val totalKobo: Long, val status: String, val isOverdue: Boolean, val dueAt: String?, val paidAt: String?, val paymentMethod: String?, val createdAt: String?) {
    fun toDomain() = FinanceInvoice(id, number, billingPeriod, subtotalKobo, totalKobo, status, isOverdue, dueAt, paidAt, paymentMethod, createdAt)
}
data class FinanceRouterOptionDto(val id: String, val name: String) { fun toDomain() = FinanceRouterOption(id, name) }
data class FinanceOptionsDto(val ledgerStatuses: List<String>, val invoiceStatuses: List<String>, val routers: List<FinanceRouterOptionDto>) {
    fun toDomain() = FinanceOptions(ledgerStatuses, invoiceStatuses, routers.map(FinanceRouterOptionDto::toDomain))
}
data class FinancePermissionsDto(val canPayInvoices: Boolean) { fun toDomain() = FinancePermissions(canPayInvoices) }
data class FinanceInvoiceDetailDto(val invoice: FinanceInvoiceDto, val permissions: FinanceInvoiceDetailPermissionsDto) {
    fun toDomain() = FinanceInvoiceDetail(invoice.toDomain(), permissions.canPay)
}
data class FinanceInvoiceDetailPermissionsDto(val canPay: Boolean)
data class InvoiceCheckoutDto(val authorizationUrl: String, val reference: String) { fun toDomain() = InvoiceCheckout(authorizationUrl, reference) }

data class ReportDataDto(val from: String, val to: String, val routerId: String?, val summary: ReportSummaryDto, val usage: ReportUsageDto, val salesTrend: ReportSalesTrendDto, val channels: List<ReportChannelDto>, val topPlans: List<ReportPlanDto>, val usageTrend: ReportUsageTrendDto, val options: ReportOptionsDto) {
    fun toDomain() = ReportData(from, to, routerId, summary.toDomain(), usage.toDomain(), salesTrend.toDomain(), channels.map(ReportChannelDto::toDomain), topPlans.map(ReportPlanDto::toDomain), usageTrend.toDomain(), options.routers.map(FinanceRouterOptionDto::toDomain))
}
data class ReportSummaryDto(val sales: Int, val grossKobo: Long) { fun toDomain() = ReportSummary(sales, grossKobo) }
data class ReportUsageDto(val sessions: Int, val bytes: Long) { fun toDomain() = ReportUsage(sessions, bytes) }
data class ReportSalesTrendDto(val dates: List<String>, val labels: List<String>, val series: Map<String, List<Long>>) {
    fun toDomain() = ReportSalesTrend(dates, labels, series["online"].orEmpty(), series["voucher"].orEmpty(), series["cash"].orEmpty())
}
data class ReportChannelDto(val key: String, val label: String, val sales: Int, val totalKobo: Long) { fun toDomain() = ReportChannel(key, label, sales, totalKobo) }
data class ReportPlanDto(val name: String, val sales: Int, val totalKobo: Long) { fun toDomain() = ReportPlan(name, sales, totalKobo) }
data class ReportUsageTrendDto(val dates: List<String>, val labels: List<String>, val sessions: List<Int>, val bytes: List<Long>) { fun toDomain() = ReportUsageTrend(dates, labels, sessions, bytes) }
data class ReportOptionsDto(val routers: List<FinanceRouterOptionDto>)
