package com.innobytes.hotfii.ui

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.PlanRepository
import com.innobytes.hotfii.data.repository.NetworkRepository
import com.innobytes.hotfii.data.repository.FinanceRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.data.repository.SalesRepository
import com.innobytes.hotfii.data.repository.TwoFactorRequiredException
import com.innobytes.hotfii.data.repository.VoucherRepository
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.AccessPlanSummary
import com.innobytes.hotfii.domain.PlanCatalog
import com.innobytes.hotfii.domain.PlanFilters
import com.innobytes.hotfii.domain.PlanInput
import com.innobytes.hotfii.domain.CashSaleInput
import com.innobytes.hotfii.domain.CustomerCatalog
import com.innobytes.hotfii.domain.CustomerDetail
import com.innobytes.hotfii.domain.CustomerFilters
import com.innobytes.hotfii.domain.IssuedCredential
import com.innobytes.hotfii.domain.HotspotSessionCatalog
import com.innobytes.hotfii.domain.HotspotSessionFilters
import com.innobytes.hotfii.domain.HotspotSessionRecord
import com.innobytes.hotfii.domain.NetworkCatalog
import com.innobytes.hotfii.domain.NetworkFilters
import com.innobytes.hotfii.domain.NetworkRouterDetail
import com.innobytes.hotfii.domain.SalesCatalog
import com.innobytes.hotfii.domain.SalesFilters
import com.innobytes.hotfii.domain.UserSession
import com.innobytes.hotfii.domain.TwoFactorSetup
import com.innobytes.hotfii.domain.VoucherBatchDetail
import com.innobytes.hotfii.domain.VoucherCatalog
import com.innobytes.hotfii.domain.VoucherCreateInput
import com.innobytes.hotfii.domain.VoucherEditInput
import com.innobytes.hotfii.domain.VoucherFilters
import com.innobytes.hotfii.domain.VoucherShare
import com.innobytes.hotfii.domain.FinanceCatalog
import com.innobytes.hotfii.domain.FinanceFilters
import com.innobytes.hotfii.domain.FinanceInvoiceDetail
import com.innobytes.hotfii.domain.ReportData
import com.innobytes.hotfii.domain.ReportExport
import com.innobytes.hotfii.domain.ReportFilters
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class PlanUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val catalog: PlanCatalog? = null,
    val filters: PlanFilters = PlanFilters(),
    val error: String? = null,
    val notice: String? = null,
)

data class VoucherUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val catalog: VoucherCatalog? = null,
    val detail: VoucherBatchDetail? = null,
    val filters: VoucherFilters = VoucherFilters(),
    val error: String? = null,
    val notice: String? = null,
    val pendingSharePdfPaths: List<String> = emptyList(),
    val pendingThermalPrint: VoucherShare? = null,
)

data class SalesUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val catalog: SalesCatalog? = null,
    val filters: SalesFilters = SalesFilters(),
    val customerCatalog: CustomerCatalog? = null,
    val customerFilters: CustomerFilters = CustomerFilters(),
    val customerDetail: CustomerDetail? = null,
    val error: String? = null,
    val notice: String? = null,
    val issuedCredential: IssuedCredential? = null,
    val failedCashSale: CashSaleInput? = null,
)

data class NetworkUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val catalog: NetworkCatalog? = null,
    val filters: NetworkFilters = NetworkFilters(),
    val routerDetail: NetworkRouterDetail? = null,
    val sessionCatalog: HotspotSessionCatalog? = null,
    val sessionFilters: HotspotSessionFilters = HotspotSessionFilters(),
    val selectedSession: HotspotSessionRecord? = null,
    val error: String? = null,
    val notice: String? = null,
)

data class FinanceUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val catalog: FinanceCatalog? = null,
    val filters: FinanceFilters = FinanceFilters(),
    val selectedInvoice: FinanceInvoiceDetail? = null,
    val checkoutUrl: String? = null,
    val error: String? = null,
    val notice: String? = null,
)

