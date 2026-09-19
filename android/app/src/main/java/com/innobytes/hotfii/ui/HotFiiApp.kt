package com.innobytes.hotfii.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.innobytes.hotfii.ui.auth.LoginScreen

@Composable
fun HotFiiApp(
    viewModel: MainViewModel,
    onCloseApp: () -> Unit,
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val session = state.session

    when {
        state.isRestoring -> LoadingScreen()
        session == null -> LoginScreen(
            isSubmitting = state.isSubmitting,
            error = state.error,
            onSignIn = viewModel::signIn,
            onInputChanged = viewModel::clearLoginError,
            onCloseApp = onCloseApp,
        )
        else -> AuthenticatedShell(state = state, viewModel = viewModel)
    }
}
