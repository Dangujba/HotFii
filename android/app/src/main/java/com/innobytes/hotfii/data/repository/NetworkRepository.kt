package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.domain.*
import java.io.IOException
import retrofit2.HttpException

interface NetworkRepository {
    suspend fun routers(organizationId: String, filters: NetworkFilters, page: Int): NetworkCatalog
    suspend fun router(organizationId: String, routerId: String): NetworkRouterDetail
    suspend fun runTests(organizationId: String, routerId: String): String
    suspend fun sessions(organizationId: String, filters: HotspotSessionFilters, page: Int): HotspotSessionCatalog
    suspend fun disconnect(organizationId: String, sessionId: String): DisconnectResult
}

class DefaultNetworkRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
) : NetworkRepository {
    override suspend fun routers(organizationId: String, filters: NetworkFilters, page: Int) = request {
        api.routers(
            organizationId,
            filters.search.trim().ifEmpty { null },
            filters.status,
            filters.vendor,
            filters.locationId,
            page,
        ).data.toDomain()
    }

    override suspend fun router(organizationId: String, routerId: String) = request {
        api.router(organizationId, routerId).data.toDomain()
    }

    override suspend fun runTests(organizationId: String, routerId: String) = request {
        api.runRouterTests(organizationId, routerId).message
    }

    override suspend fun sessions(organizationId: String, filters: HotspotSessionFilters, page: Int) = request {
        api.hotspotSessions(
            organizationId,
            filters.view,
            filters.search.trim().ifEmpty { null },
            filters.status,
            filters.routerId,
            filters.from,
            filters.to,
            page,
        ).data.toDomain()
    }

    override suspend fun disconnect(organizationId: String, sessionId: String) = request {
        val response = api.disconnectSession(organizationId, sessionId)
        DisconnectResult(response.data.toDomain(), response.message)
    }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        val body = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        throw NetworkException(
            body?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: body?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again."
                else "The network request could not be completed.",
            error,
        )
    } catch (error: IOException) {
        throw NetworkException("HotFii could not be reached. Check your connection and try again.", error)
    }
}

class NetworkException(message: String, cause: Throwable? = null) : Exception(message, cause)
