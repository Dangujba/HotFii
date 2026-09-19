package com.innobytes.hotfii.ui

import com.innobytes.hotfii.MainDispatcherRule
import com.innobytes.hotfii.data.repository.DashboardRepository
import com.innobytes.hotfii.data.repository.SessionRepository
import com.innobytes.hotfii.domain.DashboardAlerts
import com.innobytes.hotfii.domain.DashboardPulse
import com.innobytes.hotfii.domain.DashboardSnapshot
import com.innobytes.hotfii.domain.HourlySessions
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.RevenueTrend
import com.innobytes.hotfii.domain.RouterSummary
import com.innobytes.hotfii.domain.SessionUser
import com.innobytes.hotfii.domain.UserSession
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

        val viewModel = MainViewModel(repository, dashboardRepository)

        assertFalse(viewModel.state.value.isRestoring)
        assertEquals("org-two", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-two" to null, dashboardRepository.requests.last())
    }

    @Test
    fun `successful sign in opens the server default organization`() = runTest {
        val repository = FakeSessionRepository(signInSession = session())
        val viewModel = MainViewModel(repository, FakeDashboardRepository())

        viewModel.signIn("owner@example.com", "password")

        assertEquals("org-one", viewModel.state.value.selectedOrganization?.id)
        assertEquals("org-one", repository.selectedId)
        assertNull(viewModel.state.value.error)
    }

    @Test
    fun `organization selection is limited to the authenticated membership list`() = runTest {
        val repository = FakeSessionRepository(restoredSession = session())
        val dashboardRepository = FakeDashboardRepository()
        val viewModel = MainViewModel(repository, dashboardRepository)

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
        )

        viewModel.selectRouter("outside-router")
        assertNull(viewModel.state.value.selectedRouterId)

        viewModel.selectRouter("router-one")
        assertEquals("router-one", viewModel.state.value.selectedRouterId)
        assertEquals("org-one" to "router-one", dashboardRepository.requests.last())
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
