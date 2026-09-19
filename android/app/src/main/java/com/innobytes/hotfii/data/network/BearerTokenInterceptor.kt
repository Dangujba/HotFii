package com.innobytes.hotfii.data.network

import com.innobytes.hotfii.data.security.SecureSessionStore
import okhttp3.Interceptor
import okhttp3.Response

class BearerTokenInterceptor(
    private val sessionStore: SecureSessionStore,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = sessionStore.readToken()
        val original = chain.request()
        val request = original.newBuilder()
            .apply {
                if (original.header("Accept") == null) {
                    header("Accept", "application/json")
                }
            }
            .apply {
                if (token != null) {
                    header("Authorization", "Bearer $token")
                }
            }
            .build()

        return chain.proceed(request)
    }
}
