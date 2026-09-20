package com.innobytes.hotfii.ui

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.data.repository.VoucherRepository
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.UserSession
import com.innobytes.hotfii.domain.VoucherBatchDetail
import com.innobytes.hotfii.domain.VoucherCatalog
import com.innobytes.hotfii.domain.VoucherCreateInput
import com.innobytes.hotfii.domain.VoucherEditInput
import com.innobytes.hotfii.domain.VoucherFilters
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class VoucherUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val catalog: VoucherCatalog? = null,
    val detail: VoucherBatchDetail? = null,
    val filters: VoucherFilters = VoucherFilters(),
    val error: String? = null,
    val notice: String? = null,
    val pendingSharePdfPaths: List<String> = emptyList(),
)

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
    val vouchers: VoucherUiState = VoucherUiState(),
) {
    val selectedOrganization: OrganizationSummary?
        get() = session?.organizations?.firstOrNull { it.id == selectedOrganizationId }
            ?: session?.organizations?.firstOrNull()
}

class MainViewModel(
    private val sessionRepository: SessionRepository,
    private val dashboardRepository: DashboardRepository,
    private val voucherRepository: VoucherRepository,
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

    fun clearLoginError() {
        _state.update { it.copy(error = null) }
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
                vouchers = VoucherUiState(),
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

    fun loadVouchers(
        filters: VoucherFilters = _state.value.vouchers.filters,
        page: Int = 1,
    ) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(
                    isLoading = true,
                    filters = filters,
                    error = null,
                    notice = null,
                ))
            }
            runCatching { voucherRepository.catalog(organizationId, filters, page) }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(vouchers = current.vouchers.copy(
                            isLoading = false,
                            catalog = catalog,
                            error = null,
                        ))
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(vouchers = current.vouchers.copy(
                            isLoading = false,
                            error = error.message ?: "Vouchers could not be loaded.",
                        ))
                    }
                }
        }
    }

    fun openVoucherBatch(batchId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(isLoading = true, error = null, notice = null))
            }
            runCatching { voucherRepository.detail(organizationId, batchId) }
                .onSuccess { detail ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(vouchers = current.vouchers.copy(
                            isLoading = false,
                            detail = detail,
                        ))
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        current.copy(vouchers = current.vouchers.copy(
                            isLoading = false,
                            error = error.message ?: "Voucher batch could not be opened.",
                        ))
                    }
                }
        }
    }

    fun closeVoucherBatch() {
        _state.update { it.copy(vouchers = it.vouchers.copy(detail = null, error = null, notice = null)) }
    }

    fun createVoucherBatch(input: VoucherCreateInput) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { voucherRepository.create(organizationId, input) }
                .onSuccess { detail ->
                    val catalog = runCatching {
                        voucherRepository.catalog(organizationId, _state.value.vouchers.filters, 1)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(vouchers = current.vouchers.copy(
                            isActionRunning = false,
                            detail = detail,
                            catalog = catalog ?: current.vouchers.catalog,
                            notice = "Voucher batch generated.",
                        ))
                    }
                }
                .onFailure { voucherActionFailed(it, "Voucher batch could not be generated.") }
        }
    }

    fun updateVoucherBatch(batchId: String, input: VoucherEditInput) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { voucherRepository.update(organizationId, batchId, input) }
                .onSuccess { detail ->
                    val catalog = runCatching {
                        voucherRepository.catalog(organizationId, _state.value.vouchers.filters, 1)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(vouchers = current.vouchers.copy(
                            isActionRunning = false,
                            detail = detail,
                            catalog = catalog ?: current.vouchers.catalog,
                            notice = "Voucher batch updated.",
                        ))
                    }
                }
                .onFailure { voucherActionFailed(it, "Voucher batch could not be updated.") }
        }
    }

    fun deleteVoucherBatch(batchId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { voucherRepository.delete(organizationId, batchId) }
                .onSuccess {
                    val catalog = runCatching {
                        voucherRepository.catalog(organizationId, _state.value.vouchers.filters, 1)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(vouchers = current.vouchers.copy(
                            isActionRunning = false,
                            detail = null,
                            catalog = catalog ?: current.vouchers.catalog,
                            notice = "Unused voucher batch deleted.",
                        ))
                    }
                }
                .onFailure { voucherActionFailed(it, "Voucher batch could not be deleted.") }
        }
    }

    fun shareVoucherPdf(batchId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        val batch = _state.value.vouchers.detail?.summary?.takeIf { it.id == batchId } ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching {
                voucherRepository.sharePdf(
                    organizationId,
                    batchId,
                    batch.reference,
                    batch.quantity,
                )
            }
                .onSuccess { paths ->
                    val detail = runCatching { voucherRepository.detail(organizationId, batchId) }.getOrNull()
                    val catalog = runCatching {
                        voucherRepository.catalog(organizationId, _state.value.vouchers.filters, 1)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(vouchers = current.vouchers.copy(
                            isActionRunning = false,
                            detail = detail ?: current.vouchers.detail,
                            catalog = catalog ?: current.vouchers.catalog,
                            pendingSharePdfPaths = paths,
                            notice = "Voucher PDF is ready to share.",
                        ))
                    }
                }
                .onFailure { voucherActionFailed(it, "Voucher PDF could not be prepared.") }
        }
    }

    fun consumeVoucherPdfShare() {
        _state.update { it.copy(vouchers = it.vouchers.copy(pendingSharePdfPaths = emptyList())) }
    }

    fun clearVoucherFeedback() {
        _state.update { it.copy(vouchers = it.vouchers.copy(error = null, notice = null)) }
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

    private fun voucherActionFailed(error: Throwable, fallback: String) {
        Log.e("HotFiiVoucher", fallback, error)
        _state.update {
            it.copy(vouchers = it.vouchers.copy(
                isActionRunning = false,
                error = error.message ?: fallback,
            ))
        }
    }

    companion object {
        fun factory(
            sessionRepository: SessionRepository,
            dashboardRepository: DashboardRepository,
            voucherRepository: VoucherRepository,
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                MainViewModel(sessionRepository, dashboardRepository, voucherRepository) as T
        }
    }
}
