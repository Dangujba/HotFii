package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.VoucherCreateRequestDto
import com.innobytes.hotfii.data.network.dto.VoucherEditRequestDto
import com.innobytes.hotfii.domain.*
import java.io.IOException
import retrofit2.HttpException

interface VoucherRepository {
    suspend fun catalog(organizationId: String, filters: VoucherFilters, page: Int): VoucherCatalog
    suspend fun detail(organizationId: String, batchId: String): VoucherBatchDetail
    suspend fun create(organizationId: String, input: VoucherCreateInput): VoucherBatchDetail
    suspend fun update(organizationId: String, batchId: String, input: VoucherEditInput): VoucherBatchDetail
    suspend fun delete(organizationId: String, batchId: String)
    suspend fun share(organizationId: String, batchId: String): VoucherShare
}

class DefaultVoucherRepository(private val api: HotFiiApi, private val gson: Gson) : VoucherRepository {
    override suspend fun catalog(organizationId: String, filters: VoucherFilters, page: Int) = request {
        api.voucherBatches(
            organizationId, filters.search.trim().ifEmpty { null }, filters.status,
            filters.planId, filters.routerId, page,
        ).data.toDomain()
    }
    override suspend fun detail(organizationId: String, batchId: String) =
        request { api.voucherBatch(organizationId, batchId).data.toDetail() }
    override suspend fun create(organizationId: String, input: VoucherCreateInput) =
        request { api.createVoucherBatch(organizationId, VoucherCreateRequestDto.from(input)).data.toDetail() }
    override suspend fun update(organizationId: String, batchId: String, input: VoucherEditInput) =
        request { api.updateVoucherBatch(organizationId, batchId, VoucherEditRequestDto.from(input)).data.toDetail() }
    override suspend fun delete(organizationId: String, batchId: String) =
        request { api.deleteVoucherBatch(organizationId, batchId) }
    override suspend fun share(organizationId: String, batchId: String) =
        request { api.shareVoucherBatch(organizationId, batchId).data.toDomain() }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        val body = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        throw VoucherException(
            body?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: body?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again."
                else "The voucher request could not be completed.",
        )
    } catch (_: IOException) {
        throw VoucherException("HotFii could not be reached. Check your connection and try again.")
    }
}

class VoucherException(message: String) : Exception(message)
