package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.domain.*
import java.io.File
import java.io.IOException
import retrofit2.HttpException

interface FinanceRepository {
    suspend fun finance(organizationId: String, filters: FinanceFilters, ledgerPage: Int, invoicePage: Int): FinanceCatalog
    suspend fun invoice(organizationId: String, invoiceId: String): FinanceInvoiceDetail
    suspend fun startInvoicePayment(organizationId: String, invoiceId: String): InvoiceCheckout
    suspend fun report(organizationId: String, filters: ReportFilters): ReportData
    suspend fun exportReport(organizationId: String, filters: ReportFilters, format: String): ReportExport
}

class DefaultFinanceRepository(private val api: HotFiiApi, private val gson: Gson, private val cacheDir: File) : FinanceRepository {
    override suspend fun finance(organizationId: String, filters: FinanceFilters, ledgerPage: Int, invoicePage: Int) = request {
        api.finance(organizationId, filters.ledgerStatus, filters.period, filters.invoiceStatus, filters.routerId, ledgerPage, invoicePage).data.toDomain()
    }
    override suspend fun invoice(organizationId: String, invoiceId: String) = request { api.financeInvoice(organizationId, invoiceId).data.toDomain() }
    override suspend fun startInvoicePayment(organizationId: String, invoiceId: String) = request { api.startInvoicePayment(organizationId, invoiceId).data.toDomain() }
    override suspend fun report(organizationId: String, filters: ReportFilters) = request { api.report(organizationId, filters.from, filters.to, filters.routerId).data.toDomain() }
    override suspend fun exportReport(organizationId: String, filters: ReportFilters, format: String): ReportExport = request {
        require(format == "pdf" || format == "csv")
        val body = if (format == "pdf") api.exportReportPdf(organizationId, filters.from, filters.to, filters.routerId) else api.exportReportCsv(organizationId, filters.from, filters.to, filters.routerId)
        val directory = File(cacheDir, "shared_reports").apply { mkdirs() }
        val destination = File(directory, "hotfii-report-${filters.from ?: "latest"}-${filters.to ?: "today"}.$format")
        val partial = File(directory, destination.name + ".part")
        body.byteStream().use { input -> partial.outputStream().use(input::copyTo) }
        check(!destination.exists() || destination.delete()) { "The previous report file could not be replaced." }
        check(partial.renameTo(destination)) { "The report file could not be prepared." }
        ReportExport(destination.absolutePath, if (format == "pdf") "application/pdf" else "text/csv")
    }
    private suspend fun <T> request(block: suspend () -> T): T = try { block() } catch (error: HttpException) {
        val body = runCatching { gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java) }.getOrNull()
        throw NetworkException(body?.errors?.values?.firstOrNull()?.firstOrNull() ?: body?.message ?: if (error.code() == 401) "Your session has expired. Sign in again." else "The request could not be completed.", error)
    } catch (error: IOException) { throw NetworkException("HotFii could not be reached. Check your connection and try again.", error) }
}
