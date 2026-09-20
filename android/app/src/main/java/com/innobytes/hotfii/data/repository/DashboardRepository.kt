package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.domain.DashboardSnapshot
import java.io.IOException
import retrofit2.HttpException

interface DashboardRepository {
    suspend fun load(organizationId: String, routerId: String? = null): DashboardSnapshot
}

class DefaultDashboardRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
) : DashboardRepository {
    override suspend fun load(organizationId: String, routerId: String?): DashboardSnapshot = try {
        api.dashboard(organizationId, routerId).data.toDomain()
    } catch (error: HttpException) {
        val apiError = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        val validationMessage = apiError?.errors?.values?.firstOrNull()?.firstOrNull()
        throw DashboardException(
            validationMessage
                ?: apiError?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again." else "Dashboard data could not be loaded.",
        )
    } catch (_: IOException) {
        throw DashboardException("HotFii could not be reached. Check your connection and try again.")
    }
}

class DashboardException(message: String) : Exception(message)
