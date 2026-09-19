package com.innobytes.hotfii.data

import android.content.Context
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.FieldNamingPolicy
import com.innobytes.hotfii.BuildConfig
import com.innobytes.hotfii.data.network.BearerTokenInterceptor
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.repository.DefaultSessionRepository
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.DefaultDashboardRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.data.repository.DefaultVoucherRepository
import com.innobytes.hotfii.data.repository.VoucherRepository
import com.innobytes.hotfii.data.security.SecureSessionStore
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

class AppContainer(context: Context) {
    private val gson: Gson = GsonBuilder()
        .setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
        .create()

    private val sessionStore = SecureSessionStore(context)

    private val client = OkHttpClient.Builder()
        .addInterceptor(BearerTokenInterceptor(sessionStore))
        .addInterceptor(
            HttpLoggingInterceptor().apply {
                level = if (BuildConfig.DEBUG) {
                    HttpLoggingInterceptor.Level.BASIC
                } else {
                    HttpLoggingInterceptor.Level.NONE
                }
            },
        )
        .build()

    private val api = Retrofit.Builder()
        .baseUrl(BuildConfig.API_BASE_URL)
        .client(client)
        .addConverterFactory(GsonConverterFactory.create(gson))
        .build()
        .create(HotFiiApi::class.java)

    val sessionRepository: SessionRepository = DefaultSessionRepository(
        api = api,
        sessionStore = sessionStore,
        gson = gson,
    )

    val dashboardRepository: DashboardRepository = DefaultDashboardRepository(
        api = api,
        gson = gson,
    )

    val voucherRepository: VoucherRepository = DefaultVoucherRepository(api, gson, context.cacheDir)
}
