package com.innobytes.hotfii.data.network

import com.innobytes.hotfii.data.security.SecureSessionStore
import okhttp3.Interceptor
import okhttp3.Response

class BearerTokenInterceptor(
    private val sessionStore: SecureSessionStore,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = sessionStore.readToken()
        val request = if (token == null) {
            chain.request()
        } else {
            chain.request().newBuilder()
                .header("Authorization", "Bearer $token")
                .build()
        }

        return chain.proceed(request)
    }
}