data class ReportUiState(
    val isLoading: Boolean = false,
    val isActionRunning: Boolean = false,
    val report: ReportData? = null,
    val filters: ReportFilters = ReportFilters(),
    val pendingExport: ReportExport? = null,
    val error: String? = null,
    val notice: String? = null,
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
    val requiresTwoFactor: Boolean = false,
    val securityActionRunning: Boolean = false,
    val twoFactorSetup: TwoFactorSetup? = null,
    val recoveryCodes: List<String> = emptyList(),
    val securityError: String? = null,
    val securityNotice: String? = null,
    val dashboardError: String? = null,
    val plans: PlanUiState = PlanUiState(),
    val vouchers: VoucherUiState = VoucherUiState(),
    val sales: SalesUiState = SalesUiState(),
    val network: NetworkUiState = NetworkUiState(),
    val finance: FinanceUiState = FinanceUiState(),
    val reports: ReportUiState = ReportUiState(),
) {
    val selectedOrganization: OrganizationSummary?
        get() = session?.organizations?.firstOrNull { it.id == selectedOrganizationId }
            ?: session?.organizations?.firstOrNull()
}

class MainViewModel(
    private val sessionRepository: SessionRepository,
    private val dashboardRepository: DashboardRepository,
    private val planRepository: PlanRepository,
    private val voucherRepository: VoucherRepository,
    private val salesRepository: SalesRepository,
    private val networkRepository: NetworkRepository,
    private val financeRepository: FinanceRepository,
) : ViewModel() {
    private var pendingLoginEmail: String? = null
    private var pendingLoginPassword: String? = null
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
            pendingLoginEmail = email
            pendingLoginPassword = password
            _state.update { it.copy(isSubmitting = true, error = null, requiresTwoFactor = false) }
            runCatching { sessionRepository.signIn(email, password) }
                .onSuccess {
                    pendingLoginEmail = null
                    pendingLoginPassword = null
                    openSession(it)
                }
                .onFailure { error ->
                    if (error is TwoFactorRequiredException) {
                        _state.update {
                            it.copy(
                                isRestoring = false,
                                isSubmitting = false,
                                requiresTwoFactor = true,
                                error = null,
                            )
                        }
                    } else {
                        pendingLoginEmail = null
                        pendingLoginPassword = null
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
    }

    fun verifyTwoFactor(code: String) {
        val email = pendingLoginEmail ?: return
        val password = pendingLoginPassword ?: return
        if (code.isBlank()) {
            _state.update { it.copy(error = "Enter your authenticator or recovery code.") }
            return
        }

        viewModelScope.launch {
            _state.update { it.copy(isSubmitting = true, error = null) }
            runCatching { sessionRepository.signIn(email, password, code.trim()) }
                .onSuccess {
                    pendingLoginEmail = null
                    pendingLoginPassword = null
                    openSession(it)
                }
                .onFailure { error ->
                    _state.update {
                        it.copy(
                            isSubmitting = false,
                            requiresTwoFactor = true,
                            error = error.message ?: "The verification code is invalid.",
                        )
                    }
                }
        }
    }

    fun cancelTwoFactor() {
        pendingLoginEmail = null
        pendingLoginPassword = null
        _state.update { it.copy(requiresTwoFactor = false, error = null, isSubmitting = false) }
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
                plans = PlanUiState(),
                vouchers = VoucherUiState(),
                sales = SalesUiState(),
                network = NetworkUiState(),
                finance = FinanceUiState(),
                reports = ReportUiState(),
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

    fun beginTwoFactorSetup() {
        viewModelScope.launch {
            _state.update {
                it.copy(
                    securityActionRunning = true,
                    securityError = null,
                    securityNotice = null,
                    recoveryCodes = emptyList(),
                )
            }
            runCatching { sessionRepository.setupTwoFactor() }
                .onSuccess { setup ->
                    _state.update { it.copy(securityActionRunning = false, twoFactorSetup = setup) }
                }
                .onFailure { error ->
                    _state.update {
                        it.copy(
                            securityActionRunning = false,
                            securityError = error.message ?: "Two-factor setup could not be started.",
                        )
                    }
                }
        }
    }

    fun confirmTwoFactor(code: String) {
        if (code.isBlank()) {
            _state.update { it.copy(securityError = "Enter the six-digit authenticator code.") }
            return
        }
        viewModelScope.launch {
            _state.update { it.copy(securityActionRunning = true, securityError = null) }
            runCatching { sessionRepository.confirmTwoFactor(code.trim()) }
                .onSuccess { confirmation ->
                    _state.update { current ->
                        current.copy(
                            securityActionRunning = false,
                            session = current.session?.copy(
                                user = current.session.user.copy(twoFactorEnabled = true),
                            ),
                            twoFactorSetup = null,
                            recoveryCodes = confirmation.recoveryCodes,
                            securityNotice = "Two-factor authentication is enabled.",
                        )
                    }
                }
                .onFailure { error ->
                    _state.update {
                        it.copy(
                            securityActionRunning = false,
                            securityError = error.message ?: "The authenticator code could not be confirmed.",
                        )
                    }
                }
        }
    }

    fun cancelTwoFactorSetup() {
        _state.update { it.copy(twoFactorSetup = null, securityError = null) }
    }

    fun disableTwoFactor(password: String) {
        if (password.isBlank()) {
            _state.update { it.copy(securityError = "Enter your password to disable two-factor authentication.") }
            return
        }
        viewModelScope.launch {
            _state.update { it.copy(securityActionRunning = true, securityError = null) }
            runCatching { sessionRepository.disableTwoFactor(password) }
                .onSuccess {
                    _state.update { current ->
                        current.copy(
                            securityActionRunning = false,
                            session = current.session?.copy(
                                user = current.session.user.copy(twoFactorEnabled = false),
                            ),
                            recoveryCodes = emptyList(),
                            securityNotice = "Two-factor authentication disabled.",
                        )
                    }
                }
                .onFailure { error ->
                    _state.update {
                        it.copy(
                            securityActionRunning = false,
                            securityError = error.message ?: "Two-factor authentication could not be disabled.",
                        )
                    }
                }
        }
    }

    fun clearSecurityFeedback() {
        _state.update {
            it.copy(securityError = null, securityNotice = null, recoveryCodes = emptyList())
        }
    }

    fun loadPlans(
        filters: PlanFilters = _state.value.plans.filters,
        page: Int = 1,
    ) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(plans = it.plans.copy(
                    isLoading = true,
                    filters = filters,
                    error = null,
                    notice = null,
                ))
            }
            runCatching { planRepository.catalog(organizationId, filters, page) }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(plans = current.plans.copy(
                            isLoading = false,
                            catalog = catalog,
                        ))
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(plans = current.plans.copy(
                            isLoading = false,
                            error = error.message ?: "Plans could not be loaded.",
                        ))
                    }
                }
        }
    }

    fun createPlan(input: PlanInput) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(plans = it.plans.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { planRepository.create(organizationId, input) }
                .onSuccess {
                    val catalog = runCatching {
                        planRepository.catalog(organizationId, _state.value.plans.filters, 1)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(plans = current.plans.copy(
                            isActionRunning = false,
                            catalog = catalog ?: current.plans.catalog,
                            notice = "Access plan created.",
                        ))
                    }
                }
                .onFailure { planActionFailed(it, "Access plan could not be created.") }
        }
    }

    fun updatePlan(plan: AccessPlanSummary, input: PlanInput) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(plans = it.plans.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { planRepository.update(organizationId, plan.id, input) }
                .onSuccess {
                    val page = _state.value.plans.catalog?.pagination?.currentPage ?: 1
                    val catalog = runCatching {
                        planRepository.catalog(organizationId, _state.value.plans.filters, page)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(plans = current.plans.copy(
                            isActionRunning = false,
                            catalog = catalog ?: current.plans.catalog,
                            notice = "Access plan updated.",
                        ))
                    }
                }
                .onFailure { planActionFailed(it, "Access plan could not be updated.") }
        }
    }

    fun deletePlan(plan: AccessPlanSummary) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(plans = it.plans.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { planRepository.delete(organizationId, plan.id) }
                .onSuccess {
                    val page = _state.value.plans.catalog?.pagination?.currentPage ?: 1
                    val catalog = runCatching {
                        planRepository.catalog(organizationId, _state.value.plans.filters, page)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(plans = current.plans.copy(
                            isActionRunning = false,
                            catalog = catalog ?: current.plans.catalog,
                            notice = "Unused access plan deleted.",
                        ))
                    }
                }
                .onFailure { planActionFailed(it, "Access plan could not be deleted.") }
        }
    }

    fun clearPlanFeedback() {
        _state.update { it.copy(plans = it.plans.copy(error = null, notice = null)) }
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

    fun prepareVoucherThermalPrint(batchId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(vouchers = it.vouchers.copy(isActionRunning = true, error = null, notice = null))
            }
            runCatching { voucherRepository.thermal(organizationId, batchId) }
                .onSuccess { printData ->
                    val detail = runCatching { voucherRepository.detail(organizationId, batchId) }.getOrNull()
                    val catalog = runCatching {
                        voucherRepository.catalog(organizationId, _state.value.vouchers.filters, 1)
                    }.getOrNull()
                    _state.update { current ->
                        current.copy(vouchers = current.vouchers.copy(
                            isActionRunning = false,
                            detail = detail ?: current.vouchers.detail,
                            catalog = catalog ?: current.vouchers.catalog,
                            pendingThermalPrint = printData,
                        ))
                    }
                }
                .onFailure { voucherActionFailed(it, "Thermal vouchers could not be prepared.") }
        }
    }

    fun consumeVoucherThermalPrint() {
        _state.update { it.copy(vouchers = it.vouchers.copy(pendingThermalPrint = null)) }
    }

    fun clearVoucherFeedback() {
        _state.update { it.copy(vouchers = it.vouchers.copy(error = null, notice = null)) }
    }

    fun loadSales(
        filters: SalesFilters = _state.value.sales.filters,
        transactionsPage: Int = 1,
        vouchersPage: Int = 1,
    ) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(sales = it.sales.copy(
                    isLoading = true,
                    filters = filters,
                    error = null,
                    notice = null,
                ))
            }
            runCatching {
                salesRepository.catalog(organizationId, filters, transactionsPage, vouchersPage)
            }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(sales = current.sales.copy(
                            isLoading = false,
                            catalog = catalog,
                            error = null,
                        ))
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(sales = current.sales.copy(
                            isLoading = false,
                            error = error.message ?: "Sales could not be loaded.",
                        ))
                    }
                }
        }
    }

    fun recordCashSale(input: CashSaleInput) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(sales = it.sales.copy(
                    isActionRunning = true,
                    error = null,
                    notice = null,
                    issuedCredential = null,
                    failedCashSale = null,
                ))
            }
            runCatching { salesRepository.recordCash(organizationId, input) }
                .onSuccess { result ->
                    if (_state.value.selectedOrganizationId != organizationId) return@onSuccess
                    val current = _state.value.sales
                    val catalog = runCatching {
                        salesRepository.catalog(
                            organizationId,
                            current.filters,
                            current.catalog?.transactionsPagination?.currentPage ?: 1,
                            current.catalog?.vouchersPagination?.currentPage ?: 1,
                        )
                    }.getOrNull()
                    _state.update { state ->
                        if (state.selectedOrganizationId != organizationId) state
                        else state.copy(sales = state.sales.copy(
                                isActionRunning = false,
                                catalog = catalog ?: state.sales.catalog,
                                customerCatalog = null,
                                issuedCredential = result.credential,
                                failedCashSale = null,
                                notice = "Direct cash sale recorded and access activated.",
                            ))
                    }
                }
                .onFailure { error ->
                    Log.e("HotFiiSales", "Direct cash sale could not be recorded.", error)
                    _state.update { state ->
                        if (state.selectedOrganizationId != organizationId) state
                        else state.copy(sales = state.sales.copy(
                                isActionRunning = false,
                                error = error.message ?: "Direct cash sale could not be recorded.",
                                failedCashSale = input,
                            ))
                    }
                }
        }
    }

    fun loadCustomers(
        filters: CustomerFilters = _state.value.sales.customerFilters,
        page: Int = 1,
    ) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update {
                it.copy(sales = it.sales.copy(
                    isLoading = true,
                    customerFilters = filters,
                    customerDetail = null,
                    error = null,
                ))
            }
            runCatching { salesRepository.customers(organizationId, filters, page) }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(sales = current.sales.copy(
                            isLoading = false,
                            customerCatalog = catalog,
                        ))
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(sales = current.sales.copy(
                            isLoading = false,
                            error = error.message ?: "Customers could not be loaded.",
                        ))
                    }
                }
        }
    }

    fun openCustomer(customerId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(sales = it.sales.copy(isLoading = true, error = null)) }
            runCatching { salesRepository.customer(organizationId, customerId) }
                .onSuccess { detail ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(sales = current.sales.copy(
                            isLoading = false,
                            customerDetail = detail,
                        ))
                    }
                }
                .onFailure { error ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(sales = current.sales.copy(
                                isLoading = false,
                                error = error.message ?: "Customer details could not be loaded.",
                            ))
                    }
                }
        }
    }

    fun closeCustomer() {
        _state.update { it.copy(sales = it.sales.copy(customerDetail = null, error = null)) }
    }

    fun clearSalesFeedback() {
        _state.update { it.copy(sales = it.sales.copy(error = null, notice = null, failedCashSale = null)) }
    }

    fun retryCashSale() {
        _state.value.sales.failedCashSale?.let(::recordCashSale)
    }

    fun consumeIssuedCredential() {
        _state.update { it.copy(sales = it.sales.copy(issuedCredential = null, notice = null)) }
    }

    fun loadRouters(filters: NetworkFilters = _state.value.network.filters, page: Int = 1) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(network = it.network.copy(isLoading = true, filters = filters, error = null, notice = null)) }
            runCatching { networkRepository.routers(organizationId, filters, page) }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(network = current.network.copy(isLoading = false, catalog = catalog))
                    }
                }
                .onFailure { error -> networkLoadFailed(organizationId, error, "Routers could not be loaded.") }
        }
    }

    fun openRouter(routerId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(network = it.network.copy(isLoading = true, error = null, notice = null)) }
            runCatching { networkRepository.router(organizationId, routerId) }
                .onSuccess { detail ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(network = current.network.copy(isLoading = false, routerDetail = detail))
                    }
                }
                .onFailure { error -> networkLoadFailed(organizationId, error, "Router details could not be loaded.") }
        }
    }

    fun closeRouter() {
        _state.update { it.copy(network = it.network.copy(routerDetail = null, error = null, notice = null)) }
    }

    fun runRouterTests(routerId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(network = it.network.copy(isActionRunning = true, error = null, notice = null)) }
            runCatching {
                val message = networkRepository.runTests(organizationId, routerId)
                val detail = networkRepository.router(organizationId, routerId)
                message to detail
            }.onSuccess { (message, detail) ->
                _state.update { current ->
                    if (current.selectedOrganizationId != organizationId) current
                    else current.copy(network = current.network.copy(
                        isActionRunning = false,
                        routerDetail = detail,
                        notice = message,
                    ))
                }
            }.onFailure { error -> networkActionFailed(organizationId, error, "Readiness tests could not be started.") }
        }
    }

    fun loadHotspotSessions(
        filters: HotspotSessionFilters = _state.value.network.sessionFilters,
        page: Int = 1,
    ) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(network = it.network.copy(
                isLoading = true,
                sessionFilters = filters,
                selectedSession = null,
                error = null,
                notice = null,
            )) }
            runCatching { networkRepository.sessions(organizationId, filters, page) }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(network = current.network.copy(isLoading = false, sessionCatalog = catalog))
                    }
                }
                .onFailure { error -> networkLoadFailed(organizationId, error, "Sessions could not be loaded.") }
        }
    }

    fun openHotspotSession(session: HotspotSessionRecord) {
        _state.update { it.copy(network = it.network.copy(selectedSession = session, error = null, notice = null)) }
    }

    fun closeHotspotSession() {
        _state.update { it.copy(network = it.network.copy(selectedSession = null, error = null, notice = null)) }
    }

    fun disconnectHotspotSession(sessionId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(network = it.network.copy(isActionRunning = true, error = null, notice = null)) }
            runCatching {
                val result = networkRepository.disconnect(organizationId, sessionId)
                val current = _state.value.network
                val catalog = networkRepository.sessions(
                    organizationId,
                    current.sessionFilters,
                    current.sessionCatalog?.pagination?.currentPage ?: 1,
                )
                result to catalog
            }.onSuccess { (result, catalog) ->
                _state.update { current ->
                    if (current.selectedOrganizationId != organizationId) current
                    else current.copy(network = current.network.copy(
                        isActionRunning = false,
                        sessionCatalog = catalog,
                        selectedSession = result.session,
                        notice = result.message,
                    ))
                }
            }.onFailure { error -> networkActionFailed(organizationId, error, "The session could not be disconnected.") }
        }
    }

    fun clearNetworkFeedback() {
        _state.update { it.copy(network = it.network.copy(error = null, notice = null)) }
    }

    fun loadFinance(
        filters: FinanceFilters = _state.value.finance.filters,
        ledgerPage: Int = 1,
        invoicePage: Int = 1,
    ) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(finance = it.finance.copy(
                isLoading = true,
                filters = filters,
                selectedInvoice = null,
                error = null,
            )) }
            runCatching { financeRepository.finance(organizationId, filters, ledgerPage, invoicePage) }
                .onSuccess { catalog ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(finance = current.finance.copy(isLoading = false, catalog = catalog))
                    }
                }
                .onFailure { error -> financeFailed(organizationId, error, "Finance could not be loaded.") }
        }
    }

    fun openInvoice(invoiceId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(finance = it.finance.copy(isLoading = true, error = null, notice = null)) }
            runCatching { financeRepository.invoice(organizationId, invoiceId) }
                .onSuccess { invoice ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(finance = current.finance.copy(isLoading = false, selectedInvoice = invoice))
                    }
                }
                .onFailure { error -> financeFailed(organizationId, error, "Invoice details could not be loaded.") }
        }
    }

    fun closeInvoice() {
        _state.update { it.copy(finance = it.finance.copy(selectedInvoice = null, error = null, notice = null)) }
    }

    fun payInvoice(invoiceId: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(finance = it.finance.copy(isActionRunning = true, error = null, notice = null)) }
            runCatching { financeRepository.startInvoicePayment(organizationId, invoiceId) }
                .onSuccess { checkout ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(finance = current.finance.copy(
                            isActionRunning = false,
                            checkoutUrl = checkout.authorizationUrl,
                        ))
                    }
                }
                .onFailure { error -> financeFailed(organizationId, error, "Invoice payment could not be started.") }
        }
    }

    fun consumeInvoiceCheckout() {
        _state.update { it.copy(finance = it.finance.copy(checkoutUrl = null)) }
    }

    fun refreshFinanceAfterPayment(status: String? = null) {
        _state.update { current ->
            current.copy(finance = current.finance.copy(
                notice = if (status == "paid") "Invoice payment received." else "Payment status is being confirmed.",
            ))
        }
        val current = _state.value.finance
        loadFinance(
            current.filters,
            current.catalog?.ledgerPagination?.currentPage ?: 1,
            current.catalog?.invoicePagination?.currentPage ?: 1,
        )
    }

    fun clearFinanceFeedback() {
        _state.update { it.copy(finance = it.finance.copy(error = null, notice = null)) }
    }

    fun loadReport(filters: ReportFilters = _state.value.reports.filters) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        viewModelScope.launch {
            _state.update { it.copy(reports = it.reports.copy(
                isLoading = true,
                filters = filters,
                error = null,
                notice = null,
            )) }
            runCatching { financeRepository.report(organizationId, filters) }
                .onSuccess { report ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(reports = current.reports.copy(
                            isLoading = false,
                            report = report,
                            filters = ReportFilters(report.from, report.to, report.routerId),
                        ))
                    }
                }
                .onFailure { error -> reportFailed(organizationId, error, "Reports could not be loaded.") }
        }
    }

    fun exportReport(format: String) {
        val organizationId = _state.value.selectedOrganizationId ?: return
        val filters = _state.value.reports.filters
        viewModelScope.launch {
            _state.update { it.copy(reports = it.reports.copy(isActionRunning = true, error = null, notice = null)) }
            runCatching { financeRepository.exportReport(organizationId, filters, format) }
                .onSuccess { export ->
                    _state.update { current ->
                        if (current.selectedOrganizationId != organizationId) current
                        else current.copy(reports = current.reports.copy(isActionRunning = false, pendingExport = export))
                    }
                }
                .onFailure { error -> reportFailed(organizationId, error, "The report could not be prepared.") }
        }
    }

    fun consumeReportExport() {
        _state.update { it.copy(reports = it.reports.copy(pendingExport = null)) }
    }

    fun clearReportFeedback() {
        _state.update { it.copy(reports = it.reports.copy(error = null, notice = null)) }
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

    private fun planActionFailed(error: Throwable, fallback: String) {
        Log.e("HotFiiPlan", fallback, error)
        _state.update {
            it.copy(plans = it.plans.copy(
                isActionRunning = false,
                error = error.message ?: fallback,
            ))
        }
    }

    private fun networkLoadFailed(organizationId: String, error: Throwable, fallback: String) {
        _state.update { current ->
            if (current.selectedOrganizationId != organizationId) current
            else current.copy(network = current.network.copy(
                isLoading = false,
                error = error.message ?: fallback,
            ))
        }
    }

    private fun networkActionFailed(organizationId: String, error: Throwable, fallback: String) {
        Log.e("HotFiiNetwork", fallback, error)
        _state.update { current ->
            if (current.selectedOrganizationId != organizationId) current
            else current.copy(network = current.network.copy(
                isActionRunning = false,
                error = error.message ?: fallback,
            ))
        }
    }

    private fun financeFailed(organizationId: String, error: Throwable, fallback: String) {
        _state.update { current ->
            if (current.selectedOrganizationId != organizationId) current
            else current.copy(finance = current.finance.copy(
                isLoading = false,
                isActionRunning = false,
                error = error.message ?: fallback,
            ))
        }
    }

    private fun reportFailed(organizationId: String, error: Throwable, fallback: String) {
        Log.e("HotFiiReport", fallback, error)
        _state.update { current ->
            if (current.selectedOrganizationId != organizationId) current
            else current.copy(reports = current.reports.copy(
                isLoading = false,
                isActionRunning = false,
                error = error.message ?: fallback,
            ))
        }
    }

    companion object {
        fun factory(
            sessionRepository: SessionRepository,
            dashboardRepository: DashboardRepository,
            planRepository: PlanRepository,
            voucherRepository: VoucherRepository,
            salesRepository: SalesRepository,
            networkRepository: NetworkRepository,
            financeRepository: FinanceRepository,
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T =
                MainViewModel(
                    sessionRepository,
                    dashboardRepository,
                    planRepository,
                    voucherRepository,
                    salesRepository,
                    networkRepository,
                    financeRepository,
                ) as T
        }
    }
}
