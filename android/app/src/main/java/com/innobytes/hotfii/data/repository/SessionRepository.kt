package com.innobytes.hotfii.data.repository

import android.os.Build
import com.google.gson.Gson
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.network.dto.ApiErrorDto
import com.innobytes.hotfii.data.network.dto.LoginRequestDto
import com.innobytes.hotfii.data.security.SecureSessionStore
import com.innobytes.hotfii.domain.UserSession
import java.io.IOException
import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLException
import retrofit2.HttpException
import retrofit2.Response

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
    override suspend fun signIn(email: String, password: String): UserSession = try {
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
        )

        if (!response.isSuccessful) {
            throw response.toSessionException(
                fallback = "Sign in failed. Check your details and try again.",
            )
        }

        val session = response.body()?.data
            ?: throw SessionException("HotFii returned an empty sign-in response. Please try again.")

        sessionStore.saveToken(session.token)
        session.toDomain()
    } catch (error: SessionException) {
        throw error
    } catch (error: IOException) {
        throw error.toSessionException()
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
                throw error.toSessionException("HotFii could not restore your session.")
            }
        } catch (error: IOException) {
            throw error.toSessionException()
        }
    }

    override suspend fun signOut() {
        runCatching { api.logout() }
        sessionStore.clearToken()
    }

    override fun selectedOrganizationId(): String? = sessionStore.selectedOrganizationId()

    override fun selectOrganization(id: String) = sessionStore.selectOrganization(id)

    private fun Response<*>.toSessionException(fallback: String): SessionException {
        val body = runCatching { errorBody()?.string() }.getOrNull()
        return SessionException(sessionApiErrorMessage(gson, code(), body, fallback))
    }

    private fun HttpException.toSessionException(fallback: String): SessionException {
        val body = runCatching { response()?.errorBody()?.string() }.getOrNull()
        return SessionException(sessionApiErrorMessage(gson, code(), body, fallback))
    }
}

internal fun sessionApiErrorMessage(
    gson: Gson,
    statusCode: Int,
    responseBody: String?,
    fallback: String,
): String {
    val apiError = responseBody
        ?.takeIf(String::isNotBlank)
        ?.let { body -> runCatching { gson.fromJson(body, ApiErrorDto::class.java) }.getOrNull() }
    val validationMessage = apiError?.errors
        ?.values
        ?.asSequence()
        ?.flatMap(List<String>::asSequence)
        ?.firstOrNull(String::isNotBlank)

    return validationMessage
        ?: apiError?.message?.takeIf(String::isNotBlank)
        ?: when (statusCode) {
            401 -> "Your session has expired. Sign in again."
            403 -> "You do not have permission to complete this request."
            404 -> "The requested HotFii service was not found."
            429 -> "Too many attempts. Wait a moment and try again."
            in 500..599 -> "HotFii is having a server problem. Please try again shortly."
            else -> fallback
        }
}

private fun IOException.toSessionException(): SessionException = SessionException(
    when (this) {
        is UnknownHostException -> "No internet connection. Check your mobile data or Wi-Fi and try again."
        is SocketTimeoutException -> "HotFii took too long to respond. Check your connection and try again."
        is SSLException -> "A secure connection to HotFii could not be established."
        is ConnectException -> "HotFii could not be reached. Check your connection and try again."
        else -> "The connection was interrupted. Check your connection and try again."
    },
)

class SessionException(message: String) : Exception(message)
