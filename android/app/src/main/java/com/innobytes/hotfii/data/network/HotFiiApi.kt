package com.innobytes.hotfii.data.network

import com.innobytes.hotfii.data.network.dto.ApiEnvelope
import com.innobytes.hotfii.data.network.dto.DashboardDto
import com.innobytes.hotfii.data.network.dto.LoginRequestDto
import com.innobytes.hotfii.data.network.dto.LoginSessionDto
import com.innobytes.hotfii.data.network.dto.SessionDto
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Path
import retrofit2.http.POST
import retrofit2.http.Query
import retrofit2.Response

interface HotFiiApi {
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequestDto): Response<ApiEnvelope<LoginSessionDto>>

    @GET("session")
    suspend fun session(): ApiEnvelope<SessionDto>

    @DELETE("auth/logout")
    suspend fun logout()

    @GET("organizations/{organization}/dashboard")
    suspend fun dashboard(
        @Path("organization") organizationId: String,
        @Query("router") routerId: String? = null,
    ): ApiEnvelope<DashboardDto>
}
