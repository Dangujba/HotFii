package com.innobytes.hotfii

import android.app.Application
import com.innobytes.hotfii.data.AppContainer

class HotFiiApplication : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
        container.pushNotificationManager.initialize()
    }
}
