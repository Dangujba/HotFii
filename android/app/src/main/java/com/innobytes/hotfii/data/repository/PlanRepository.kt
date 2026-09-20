package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.PlanRequestDto
import com.innobytes.hotfii.domain.AccessPlanSummary
import com.innobytes.hotfii.domain.PlanCatalog
import com.innobytes.hotfii.domain.PlanFilters
import com.innobytes.hotfii.domain.PlanInput
import java.io.IOException
import retrofit2.HttpException

interface PlanRepository {
    suspend fun catalog(organizationId: String, filters: PlanFilters, page: Int): PlanCatalog
    suspend fun create(organizationId: String, input: PlanInput): AccessPlanSummary
    suspend fun update(organizationId: String, planId: String, input: PlanInput): AccessPlanSummary
    suspend fun delete(organizationId: String, planId: String)
}

class DefaultPlanRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
) : PlanRepository {
    override suspend fun catalog(organizationId: String, filters: PlanFilters, page: Int) = request {
        api.plans(
            organizationId,
            filters.search.trim().ifEmpty { null },
            filters.type,
            filters.state,
            page,
        ).data.toDomain()
    }

    override suspend fun create(organizationId: String, input: PlanInput) = request {
        api.createPlan(organizationId, PlanRequestDto.from(input)).data.toDomain()
    }

    override suspend fun update(organizationId: String, planId: String, input: PlanInput) = request {
        api.updatePlan(organizationId, planId, PlanRequestDto.from(input)).data.toDomain()
    }

    override suspend fun delete(organizationId: String, planId: String) = request {
        api.deletePlan(organizationId, planId)
    }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        val body = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        throw PlanException(
            body?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: body?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again."
                else "The plan request could not be completed.",
            error,
        )
    } catch (error: IOException) {
        throw PlanException("HotFii could not be reached. Check your connection and try again.", error)
    }
}

class PlanException(message: String, cause: Throwable? = null) : Exception(message, cause)
