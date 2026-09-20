package com.innobytes.hotfii.data.preferences

import android.content.Context

enum class AppThemeMode {
    System,
    Light,
    Dark,
}

class AppPreferences(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    fun themeMode(): AppThemeMode = runCatching {
        AppThemeMode.valueOf(preferences.getString(KEY_THEME, null) ?: AppThemeMode.System.name)
    }.getOrDefault(AppThemeMode.System)

    fun setThemeMode(mode: AppThemeMode) {
        preferences.edit().putString(KEY_THEME, mode.name).apply()
    }

    fun biometricEnabled(): Boolean = preferences.getBoolean(KEY_BIOMETRIC, false)

    fun setBiometricEnabled(enabled: Boolean) {
        preferences.edit().putBoolean(KEY_BIOMETRIC, enabled).apply()
    }

    private companion object {
        const val PREFERENCES_NAME = "hotfii_app_preferences"
        const val KEY_THEME = "theme_mode"
        const val KEY_BIOMETRIC = "biometric_enabled"
    }
}
