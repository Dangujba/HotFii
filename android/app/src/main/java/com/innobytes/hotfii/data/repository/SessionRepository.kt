package com.innobytes.hotfii.data.repository

import android.os.Build
import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.LoginRequestDto
import com.innobytes.hotfii.data.security.SecureSessionStore
import com.innobytes.hotfii.domain.UserSession
import java.io.IOException
import retrofit2.HttpException

interface SessionRepository {
    suspend fun signIn(email: String, password: String): UserSession
    suspend fun restore(): UserSession?
    suspend fun signOut()
    fun selectedOrganizationId(): String?
    fun selectOrganization(id: String)
}

class DefaultSessionRepository(
    private val api: HotFiiApi,
    private val sessionStore: SecureSessionStore,
    private val gson: Gson,
) : SessionRepository {
    override suspend fun signIn(email: String, password: String): UserSession = apiCall {
        val response = api.login(
            LoginRequestDto(
                email = email.trim(),
                password = password,
                deviceName = listOf(Build.MANUFACTURER, Build.MODEL)
                    .filter(String::isNotBlank)
                    .joinToString(" ")
                    .ifBlank { "Android" },
                deviceId = sessionStore.deviceId(),
            ),
        ).data

        sessionStore.saveToken(response.token)
        response.toDomain()
    }

    override suspend fun restore(): UserSession? {
        if (sessionStore.readToken() == null) return null

        return try {
            api.session().data.toDomain()
        } catch (error: HttpException) {
            if (error.code() == 401) {
                sessionStore.clearToken()
                null
            } else {
                throw error.toSessionException()
            }
        } catch (error: IOException) {
            throw SessionException("HotFii could not be reached. Check your connection and try again.")
        }
    }

    override suspend fun signOut() {
        runCatching { api.logout() }
        sessionStore.clearToken()
    }

    override fun selectedOrganizationId(): String? = sessionStore.selectedOrganizationId()

    override fun selectOrganization(id: String) = sessionStore.selectOrganization(id)

    private suspend fun <T> apiCall(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        throw error.toSessionException()
    } catch (error: IOException) {
        throw SessionException("HotFii could not be reached. Check your connection and try again.")
    }

    private fun HttpException.toSessionException(): SessionException {
        val apiError = runCatching {
            gson.fromJson(response()?.errorBody()?.charStream(), ApiErrorDto::class.java)
        }.getOrNull()
        val validationMessage = apiError?.errors?.values?.firstOrNull()?.firstOrNull()

        return SessionException(
            validationMessage
                ?: apiError?.message
                ?: if (code() == 401) "Your session has expired. Sign in again." else "HotFii could not complete the request.",
        )
    }
}

class SessionException(message: String) : Exception(message)
