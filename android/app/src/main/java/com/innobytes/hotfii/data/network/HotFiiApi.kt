package com.innobytes.hotfii.data.network

import com.innobytes.hotfii.data.network.dto.ApiEnvelope
import com.innobytes.hotfii.data.network.dto.DashboardDto
import com.innobytes.hotfii.data.network.dto.LoginRequestDto
import com.innobytes.hotfii.data.network.dto.LoginResponseDto
import com.innobytes.hotfii.data.network.dto.LoginSessionDto
import com.innobytes.hotfii.data.network.dto.SessionDto
import com.innobytes.hotfii.data.network.dto.PasswordRequestDto
import com.innobytes.hotfii.data.network.dto.TwoFactorCodeRequestDto
import com.innobytes.hotfii.data.network.dto.TwoFactorConfirmationDto
import com.innobytes.hotfii.data.network.dto.TwoFactorSetupDto
import com.innobytes.hotfii.data.network.dto.VoucherBatchDto
import com.innobytes.hotfii.data.network.dto.VoucherCatalogDto
import com.innobytes.hotfii.data.network.dto.VoucherCreateRequestDto
import com.innobytes.hotfii.data.network.dto.VoucherEditRequestDto
import com.innobytes.hotfii.data.network.dto.VoucherShareDto
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.Path
import retrofit2.http.PATCH
import retrofit2.http.POST
import retrofit2.http.Query
import retrofit2.http.Streaming

interface HotFiiApi {
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequestDto): Response<LoginResponseDto>

    @GET("session")
    suspend fun session(): ApiEnvelope<SessionDto>

    @DELETE("auth/logout")
    suspend fun logout()

    @POST("auth/two-factor/setup")
    suspend fun setupTwoFactor(): ApiEnvelope<TwoFactorSetupDto>

    @POST("auth/two-factor/confirm")
    suspend fun confirmTwoFactor(@Body request: TwoFactorCodeRequestDto): ApiEnvelope<TwoFactorConfirmationDto>

    @HTTP(method = "DELETE", path = "auth/two-factor", hasBody = true)
    suspend fun disableTwoFactor(@Body request: PasswordRequestDto)

    @GET("organizations/{organization}/dashboard")
    suspend fun dashboard(
        @Path("organization") organizationId: String,
        @Query("router") routerId: String? = null,
    ): ApiEnvelope<DashboardDto>

    @GET("organizations/{organization}/voucher-batches")
    suspend fun voucherBatches(
        @Path("organization") organizationId: String,
        @Query("search") search: String? = null,
        @Query("status") status: String? = null,
        @Query("plan") planId: String? = null,
        @Query("router") routerId: String? = null,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<VoucherCatalogDto>

    @GET("organizations/{organization}/voucher-batches/{batch}")
    suspend fun voucherBatch(
        @Path("organization") organizationId: String,
        @Path("batch") batchId: String,
    ): ApiEnvelope<VoucherBatchDto>

    @POST("organizations/{organization}/voucher-batches")
    suspend fun createVoucherBatch(
        @Path("organization") organizationId: String,
        @Body request: VoucherCreateRequestDto,
    ): ApiEnvelope<VoucherBatchDto>

    @PATCH("organizations/{organization}/voucher-batches/{batch}")
    suspend fun updateVoucherBatch(
        @Path("organization") organizationId: String,
        @Path("batch") batchId: String,
        @Body request: VoucherEditRequestDto,
    ): ApiEnvelope<VoucherBatchDto>

    @DELETE("organizations/{organization}/voucher-batches/{batch}")
    suspend fun deleteVoucherBatch(
        @Path("organization") organizationId: String,
        @Path("batch") batchId: String,
    )

    @Streaming
    @GET("organizations/{organization}/voucher-batches/{batch}/pdf")
    suspend fun voucherBatchPdf(
        @Path("organization") organizationId: String,
        @Path("batch") batchId: String,
        @Query("part") part: Int,
    ): ResponseBody

    @POST("organizations/{organization}/voucher-batches/{batch}/share")
    suspend fun shareVoucherBatch(
        @Path("organization") organizationId: String,
        @Path("batch") batchId: String,
    ): ApiEnvelope<VoucherShareDto>
}
