package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.VoucherCreateRequestDto
import com.innobytes.hotfii.data.network.dto.VoucherEditRequestDto
import com.innobytes.hotfii.domain.*
import java.io.File
import java.io.IOException
import java.net.SocketTimeoutException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import retrofit2.HttpException

interface VoucherRepository {
    suspend fun catalog(organizationId: String, filters: VoucherFilters, page: Int): VoucherCatalog
    suspend fun detail(organizationId: String, batchId: String): VoucherBatchDetail
    suspend fun create(organizationId: String, input: VoucherCreateInput): VoucherBatchDetail
    suspend fun update(organizationId: String, batchId: String, input: VoucherEditInput): VoucherBatchDetail
    suspend fun delete(organizationId: String, batchId: String)
    suspend fun thermal(organizationId: String, batchId: String): VoucherShare
    suspend fun sharePdf(
        organizationId: String,
        batchId: String,
        reference: String,
        quantity: Int,
    ): List<String>
}

class DefaultVoucherRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
    private val cacheDir: File,
) : VoucherRepository {
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
    override suspend fun thermal(organizationId: String, batchId: String) =
        request { api.shareVoucherBatch(organizationId, batchId).data.toDomain() }
    override suspend fun sharePdf(
        organizationId: String,
        batchId: String,
        reference: String,
        quantity: Int,
    ): List<String> = withContext(Dispatchers.IO) {
        val partCount = maxOf(1, (quantity + 99) / 100)
        val safeReference = reference.replace(Regex("[^A-Za-z0-9._-]"), "_")
        val directory = File(cacheDir, "shared_vouchers").apply { mkdirs() }
        directory.listFiles()
            ?.filter { it.name.startsWith(safeReference) }
            ?.forEach(File::delete)
        val files = mutableListOf<File>()
        val partialFiles = mutableListOf<File>()

        try {
            for (part in 1..partCount) {
                val filename = if (partCount == 1) {
                    "$safeReference.pdf"
                } else {
                    "%s-part-%02d-of-%02d.pdf".format(safeReference, part, partCount)
                }
                val file = File(directory, filename)
                val partialFile = File(directory, "$filename.part").also {
                    it.delete()
                    partialFiles += it
                }
                request {
                    api.voucherBatchPdf(organizationId, batchId, part).use { body ->
                        body.byteStream().use { input ->
                            partialFile.outputStream().use { output -> input.copyTo(output) }
                        }
                    }
                }
                if (!partialFile.renameTo(file)) {
                    partialFile.copyTo(file, overwrite = true)
                    partialFile.delete()
                }
                files += file
            }
        } catch (error: Throwable) {
            (files + partialFiles).forEach(File::delete)
            throw error
        }

        files.map(File::getAbsolutePath)
    }

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
            error,
        )
    } catch (error: SocketTimeoutException) {
        throw VoucherException("The voucher PDF took too long to download. Please try again.", error)
    } catch (error: IOException) {
        throw VoucherException("HotFii could not be reached. Check your connection and try again.", error)
    }
}

class VoucherException(message: String, cause: Throwable? = null) : Exception(message, cause)
