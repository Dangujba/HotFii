package com.innobytes.hotfii.data.network

import com.innobytes.hotfii.data.network.dto.ApiEnvelope
import com.innobytes.hotfii.data.network.dto.DashboardDto
import com.innobytes.hotfii.data.network.dto.LoginRequestDto
import com.innobytes.hotfii.data.network.dto.LoginResponseDto
import com.innobytes.hotfii.data.network.dto.LoginSessionDto
import com.innobytes.hotfii.data.network.dto.SessionDto
import com.innobytes.hotfii.data.network.dto.PasswordRequestDto
import com.innobytes.hotfii.data.network.dto.AccessPlanDto
import com.innobytes.hotfii.data.network.dto.PlanCatalogDto
import com.innobytes.hotfii.data.network.dto.PlanRequestDto
import com.innobytes.hotfii.data.network.dto.TwoFactorCodeRequestDto
import com.innobytes.hotfii.data.network.dto.TwoFactorConfirmationDto
import com.innobytes.hotfii.data.network.dto.TwoFactorSetupDto
import com.innobytes.hotfii.data.network.dto.VoucherBatchDto
import com.innobytes.hotfii.data.network.dto.VoucherCatalogDto
import com.innobytes.hotfii.data.network.dto.VoucherCreateRequestDto
import com.innobytes.hotfii.data.network.dto.VoucherEditRequestDto
import com.innobytes.hotfii.data.network.dto.VoucherShareDto
import com.innobytes.hotfii.data.network.dto.CashSaleRequestDto
import com.innobytes.hotfii.data.network.dto.CashSaleResultDto
import com.innobytes.hotfii.data.network.dto.CustomerCatalogDto
import com.innobytes.hotfii.data.network.dto.CustomerDetailDto
import com.innobytes.hotfii.data.network.dto.SalesCatalogDto
import com.innobytes.hotfii.data.network.dto.ApiMessageEnvelope
import com.innobytes.hotfii.data.network.dto.HotspotSessionCatalogDto
import com.innobytes.hotfii.data.network.dto.HotspotSessionRecordDto
import com.innobytes.hotfii.data.network.dto.MessageDto
import com.innobytes.hotfii.data.network.dto.NetworkCatalogDto
import com.innobytes.hotfii.data.network.dto.NetworkRouterDetailDto
import com.innobytes.hotfii.data.network.dto.FinanceCatalogDto
import com.innobytes.hotfii.data.network.dto.FinanceInvoiceDetailDto
import com.innobytes.hotfii.data.network.dto.InvoiceCheckoutDto
import com.innobytes.hotfii.data.network.dto.ReportDataDto
import com.innobytes.hotfii.data.network.dto.NotificationCatalogDto
import com.innobytes.hotfii.data.network.dto.NotificationPreferencesRequestDto
import com.innobytes.hotfii.data.network.dto.NotificationPreferencesResponseDto
import com.innobytes.hotfii.data.network.dto.NotificationReadRequestDto
import com.innobytes.hotfii.data.network.dto.MobileDeviceRequestDto
import com.innobytes.hotfii.data.network.dto.MobileDeviceDeleteRequestDto
import com.innobytes.hotfii.data.network.dto.DeviceSessionCatalogDto
import com.innobytes.hotfii.data.network.dto.OrganizationSettingsCatalogDto
import com.innobytes.hotfii.data.network.dto.OrganizationSettingsRequestDto
import com.innobytes.hotfii.data.network.dto.OrganizationSettingsUpdateDto
import com.innobytes.hotfii.data.network.dto.PaymentProfileRequestDto
import com.innobytes.hotfii.data.network.dto.PaymentProfileUpdateDto
import com.innobytes.hotfii.data.network.dto.TeamCatalogDto
import com.innobytes.hotfii.data.network.dto.TeamMemberRequestDto
import com.innobytes.hotfii.data.network.dto.TeamMemberResponseDto
import com.innobytes.hotfii.data.network.dto.TeamRoleRequestDto
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
import retrofit2.http.PUT
import retrofit2.http.Streaming

