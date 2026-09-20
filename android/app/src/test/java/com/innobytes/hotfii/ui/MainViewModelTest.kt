package com.innobytes.hotfii.ui

import com.innobytes.hotfii.MainDispatcherRule
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.data.repository.VoucherRepository
import com.innobytes.hotfii.domain.DashboardAlerts
import com.innobytes.hotfii.domain.DashboardPulse
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.HourlySessions
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.RevenueTrend
import com.innobytes.hotfii.domain.RouterSummary
import com.innobytes.hotfii.domain.SessionUser
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

        val viewModel = MainViewModel(repository, dashboardRepository, FakeVoucherRepository())

        assertFalse(viewModel.state.value.isRestoring)
        assertEquals("org-two", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-two" to null, dashboardRepository.requests.last())
    }

    @Test
    fun `successful sign in opens the server default organization`() = runTest {
        val repository = FakeSessionRepository(signInSession = session())
        val viewModel = MainViewModel(repository, FakeDashboardRepository(), FakeVoucherRepository())

        viewModel.signIn("owner@example.com", "password")

        assertEquals("org-one", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-one", repository.selectedId)
        assertNull(viewModel.state.value.error)
    }

    @Test
    fun `organization selection is limited to the authenticated membership list`() = runTest {
        val repository = FakeSessionRepository(restoredSession = session())
        val dashboardRepository = FakeDashboardRepository()
        val viewModel = MainViewModel(repository, dashboardRepository, FakeVoucherRepository())

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
            vouchers,
        )
        val filters = VoucherFilters(search = "VB-2609", status = "printed")

        viewModel.loadVouchers(filters, 2)

        assertEquals(Triple("org-one", filters, 2), vouchers.catalogRequests.single())
        assertEquals(filters, viewModel.state.value.vouchers.filters)
        assertEquals(0, viewModel.state.value.vouchers.catalog?.pagination?.total)
    }

    private fun session() = UserSession(
        user = SessionUser(
            id = "user-one",
            name = "Muhammad",
            email = "owner@example.com",
            phone = null,
            timezone = "Africa/Lagos",
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
    var selectedId: String? = null,
) : SessionRepository {
    override suspend fun signIn(email: String, password: String): UserSession =
        requireNotNull(signInSession)

    override suspend fun restore(): UserSession? = restoredSession

    override suspend fun signOut() = Unit

    override fun selectedOrganizationId(): String? = selectedId

    override fun selectOrganization(id: String) {
        selectedId = id
    }
}
