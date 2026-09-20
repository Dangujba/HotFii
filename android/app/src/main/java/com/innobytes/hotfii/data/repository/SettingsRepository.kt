package com.innobytes.hotfii.data.repository

import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.OrganizationSettingsRequestDto
import com.innobytes.hotfii.data.network.dto.PaymentProfileRequestDto
import com.innobytes.hotfii.data.network.dto.TeamMemberRequestDto
import com.innobytes.hotfii.data.network.dto.TeamRoleRequestDto
import com.innobytes.hotfii.domain.DeviceSession
import com.innobytes.hotfii.domain.OrganizationSettings
import com.innobytes.hotfii.domain.OrganizationSettingsCatalog
import com.innobytes.hotfii.domain.OrganizationSettingsInput
import com.innobytes.hotfii.domain.PaymentProfile
import com.innobytes.hotfii.domain.PaymentProfileInput
import com.innobytes.hotfii.domain.TeamCatalog
import java.io.IOException
import retrofit2.HttpException

interface SettingsRepository {
    suspend fun settings(organizationId: String, auditPage: Int = 1): OrganizationSettingsCatalog
    suspend fun updateOrganization(organizationId: String, input: OrganizationSettingsInput): OrganizationSettings
    suspend fun submitPaymentProfile(organizationId: String, input: PaymentProfileInput): PaymentProfile
    suspend fun team(organizationId: String, page: Int = 1): TeamCatalog
    suspend fun addTeamMember(organizationId: String, email: String, role: String)
    suspend fun updateTeamRole(organizationId: String, memberId: String, role: String)
    suspend fun deviceSessions(): List<DeviceSession>
    suspend fun revokeDeviceSession(sessionId: String)
}

class DefaultSettingsRepository(
    private val api: HotFiiApi,
    private val gson: Gson,
) : SettingsRepository {
    override suspend fun settings(organizationId: String, auditPage: Int) = request {
        api.organizationSettings(organizationId, auditPage).data.toDomain()
    }

    override suspend fun updateOrganization(organizationId: String, input: OrganizationSettingsInput) = request {
        api.updateOrganizationSettings(organizationId, OrganizationSettingsRequestDto.from(input)).data.organization.toDomain()
    }

    override suspend fun submitPaymentProfile(organizationId: String, input: PaymentProfileInput) = request {
        api.submitPaymentProfile(organizationId, PaymentProfileRequestDto.from(input)).data.paymentProfile.toDomain()
    }

    override suspend fun team(organizationId: String, page: Int) = request {
        api.team(organizationId, page).data.toDomain()
    }

    override suspend fun addTeamMember(organizationId: String, email: String, role: String) = request {
        api.addTeamMember(organizationId, TeamMemberRequestDto(email, role))
        Unit
    }

    override suspend fun updateTeamRole(organizationId: String, memberId: String, role: String) = request {
        api.updateTeamMember(organizationId, memberId, TeamRoleRequestDto(role))
        Unit
    }

    override suspend fun deviceSessions() = request {
        api.deviceSessions().data.toDomain()
    }

    override suspend fun revokeDeviceSession(sessionId: String) = request {
        api.revokeDeviceSession(sessionId)
    }

    private suspend fun <T> request(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        val body = runCatching {
            gson.fromJson(error.response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        throw SettingsException(
            body?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: body?.message
                ?: if (error.code() == 401) "Your session has expired. Sign in again."
                else "The settings request could not be completed.",
            error,
        )
    } catch (error: IOException) {
        throw SettingsException("HotFii could not be reached. Check your connection and try again.", error)
    }
}

class SettingsException(message: String, cause: Throwable? = null) : Exception(message, cause)
