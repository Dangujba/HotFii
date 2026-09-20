package com.innobytes.hotfii.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.innobytes.hotfii.ui.auth.LoginScreen
import com.innobytes.hotfii.data.preferences.AppThemeMode
import androidx.compose.runtime.LaunchedEffect

@Composable
fun HotFiiApp(
    viewModel: MainViewModel,
    onCloseApp: () -> Unit,
    themeMode: AppThemeMode,
    onThemeModeChanged: (AppThemeMode) -> Unit,
    biometricEnabled: Boolean,
    biometricUnlocked: Boolean,
    biometricMessage: String?,
    onRequestBiometricUnlock: () -> Unit,
    onBiometricChanged: (Boolean) -> Unit,
    onBiometricMessageDismissed: () -> Unit,
    openFinanceRequest: Int = 0,
    invoicePaymentStatus: String? = null,
    openNotificationsRequest: Int = 0,
    onRequestNotificationPermission: () -> Unit,
    pushNotificationsAvailable: Boolean,
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val session = state.session

    when {
        state.isRestoring -> LoadingScreen()
        session == null -> LoginScreen(
            isSubmitting = state.isSubmitting,
            error = state.error,
            requiresTwoFactor = state.requiresTwoFactor,
            onSignIn = viewModel::signIn,
            onVerifyTwoFactor = viewModel::verifyTwoFactor,
            onCancelTwoFactor = viewModel::cancelTwoFactor,
            onInputChanged = viewModel::clearLoginError,
            onCloseApp = onCloseApp,
        )
        biometricEnabled && !biometricUnlocked -> {
            LaunchedEffect(Unit) { onRequestBiometricUnlock() }
            BiometricLockScreen(
                message = biometricMessage,
                onUnlock = onRequestBiometricUnlock,
                onSignOut = viewModel::signOut,
                onMessageDismissed = onBiometricMessageDismissed,
            )
        }
        else -> AuthenticatedShell(
            state = state,
            viewModel = viewModel,
            themeMode = themeMode,
            onThemeModeChanged = onThemeModeChanged,
            biometricEnabled = biometricEnabled,
            biometricMessage = biometricMessage,
            onBiometricChanged = onBiometricChanged,
            onBiometricMessageDismissed = onBiometricMessageDismissed,
            openFinanceRequest = openFinanceRequest,
            invoicePaymentStatus = invoicePaymentStatus,
            openNotificationsRequest = openNotificationsRequest,
            onRequestNotificationPermission = onRequestNotificationPermission,
            pushNotificationsAvailable = pushNotificationsAvailable,
        )
    }
}
