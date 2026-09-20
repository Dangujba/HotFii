package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.CashSaleRequestDto
import com.innobytes.hotfii.domain.CashSaleInput
import com.innobytes.hotfii.domain.CashSaleResult
import com.innobytes.hotfii.domain.CustomerCatalog
import com.innobytes.hotfii.domain.CustomerDetail
import com.innobytes.hotfii.domain.CustomerFilters
import com.innobytes.hotfii.domain.SalesCatalog
import com.innobytes.hotfii.domain.SalesFilters
import java.io.IOException
import retrofit2.HttpException

interface SalesRepository {
    suspend fun catalog(
        organizationId: String,
        filters: SalesFilters,
        transactionsPage: Int,
        vouchersPage: Int,
    ): SalesCatalog

    suspend fun recordCash(organizationId: String, input: CashSaleInput): CashSaleResult
    suspend fun customers(organizationId: String, filters: CustomerFilters, page: Int): CustomerCatalog
    suspend fun customer(organizationId: String, customerId: String): CustomerDetail
}

class DefaultSalesRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
) : SalesRepository {
    override suspend fun catalog(
        organizationId: String,
        filters: SalesFilters,
        transactionsPage: Int,
        vouchersPage: Int,
    ) = request {
        api.sales(
            organizationId = organizationId,
            search = filters.search.trim().ifEmpty { null },
            status = filters.status,
            channel = filters.channel,
            from = filters.from,
            to = filters.to,
            routerId = filters.routerId,
            transactionsPage = transactionsPage,
            vouchersPage = vouchersPage,
        ).data.toDomain()
    }

    override suspend fun recordCash(organizationId: String, input: CashSaleInput) = request {
        api.recordCashSale(organizationId, CashSaleRequestDto.from(input)).data.toDomain()
    }

    override suspend fun customers(organizationId: String, filters: CustomerFilters, page: Int) = request {
        api.customers(
            organizationId,
            filters.search.trim().ifEmpty { null },
            filters.type,
            filters.status,
            page,
        ).data.toDomain()
    }

    override suspend fun customer(organizationId: String, customerId: String) = request {
        api.customer(organizationId, customerId).data.toDomain()
    }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        val body = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        throw SalesException(
            body?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: body?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again."
                else "The sales request could not be completed.",
            error,
        )
    } catch (error: IOException) {
        throw SalesException("HotFii could not be reached. Check your connection and try again.", error)
    }
}

class SalesException(message: String, cause: Throwable? = null) : Exception(message, cause)
