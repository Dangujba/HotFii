package com.innobytes.hotfii.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.UserSession
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class MainUiState(
    val isRestoring: Boolean = true,
    val isSubmitting: Boolean = false,
    val isDashboardLoading: Boolean = false,
    val session: UserSession? = null,
    val selectedOrganizationId: String? = null,
    val selectedRouterId: String? = null,
    val dashboard: DashboardSnapshot? = null,
    val error: String? = null,
    val dashboardError: String? = null,
) {
    val selectedOrganization: OrganizationSummary?
        get() = session?.organizations?.firstOrNull { it.id == selectedOrganizationId }
            ?: session?.organizations?.firstOrNull()
}

class MainViewModel(
    private val sessionRepository: SessionRepository,
    private val dashboardRepository: DashboardRepository,
) : ViewModel() {
    private val _state = MutableStateFlow(MainUiState())
    val state: StateFlow<MainUiState> = _state.asStateFlow()

    init {
        restoreSession()
    }

    fun signIn(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _state.update { it.copy(error = "Enter your email address and password.") }
            return
        }

        viewModelScope.launch {
            _state.update { it.copy(isSubmitting = true, error = null) }
            runCatching { sessionRepository.signIn(email, password) }
                .onSuccess(::openSession)
                .onFailure { error ->
                    _state.update {
                        it.copy(
                            isRestoring = false,
                            isSubmitting = false,
                            error = error.message ?: "Sign in failed.",
                        )
                    }
                }
        }
    }

    fun refresh() {
        viewModelScope.launch {
            _state.update { it.copy(isSubmitting = true, error = null) }
            runCatching { sessionRepository.restore() }
                .onSuccess { session ->
                    if (session == null) {
                        _state.value = MainUiState(isRestoring = false)
                    } else {
                        openSession(session, _state.value.selectedOrganizationId)
                    }
                }
                .onFailure { error ->
                    _state.update {
                        it.copy(isSubmitting = false, error = error.message ?: "Refresh failed.")
                    }
                }
        }
    }

    fun refreshDashboard() {
        loadDashboard(_state.value.selectedOrganizationId, _state.value.selectedRouterId)
    }

    fun selectOrganization(id: String) {
        if (_state.value.session?.organizations?.none { it.id == id } != false) return
        sessionRepository.selectOrganization(id)
        _state.update {
            it.copy(
                selectedOrganizationId = id,
                selectedRouterId = null,
                dashboard = null,
                dashboardError = null,
            )
        }
        loadDashboard(id, null)
    }

    fun selectRouter(id: String?) {
        if (id != null && _state.value.dashboard?.routers?.none { it.id == id } != false) return
        _state.update { it.copy(selectedRouterId = id, dashboardError = null) }
        loadDashboard(_state.value.selectedOrganizationId, id)
    }

    fun signOut() {
        viewModelScope.launch {
            _state.update { it.copy(isSubmitting = true, error = null) }
            sessionRepository.signOut()
            _state.value = MainUiState(isRestoring = false)
        }
    }

    private fun restoreSession() {
        viewModelScope.launch {
            runCatching { sessionRepository.restore() }
                .onSuccess { session ->
                    if (session == null) {
                        _state.value = MainUiState(isRestoring = false)
                    } else {
                        openSession(session)
                    }
                }
                .onFailure { error ->
                    _state.value = MainUiState(
                        isRestoring = false,
                        error = error.message ?: "HotFii could not restore your session.",
                    )
                }
        }
    }

    private fun openSession(session: UserSession, preferredOrganizationId: String? = null) {
        val selectedId = preferredOrganizationId
            ?.takeIf { saved -> session.organizations.any { it.id == saved } }
            ?: sessionRepository.selectedOrganizationId()
                ?.takeIf { saved -> session.organizations.any { it.id == saved } }
            ?: session.defaultOrganizationId
            ?: session.organizations.firstOrNull()?.id

        selectedId?.let(sessionRepository::selectOrganization)
        _state.value = MainUiState(
            isRestoring = false,
            session = session,
            selectedOrganizationId = selectedId,
        )
        loadDashboard(selectedId, null)
    }

    private fun loadDashboard(organizationId: String?, routerId: String?) {
        if (organizationId == null) return

        viewModelScope.launch {
            _state.update { it.copy(isDashboardLoading = true, dashboardError = null) }
            runCatching { dashboardRepository.load(organizationId, routerId) }
                .onSuccess { dashboard ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId || current.selectedRouterId != routerId) {
                            current
                        } else {
                            current.copy(
                                isDashboardLoading = false,
                                dashboard = dashboard,
                                dashboardError = null,
                            )
                        }
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId || current.selectedRouterId != routerId) {
                            current
                        } else {
                            current.copy(
                                isDashboardLoading = false,
                                dashboardError = error.message ?: "Dashboard data could not be loaded.",
                            )
                        }
                    }
                }
        }
    }

    companion object {
        fun factory(
            sessionRepository: SessionRepository,
            dashboardRepository: DashboardRepository,
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                MainViewModel(sessionRepository, dashboardRepository) as T
        }
    }
}
