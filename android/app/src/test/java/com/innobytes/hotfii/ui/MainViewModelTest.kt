package com.innobytes.hotfii.ui

import com.innobytes.hotfii.MainDispatcherRule
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.PlanRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.data.repository.TwoFactorRequiredException
import com.innobytes.hotfii.data.repository.VoucherRepository
import com.innobytes.hotfii.domain.DashboardAlerts
import com.innobytes.hotfii.domain.DashboardPulse
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.HourlySessions
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.AccessPlanSummary
import com.innobytes.hotfii.domain.PlanCatalog
import com.innobytes.hotfii.domain.PlanFilters
import com.innobytes.hotfii.domain.PlanInput
import com.innobytes.hotfii.domain.PlanOptions
import com.innobytes.hotfii.domain.PlanPagination
import com.innobytes.hotfii.domain.PlanPermissions
import com.innobytes.hotfii.domain.RevenueTrend
import com.innobytes.hotfii.domain.RouterSummary
import com.innobytes.hotfii.domain.SessionUser
import com.innobytes.hotfii.domain.TwoFactorConfirmation
import com.innobytes.hotfii.domain.TwoFactorSetup
import com.innobytes.hotfii.domain.UserSession
import com.innobytes.hotfii.domain.VoucherBatchDetail
import com.innobytes.hotfii.domain.VoucherCatalog
import com.innobytes.hotfii.domain.VoucherCreateInput
import com.innobytes.hotfii.domain.VoucherEditInput
import com.innobytes.hotfii.domain.VoucherFilters
import com.innobytes.hotfii.domain.VoucherOptions
import com.innobytes.hotfii.domain.VoucherPagination
import com.innobytes.hotfii.domain.VoucherPermissions
import com.innobytes.hotfii.domain.VoucherShare
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class MainViewModelTest {
    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    @Test
    fun `restores the saved organization when the app opens`() = runTest {
        val repository = FakeSessionRepository(
            restoredSession = session(),
            selectedId = "org-two",
        )
        val dashboardRepository = FakeDashboardRepository()

        val viewModel = MainViewModel(repository, dashboardRepository, FakePlanRepository(), FakeVoucherRepository())

        assertFalse(viewModel.state.value.isRestoring)
        assertEquals("org-two", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-two" to null, dashboardRepository.requests.last())
    }

    @Test
    fun `successful sign in opens the server default organization`() = runTest {
        val repository = FakeSessionRepository(signInSession = session())
        val viewModel = MainViewModel(repository, FakeDashboardRepository(), FakePlanRepository(), FakeVoucherRepository())

        viewModel.signIn("owner@example.com", "password")

        assertEquals("org-one", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-one", repository.selectedId)
        assertNull(viewModel.state.value.error)
    }

    @Test
    fun `two factor login waits for and submits the verification code`() = runTest {
        val repository = FakeSessionRepository(
            signInSession = session(),
            twoFactorRequired = true,
        )
        val viewModel = MainViewModel(repository, FakeDashboardRepository(), FakePlanRepository(), FakeVoucherRepository())

        viewModel.signIn("owner@example.com", "password")

        assertTrue(viewModel.state.value.requiresTwoFactor)
        assertNull(viewModel.state.value.session)

        viewModel.verifyTwoFactor("123456")

        assertEquals(listOf(null, "123456"), repository.submittedCodes)
        assertEquals("org-one", viewModel.state.value.selectedOrganization?.id)
    }

    @Test
    fun `organization selection is limited to the authenticated membership list`() = runTest {
        val repository = FakeSessionRepository(restoredSession = session())
        val dashboardRepository = FakeDashboardRepository()
        val viewModel = MainViewModel(repository, dashboardRepository, FakePlanRepository(), FakeVoucherRepository())

        viewModel.selectOrganization("outside-org")
        assertEquals("org-one", viewModel.state.value.selectedOrganization?.id)

        viewModel.selectOrganization("org-two")
        assertEquals("org-two", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-two" to null, dashboardRepository.requests.last())
    }

    @Test
    fun `router selection is limited to the dashboard router list`() = runTest {
        val dashboardRepository = FakeDashboardRepository()
        val viewModel = MainViewModel(
            FakeSessionRepository(restoredSession = session()),
            dashboardRepository,
            FakePlanRepository(),
            FakeVoucherRepository(),
        )

        viewModel.selectRouter("outside-router")
        assertNull(viewModel.state.value.selectedRouterId)

        viewModel.selectRouter("router-one")
        assertEquals("router-one", viewModel.state.value.selectedRouterId)
        assertEquals("org-one" to "router-one", dashboardRepository.requests.last())
    }

    @Test
    fun `voucher filters and pagination are sent for the selected organization`() = runTest {
        val vouchers = FakeVoucherRepository()
        val viewModel = MainViewModel(
            FakeSessionRepository(restoredSession = session()),
            FakeDashboardRepository(),
            FakePlanRepository(),
            vouchers,
        )
        val filters = VoucherFilters(search = "VB-2609", status = "printed")

        viewModel.loadVouchers(filters, 2)

        assertEquals(Triple("org-one", filters, 2), vouchers.catalogRequests.single())
        assertEquals(filters, viewModel.state.value.vouchers.filters)
        assertEquals(0, viewModel.state.value.vouchers.catalog?.pagination?.total)
    }

    @Test
    fun plan_filters_and_pagination_are_sent_for_the_selected_organization() = runTest {
        val plans = FakePlanRepository()
        val viewModel = MainViewModel(
            FakeSessionRepository(restoredSession = session()),
            FakeDashboardRepository(),
            plans,
            FakeVoucherRepository(),
        )
        val filters = PlanFilters(search = "Day", type = "paid", state = "active")

        viewModel.loadPlans(filters, 3)

        assertEquals(Triple("org-one", filters, 3), plans.catalogRequests.single())
        assertEquals(filters, viewModel.state.value.plans.filters)
        assertEquals(0, viewModel.state.value.plans.catalog?.pagination?.total)
    }

    private fun session() = UserSession(
        user = SessionUser(
            id = "user-one",
            name = "Muhammad",
            email = "owner@example.com",
            phone = null,
            timezone = "Africa/Lagos",
            twoFactorEnabled = false,
        ),
        organizations = listOf(
            organization("org-one", "BALA STARLINK"),
            organization("org-two", "InnoBytes Lab"),
        ),
        defaultOrganizationId = "org-one",
    )

    private fun organization(id: String, name: String) = OrganizationSummary(
        id = id,
        name = name,
        slug = name.lowercase().replace(' ', '-'),
        role = "owner",
        mode = "commerce",
        status = "trial",
        currency = "NGN",
        timezone = "Africa/Lagos",
        permissions = setOf("manage_plans"),
    )
}

private class FakePlanRepository : PlanRepository {
    val catalogRequests = mutableListOf<Triple<String, PlanFilters, Int>>()

    override suspend fun catalog(
        organizationId: String,
        filters: PlanFilters,
        page: Int,
    ): PlanCatalog {
        catalogRequests += Triple(organizationId, filters, page)
        return PlanCatalog(
            plans = emptyList(),
            pagination = PlanPagination(page, page, 20, 0),
            options = PlanOptions(emptyList(), emptyList(), "Africa/Lagos"),
            permissions = PlanPermissions(canManage = true),
        )
    }

    override suspend fun create(organizationId: String, input: PlanInput): AccessPlanSummary =
        error("Not used in this test")

    override suspend fun update(
        organizationId: String,
        planId: String,
        input: PlanInput,
    ): AccessPlanSummary = error("Not used in this test")

    override suspend fun delete(organizationId: String, planId: String) = Unit
}

private class FakeVoucherRepository : VoucherRepository {
    val catalogRequests = mutableListOf<Triple<String, VoucherFilters, Int>>()

    override suspend fun catalog(
        organizationId: String,
        filters: VoucherFilters,
        page: Int,
    ): VoucherCatalog {
        catalogRequests += Triple(organizationId, filters, page)
        return VoucherCatalog(
            batches = emptyList(),
            pagination = VoucherPagination(page, page, 20, 0),
            options = VoucherOptions(emptyList(), emptyList(), listOf(2, 4, 6, 8, 10, 12), emptyList(), emptyList()),
            permissions = VoucherPermissions(canCreate = true, canManage = true),
        )
    }

    override suspend fun detail(organizationId: String, batchId: String): VoucherBatchDetail =
        error("Not used in this test")

    override suspend fun create(organizationId: String, input: VoucherCreateInput): VoucherBatchDetail =
        error("Not used in this test")

    override suspend fun update(
        organizationId: String,
        batchId: String,
        input: VoucherEditInput,
    ): VoucherBatchDetail = error("Not used in this test")

    override suspend fun delete(organizationId: String, batchId: String) = Unit

    override suspend fun thermal(organizationId: String, batchId: String): VoucherShare =
        error("Not used in this test")

    override suspend fun sharePdf(
        organizationId: String,
        batchId: String,
        reference: String,
        quantity: Int,
    ): List<String> = error("Not used in this test")
}

private class FakeDashboardRepository : DashboardRepository {
    val requests = mutableListOf<Pair<String, String?>>()

    override suspend fun load(organizationId: String, routerId: String?): DashboardSnapshot {
        requests += organizationId to routerId
        return dashboard(organizationId, routerId)
    }

    private fun dashboard(organizationId: String, routerId: String?) = DashboardSnapshot(
        organizationName = organizationId,
        currency = "NGN",
        routers = listOf(RouterSummary("router-one", "Main router", "Main site", "online")),
        selectedRouter = routerId?.let { RouterSummary(it, "Main router", "Main site", "online") },
        alerts = DashboardAlerts(billingSuspended = false, paymentProfileRequired = false),
        pulse = DashboardPulse(
            revenueTodayKobo = 0,
            revenueYesterdayKobo = 0,
            revenueDelta = null,
            salesToday = 0,
            salesYesterday = 0,
            salesDelta = null,
            activeSessions = 0,
            sessionsStartedToday = 0,
            sessionsStartedYesterday = 0,
            sessionsDelta = null,
            onlineRouters = 1,
            totalRouters = 1,
            availableVouchers = 0,
            vouchersInUse = 0,
        ),
        revenue = RevenueTrend(emptyList(), emptyList(), 0, 0, 14),
        fleet = emptyList(),
        fleetTotal = 1,
        sessionsToday = HourlySessions(emptyList(), emptyList(), 0, null, null),
        topPlans = emptyList(),
        topPlansDays = 30,
        networkHealth = emptyList(),
        recentTransactions = emptyList(),
        generatedAt = "2026-09-19T12:00:00+01:00",
    )
}

private class FakeSessionRepository(
    private val restoredSession: UserSession? = null,
    private val signInSession: UserSession? = null,
    private val twoFactorRequired: Boolean = false,
    var selectedId: String? = null,
) : SessionRepository {
    val submittedCodes = mutableListOf<String?>()

    override suspend fun signIn(email: String, password: String, twoFactorCode: String?): UserSession {
        submittedCodes += twoFactorCode
        if (twoFactorRequired && twoFactorCode == null) {
            throw TwoFactorRequiredException("Enter your authenticator code.")
        }
        return requireNotNull(signInSession)
    }

    override suspend fun restore(): UserSession? = restoredSession

    override suspend fun signOut() = Unit

    override suspend fun setupTwoFactor(): TwoFactorSetup = error("Not used in this test")

    override suspend fun confirmTwoFactor(code: String): TwoFactorConfirmation =
        error("Not used in this test")

    override suspend fun disableTwoFactor(password: String) = Unit

    override fun selectedOrganizationId(): String? = selectedId

    override fun selectOrganization(id: String) {
        selectedId = id
    }
}