interface HotFiiApi {
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequestDto): Response<LoginResponseDto>

    @GET("session")
    suspend fun session(): ApiEnvelope<SessionDto>

    @DELETE("auth/logout")
    suspend fun logout()

    @GET("auth/sessions")
    suspend fun deviceSessions(): ApiEnvelope<DeviceSessionCatalogDto>

    @DELETE("auth/sessions/{session}")
    suspend fun revokeDeviceSession(@Path("session") sessionId: String)

    @PUT("device")
    suspend fun registerDevice(@Body request: MobileDeviceRequestDto): Response<Unit>

    @HTTP(method = "DELETE", path = "device", hasBody = true)
    suspend fun unregisterDevice(@Body request: MobileDeviceDeleteRequestDto)

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

    @GET("organizations/{organization}/notifications")
    suspend fun notifications(
        @Path("organization") organizationId: String,
        @Query("category") category: String? = null,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<NotificationCatalogDto>

    @PATCH("organizations/{organization}/notifications/preferences")
    suspend fun updateNotificationPreferences(
        @Path("organization") organizationId: String,
        @Body request: NotificationPreferencesRequestDto,
    ): ApiEnvelope<NotificationPreferencesResponseDto>

    @POST("organizations/{organization}/notifications/read")
    suspend fun readNotifications(
        @Path("organization") organizationId: String,
        @Body request: NotificationReadRequestDto,
    ): ApiEnvelope<MessageDto>

    @GET("organizations/{organization}/settings")
    suspend fun organizationSettings(
        @Path("organization") organizationId: String,
        @Query("audit_page") auditPage: Int = 1,
    ): ApiEnvelope<OrganizationSettingsCatalogDto>

    @PATCH("organizations/{organization}/settings")
    suspend fun updateOrganizationSettings(
        @Path("organization") organizationId: String,
        @Body request: OrganizationSettingsRequestDto,
    ): ApiEnvelope<OrganizationSettingsUpdateDto>

    @POST("organizations/{organization}/settings/payment-profile")
    suspend fun submitPaymentProfile(
        @Path("organization") organizationId: String,
        @Body request: PaymentProfileRequestDto,
    ): ApiEnvelope<PaymentProfileUpdateDto>

    @GET("organizations/{organization}/team")
    suspend fun team(
        @Path("organization") organizationId: String,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<TeamCatalogDto>

    @POST("organizations/{organization}/team")
    suspend fun addTeamMember(
        @Path("organization") organizationId: String,
        @Body request: TeamMemberRequestDto,
    ): ApiEnvelope<TeamMemberResponseDto>

    @PATCH("organizations/{organization}/team/{member}")
    suspend fun updateTeamMember(
        @Path("organization") organizationId: String,
        @Path("member") memberId: String,
        @Body request: TeamRoleRequestDto,
    ): ApiEnvelope<TeamMemberResponseDto>

    @GET("organizations/{organization}/plans")
    suspend fun plans(
        @Path("organization") organizationId: String,
        @Query("search") search: String? = null,
        @Query("type") type: String? = null,
        @Query("state") state: String? = null,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<PlanCatalogDto>

    @POST("organizations/{organization}/plans")
    suspend fun createPlan(
        @Path("organization") organizationId: String,
        @Body request: PlanRequestDto,
    ): ApiEnvelope<AccessPlanDto>

    @PATCH("organizations/{organization}/plans/{plan}")
    suspend fun updatePlan(
        @Path("organization") organizationId: String,
        @Path("plan") planId: String,
        @Body request: PlanRequestDto,
    ): ApiEnvelope<AccessPlanDto>

    @DELETE("organizations/{organization}/plans/{plan}")
    suspend fun deletePlan(
        @Path("organization") organizationId: String,
        @Path("plan") planId: String,
    )

    @GET("organizations/{organization}/sales")
    suspend fun sales(
        @Path("organization") organizationId: String,
        @Query("search") search: String? = null,
        @Query("status") status: String? = null,
        @Query("channel") channel: String? = null,
        @Query("from") from: String? = null,
        @Query("to") to: String? = null,
        @Query("router") routerId: String? = null,
        @Query("transactions_page") transactionsPage: Int = 1,
        @Query("vouchers_page") vouchersPage: Int = 1,
    ): ApiEnvelope<SalesCatalogDto>

    @POST("organizations/{organization}/sales/cash")
    suspend fun recordCashSale(
        @Path("organization") organizationId: String,
        @Body request: CashSaleRequestDto,
    ): ApiEnvelope<CashSaleResultDto>

    @GET("organizations/{organization}/customers")
    suspend fun customers(
        @Path("organization") organizationId: String,
        @Query("search") search: String? = null,
        @Query("type") type: String? = null,
        @Query("status") status: String? = null,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<CustomerCatalogDto>

    @GET("organizations/{organization}/customers/{customer}")
    suspend fun customer(
        @Path("organization") organizationId: String,
        @Path("customer") customerId: String,
    ): ApiEnvelope<CustomerDetailDto>

    @GET("organizations/{organization}/routers")
    suspend fun routers(
        @Path("organization") organizationId: String,
        @Query("search") search: String? = null,
        @Query("status") status: String? = null,
        @Query("vendor") vendor: String? = null,
        @Query("location") locationId: String? = null,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<NetworkCatalogDto>

    @GET("organizations/{organization}/routers/{router}")
    suspend fun router(
        @Path("organization") organizationId: String,
        @Path("router") routerId: String,
    ): ApiEnvelope<NetworkRouterDetailDto>

    @POST("organizations/{organization}/routers/{router}/test")
    suspend fun runRouterTests(
        @Path("organization") organizationId: String,
        @Path("router") routerId: String,
    ): MessageDto

    @GET("organizations/{organization}/sessions")
    suspend fun hotspotSessions(
        @Path("organization") organizationId: String,
        @Query("view") view: String,
        @Query("search") search: String? = null,
        @Query("status") status: String? = null,
        @Query("router") routerId: String? = null,
        @Query("from") from: String? = null,
        @Query("to") to: String? = null,
        @Query("page") page: Int = 1,
    ): ApiEnvelope<HotspotSessionCatalogDto>

    @POST("organizations/{organization}/sessions/{session}/disconnect")
    suspend fun disconnectSession(
        @Path("organization") organizationId: String,
        @Path("session") sessionId: String,
    ): ApiMessageEnvelope<HotspotSessionRecordDto>

    @GET("organizations/{organization}/finance")
    suspend fun finance(@Path("organization") organizationId: String, @Query("ledger_status") ledgerStatus: String? = null, @Query("period") period: String? = null, @Query("invoice_status") invoiceStatus: String? = null, @Query("router") routerId: String? = null, @Query("ledger_page") ledgerPage: Int = 1, @Query("invoice_page") invoicePage: Int = 1): ApiEnvelope<FinanceCatalogDto>

    @GET("organizations/{organization}/finance/invoices/{invoice}")
    suspend fun financeInvoice(@Path("organization") organizationId: String, @Path("invoice") invoiceId: String): ApiEnvelope<FinanceInvoiceDetailDto>

    @POST("organizations/{organization}/finance/invoices/{invoice}/pay")
    suspend fun startInvoicePayment(@Path("organization") organizationId: String, @Path("invoice") invoiceId: String): ApiEnvelope<InvoiceCheckoutDto>

    @GET("organizations/{organization}/reports")
    suspend fun report(@Path("organization") organizationId: String, @Query("from") from: String? = null, @Query("to") to: String? = null, @Query("router") routerId: String? = null): ApiEnvelope<ReportDataDto>

    @Streaming
    @GET("organizations/{organization}/reports/export/pdf")
    suspend fun exportReportPdf(@Path("organization") organizationId: String, @Query("from") from: String? = null, @Query("to") to: String? = null, @Query("router") routerId: String? = null): ResponseBody

    @Streaming
    @GET("organizations/{organization}/reports/export/csv")
    suspend fun exportReportCsv(@Path("organization") organizationId: String, @Query("from") from: String? = null, @Query("to") to: String? = null, @Query("router") routerId: String? = null): ResponseBody

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
