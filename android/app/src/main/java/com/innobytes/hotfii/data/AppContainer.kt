package com.innobytes.hotfii.data

import android.content.Context
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.FieldNamingPolicy
import com.innobytes.hotfii.BuildConfig
import com.innobytes.hotfii.data.network.BearerTokenInterceptor
import com.innobytes.hotfii.data.preferences.AppPreferences
import com.innobytes.hotfii.data.network.HotFiiApi
import com.innobytes.hotfii.data.repository.DefaultSessionRepository
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.DefaultDashboardRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.data.repository.DefaultSalesRepository
import com.innobytes.hotfii.data.repository.SalesRepository
import com.innobytes.hotfii.data.repository.DefaultNetworkRepository
import com.innobytes.hotfii.data.repository.NetworkRepository
import com.innobytes.hotfii.data.repository.DefaultFinanceRepository
import com.innobytes.hotfii.data.repository.FinanceRepository
import com.innobytes.hotfii.data.repository.DefaultVoucherRepository
import com.innobytes.hotfii.data.repository.DefaultPlanRepository
import com.innobytes.hotfii.data.repository.PlanRepository
import com.innobytes.hotfii.data.repository.VoucherRepository
import com.innobytes.hotfii.data.repository.DefaultNotificationRepository
import com.innobytes.hotfii.data.repository.NotificationRepository
import com.innobytes.hotfii.data.repository.DefaultSettingsRepository
import com.innobytes.hotfii.data.repository.SettingsRepository
import com.innobytes.hotfii.data.security.SecureSessionStore
import com.innobytes.hotfii.notifications.PushNotificationManager
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

class AppContainer(context: Context) {
    val appPreferences = AppPreferences(context)
    private val gson: Gson = GsonBuilder()
        .setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
        .create()

    private val sessionStore = SecureSessionStore(context)

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(2, TimeUnit.MINUTES)
        .writeTimeout(30, TimeUnit.SECONDS)
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

    val planRepository: PlanRepository = DefaultPlanRepository(api, gson)

    val salesRepository: SalesRepository = DefaultSalesRepository(api, gson)

    val networkRepository: NetworkRepository = DefaultNetworkRepository(api, gson)
    val financeRepository: FinanceRepository = DefaultFinanceRepository(api, gson, context.cacheDir)
    val notificationRepository: NotificationRepository = DefaultNotificationRepository(api, gson, sessionStore)
    val settingsRepository: SettingsRepository = DefaultSettingsRepository(api, gson)
    val pushNotificationManager = PushNotificationManager(context, notificationRepository)
}
