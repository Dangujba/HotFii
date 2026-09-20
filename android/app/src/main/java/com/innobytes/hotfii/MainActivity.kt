package com.innobytes.hotfii

import android.os.Bundle
import android.os.Process
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.innobytes.hotfii.data.preferences.AppPreferences
import com.innobytes.hotfii.data.preferences.AppThemeMode
import com.innobytes.hotfii.ui.HotFiiApp
import com.innobytes.hotfii.ui.MainViewModel
import com.innobytes.hotfii.ui.theme.HotFiiTheme

class MainActivity : FragmentActivity() {
    private lateinit var appPreferences: AppPreferences
    private var themeMode by mutableStateOf(AppThemeMode.System)
    private var biometricEnabled by mutableStateOf(false)
    private var biometricUnlocked by mutableStateOf(true)
    private var biometricMessage by mutableStateOf<String?>(null)
    private val viewModel: MainViewModel by viewModels {
        val application = application as HotFiiApplication
        MainViewModel.factory(
            application.container.sessionRepository,
            application.container.dashboardRepository,
            application.container.voucherRepository,
        )
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        appPreferences = (application as HotFiiApplication).container.appPreferences
        themeMode = appPreferences.themeMode()
        biometricEnabled = appPreferences.biometricEnabled()
        biometricUnlocked = !biometricEnabled
        enableEdgeToEdge()
        setContent {
            HotFiiTheme(themeMode = themeMode) {
                HotFiiApp(
                    viewModel = viewModel,
                    onCloseApp = ::closeApp,
                    themeMode = themeMode,
                    onThemeModeChanged = ::applyThemeMode,
                    biometricEnabled = biometricEnabled,
                    biometricUnlocked = biometricUnlocked,
                    biometricMessage = biometricMessage,
                    onRequestBiometricUnlock = ::requestBiometricUnlock,
                    onBiometricChanged = ::changeBiometricSetting,
                    onBiometricMessageDismissed = { biometricMessage = null },
                )
            }
        }
    }

    private fun applyThemeMode(mode: AppThemeMode) {
        appPreferences.setThemeMode(mode)
        themeMode = mode
    }

    private fun changeBiometricSetting(enabled: Boolean) {
        if (!enabled) {
            appPreferences.setBiometricEnabled(false)
            biometricEnabled = false
            biometricUnlocked = true
            biometricMessage = "Fingerprint login disabled on this device."
            return
        }

        authenticate(
            title = "Enable fingerprint login",
            subtitle = "Confirm your fingerprint to protect the HotFii session on this device.",
        ) {
            appPreferences.setBiometricEnabled(true)
            biometricEnabled = true
            biometricUnlocked = true
            biometricMessage = "Fingerprint login enabled on this device."
        }
    }

    private fun requestBiometricUnlock() {
        authenticate(
            title = "Unlock HotFii",
            subtitle = "Use your fingerprint or device biometric to continue.",
        ) {
            biometricUnlocked = true
            biometricMessage = null
        }
    }

    private fun authenticate(title: String, subtitle: String, onSuccess: () -> Unit) {
        val authenticators = BiometricManager.Authenticators.BIOMETRIC_STRONG
        val manager = BiometricManager.from(this)
        biometricMessage = when (manager.canAuthenticate(authenticators)) {
            BiometricManager.BIOMETRIC_SUCCESS -> null
            BiometricManager.BIOMETRIC_ERROR_NONE_ENROLLED ->
                "Add a fingerprint in Android settings before enabling fingerprint login."
            BiometricManager.BIOMETRIC_ERROR_NO_HARDWARE ->
                "This device does not have supported biometric hardware."
            else -> "Fingerprint authentication is not available right now."
        }
        if (biometricMessage != null) return

        val prompt = BiometricPrompt(
            this,
            ContextCompat.getMainExecutor(this),
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    onSuccess()
                }

                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    if (errorCode != BiometricPrompt.ERROR_NEGATIVE_BUTTON &&
                        errorCode != BiometricPrompt.ERROR_USER_CANCELED
                    ) {
                        biometricMessage = errString.toString()
                    }
                }
            },
        )
        prompt.authenticate(
            BiometricPrompt.PromptInfo.Builder()
                .setTitle(title)
                .setSubtitle(subtitle)
                .setAllowedAuthenticators(authenticators)
                .setNegativeButtonText("Cancel")
                .build(),
        )
    }

    private fun closeApp() {
        finishAndRemoveTask()
        Process.killProcess(Process.myPid())
    }
}
