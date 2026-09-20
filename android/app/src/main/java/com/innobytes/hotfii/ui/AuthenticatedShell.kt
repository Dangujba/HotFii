package com.innobytes.hotfii.ui

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.BrightnessAuto
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.Dashboard
import androidx.compose.material.icons.outlined.DarkMode
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material.icons.outlined.LightMode
import androidx.compose.material.icons.outlined.Logout
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Router
import androidx.compose.material.icons.outlined.Security
import androidx.compose.material.icons.outlined.Storefront
import androidx.compose.material.icons.outlined.Tune
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.Assessment
import androidx.compose.material.icons.outlined.MoreHoriz
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.R
import com.innobytes.hotfii.data.preferences.AppThemeMode
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.TwoFactorSetup
import com.innobytes.hotfii.domain.UserSession
import com.innobytes.hotfii.ui.vouchers.VoucherScreen
import com.innobytes.hotfii.ui.plans.PlanScreen
import com.innobytes.hotfii.ui.network.NetworkScreen
import com.innobytes.hotfii.ui.sales.SalesScreen
import com.innobytes.hotfii.ui.workspace.WorkspaceScreen
import com.innobytes.hotfii.ui.finance.FinanceScreen
import com.innobytes.hotfii.ui.reports.ReportScreen
import com.innobytes.hotfii.ui.notifications.NotificationScreen
import com.innobytes.hotfii.ui.settings.OrganizationSettingsScreen

private enum class MainDestination(val label: String, val icon: ImageVector) {
    Dashboard("Home", Icons.Outlined.Dashboard),
    Sales("Sales", Icons.Outlined.Storefront),
    Network("Network", Icons.Outlined.Router),
    Finance("Finance", Icons.Outlined.AccountBalanceWallet),
    More("More", Icons.Outlined.MoreHoriz),
}

private enum class MoreDestination {
    Plans,
    Vouchers,
    Reports,
    Notifications,
    Settings,
    Account,
}

@Composable
fun AuthenticatedShell(
    state: MainUiState,
    viewModel: MainViewModel,
    themeMode: AppThemeMode,
    onThemeModeChanged: (AppThemeMode) -> Unit,
    biometricEnabled: Boolean,
    biometricMessage: String?,
    onBiometricChanged: (Boolean) -> Unit,
    onBiometricMessageDismissed: () -> Unit,
    openFinanceRequest: Int = 0,
    invoicePaymentStatus: String? = null,
    openNotificationsRequest: Int = 0,
    onRequestNotificationPermission: () -> Unit,
    pushNotificationsAvailable: Boolean,
) {
    val session = requireNotNull(state.session)
    var destination by rememberSaveable { mutableStateOf(MainDestination.Dashboard) }
    var moreDestination by rememberSaveable { mutableStateOf<MoreDestination?>(null) }
    val canViewFinance = state.selectedOrganization?.role in setOf("owner", "manager", "accountant", "viewer")
    val bottomDestinations = MainDestination.entries.filter { it != MainDestination.Finance || canViewFinance }

    LaunchedEffect(openFinanceRequest) {
        if (openFinanceRequest > 0 && canViewFinance) {
            destination = MainDestination.Finance
            moreDestination = null
            viewModel.refreshFinanceAfterPayment(invoicePaymentStatus)
        }
    }

    LaunchedEffect(openNotificationsRequest) {
        if (openNotificationsRequest > 0) {
            destination = MainDestination.More
            moreDestination = MoreDestination.Notifications
        }
    }

    BackHandler(enabled = destination != MainDestination.Dashboard || moreDestination != null) {
        if (moreDestination != null) moreDestination = null else destination = MainDestination.Dashboard
    }

    Scaffold(
        bottomBar = {
            NavigationBar {
                bottomDestinations.forEach { item ->
                    NavigationBarItem(
                        selected = destination == item,
                        onClick = {
                            destination = item
                            if (item != MainDestination.More) moreDestination = null
                        },
                        icon = { Icon(item.icon, contentDescription = null) },
                        label = { Text(item.label) },
                        alwaysShowLabel = false,
                    )
                }
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when (destination) {
                MainDestination.Dashboard -> WorkspaceScreen(
                    session = session,
                    selectedOrganization = state.selectedOrganization,
                    dashboard = state.dashboard,
                    selectedRouterId = state.selectedRouterId,
                    isRefreshing = state.isSubmitting || state.isDashboardLoading,
                    error = state.error,
                    dashboardError = state.dashboardError,
                    onOrganizationSelected = viewModel::selectOrganization,
                    onRouterSelected = viewModel::selectRouter,
                    onRefresh = viewModel::refresh,
                    onDashboardRefresh = viewModel::refreshDashboard,
                    onSignOut = viewModel::signOut,
                )

                MainDestination.Sales -> SalesScreen(
                    organizationId = state.selectedOrganizationId,
                    organizationName = state.selectedOrganization?.name,
                    state = state.sales,
                    onLoad = viewModel::loadSales,
                    onLoadCustomers = viewModel::loadCustomers,
                    onOpenCustomer = viewModel::openCustomer,
                    onCloseCustomer = viewModel::closeCustomer,
                    onRecordCash = viewModel::recordCashSale,
                    onRetryCash = viewModel::retryCashSale,
                    onFeedbackDismissed = viewModel::clearSalesFeedback,
                    onCredentialConsumed = viewModel::consumeIssuedCredential,
                )

                MainDestination.Network -> NetworkScreen(
                    organizationId = state.selectedOrganizationId,
                    organizationName = state.selectedOrganization?.name,
                    state = state.network,
                    onLoadRouters = viewModel::loadRouters,
                    onOpenRouter = viewModel::openRouter,
                    onCloseRouter = viewModel::closeRouter,
                    onRunRouterTests = viewModel::runRouterTests,
                    onLoadSessions = viewModel::loadHotspotSessions,
                    onOpenSession = viewModel::openHotspotSession,
                    onCloseSession = viewModel::closeHotspotSession,
                    onDisconnectSession = viewModel::disconnectHotspotSession,
                    onFeedbackDismissed = viewModel::clearNetworkFeedback,
                )

                MainDestination.Finance -> FinanceScreen(
                    organizationId = state.selectedOrganizationId,
                    organizationName = state.selectedOrganization?.name,
                    state = state.finance,
                    onLoad = viewModel::loadFinance,
                    onOpenInvoice = viewModel::openInvoice,
                    onCloseInvoice = viewModel::closeInvoice,
                    onPayInvoice = viewModel::payInvoice,
                    onCheckoutConsumed = viewModel::consumeInvoiceCheckout,
                    onFeedbackDismissed = viewModel::clearFinanceFeedback,
                )

                MainDestination.More -> when (moreDestination) {
                    null -> MoreScreen(canViewFinance = canViewFinance, onOpen = { moreDestination = it })
                    MoreDestination.Plans -> PlanScreen(
                        organizationId = state.selectedOrganizationId,
                        organizationName = state.selectedOrganization?.name,
                        state = state.plans,
                        onLoad = viewModel::loadPlans,
                        onCreate = viewModel::createPlan,
                        onUpdate = viewModel::updatePlan,
                        onDelete = viewModel::deletePlan,
                        onFeedbackDismissed = viewModel::clearPlanFeedback,
                    )
                    MoreDestination.Vouchers -> VoucherScreen(
                        organizationId = state.selectedOrganizationId,
                        organizationName = state.selectedOrganization?.name,
                        state = state.vouchers,
                        onLoad = viewModel::loadVouchers,
                        onOpen = viewModel::openVoucherBatch,
                        onClose = viewModel::closeVoucherBatch,
                        onCreate = viewModel::createVoucherBatch,
                        onUpdate = viewModel::updateVoucherBatch,
                        onDelete = viewModel::deleteVoucherBatch,
                        onShare = viewModel::shareVoucherPdf,
                        onShareConsumed = viewModel::consumeVoucherPdfShare,
                        onThermalPrint = viewModel::prepareVoucherThermalPrint,
                        onThermalPrintConsumed = viewModel::consumeVoucherThermalPrint,
                        onFeedbackDismissed = viewModel::clearVoucherFeedback,
                    )
                    MoreDestination.Reports -> ReportScreen(
                        organizationId = state.selectedOrganizationId,
                        organizationName = state.selectedOrganization?.name,
                        state = state.reports,
                        onBack = { moreDestination = null },
                        onLoad = viewModel::loadReport,
                        onExport = viewModel::exportReport,
                        onExportConsumed = viewModel::consumeReportExport,
                        onFeedbackDismissed = viewModel::clearReportFeedback,
                    )
                    MoreDestination.Notifications -> NotificationScreen(
                        organizationId = state.selectedOrganizationId,
                        organizationName = state.selectedOrganization?.name,
                        state = state.notifications,
                        onBack = { moreDestination = null },
                        onLoad = viewModel::loadNotifications,
                        onUpdatePreferences = viewModel::updateNotificationPreferences,
                        onMarkRead = viewModel::markNotificationsRead,
                        onFeedbackDismissed = viewModel::clearNotificationFeedback,
                        onRequestPermission = onRequestNotificationPermission,
                        pushAvailable = pushNotificationsAvailable,
                    )
                    MoreDestination.Settings -> OrganizationSettingsScreen(
                        organizationId = state.selectedOrganizationId,
                        organizationName = state.selectedOrganization?.name,
                        state = state.settings,
                        onBack = { moreDestination = null },
                        onLoad = viewModel::loadSettings,
                        onLoadAuditPage = viewModel::loadAuditPage,
                        onLoadTeamPage = viewModel::loadTeamPage,
                        onUpdateOrganization = viewModel::updateOrganizationSettings,
                        onSubmitPaymentProfile = viewModel::submitPaymentProfile,
                        onAddTeamMember = viewModel::addTeamMember,
                        onUpdateTeamRole = viewModel::updateTeamRole,
                        onRevokeDeviceSession = viewModel::revokeDeviceSession,
                        onFeedbackDismissed = viewModel::clearSettingsFeedback,
                    )
                    MoreDestination.Account -> AccountScreen(
                        session = session,
                        selectedOrganization = state.selectedOrganization,
                        isBusy = state.isSubmitting || state.securityActionRunning,
                        themeMode = themeMode,
                        biometricEnabled = biometricEnabled,
                        biometricMessage = biometricMessage,
                        twoFactorSetup = state.twoFactorSetup,
                        recoveryCodes = state.recoveryCodes,
                        securityError = state.securityError,
                        securityNotice = state.securityNotice,
                        onOrganizationSelected = viewModel::selectOrganization,
                        onThemeModeChanged = onThemeModeChanged,
                        onBiometricChanged = onBiometricChanged,
                        onBiometricMessageDismissed = onBiometricMessageDismissed,
                        onBeginTwoFactorSetup = viewModel::beginTwoFactorSetup,
                        onConfirmTwoFactor = viewModel::confirmTwoFactor,
                        onCancelTwoFactorSetup = viewModel::cancelTwoFactorSetup,
                        onDisableTwoFactor = viewModel::disableTwoFactor,
                        onSecurityFeedbackDismissed = viewModel::clearSecurityFeedback,
                        onRefresh = viewModel::refresh,
                        onSignOut = viewModel::signOut,
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MoreScreen(canViewFinance: Boolean, onOpen: (MoreDestination) -> Unit) {
    Scaffold(topBar = { TopAppBar(title = { Text("More") }) }) { padding ->
        LazyColumn(Modifier.fillMaxSize().padding(padding)) {
            item { MoreRow("Plans", "Create and manage access plans", Icons.Outlined.Tune) { onOpen(MoreDestination.Plans) } }
            item { MoreRow("Vouchers", "Generate, print, and manage voucher batches", Icons.Outlined.ConfirmationNumber) { onOpen(MoreDestination.Vouchers) } }
            if (canViewFinance) item { MoreRow("Reports", "Sales, channels, plans, and network usage", Icons.Outlined.Assessment) { onOpen(MoreDestination.Reports) } }
            item { MoreRow("Notifications", "Router, payment, invoice, and account alerts", Icons.Outlined.Notifications) { onOpen(MoreDestination.Notifications) } }
            item { MoreRow("Organization settings", "Profile, payments, team, audit, and devices", Icons.Outlined.Business) { onOpen(MoreDestination.Settings) } }
            item { MoreRow("Account", "Theme, fingerprint, authenticator, and sign out", Icons.Outlined.Person) { onOpen(MoreDestination.Account) } }
        }
    }
}

@Composable
private fun MoreRow(title: String, subtitle: String, icon: ImageVector, onClick: () -> Unit) {
    ListItem(
        headlineContent = { Text(title, fontWeight = FontWeight.SemiBold) },
        supportingContent = { Text(subtitle) },
        leadingContent = { Icon(icon, null, tint = MaterialTheme.colorScheme.primary) },
        trailingContent = { Icon(Icons.Outlined.ChevronRight, null) },
        modifier = Modifier.clickable(onClick = onClick),
    )
    HorizontalDivider(Modifier.padding(start = 56.dp))
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AccountScreen(
    session: UserSession,
    selectedOrganization: OrganizationSummary?,
    isBusy: Boolean,
    themeMode: AppThemeMode,
    biometricEnabled: Boolean,
    biometricMessage: String?,
    twoFactorSetup: TwoFactorSetup?,
    recoveryCodes: List<String>,
    securityError: String?,
    securityNotice: String?,
    onOrganizationSelected: (String) -> Unit,
    onThemeModeChanged: (AppThemeMode) -> Unit,
    onBiometricChanged: (Boolean) -> Unit,
    onBiometricMessageDismissed: () -> Unit,
    onBeginTwoFactorSetup: () -> Unit,
    onConfirmTwoFactor: (String) -> Unit,
    onCancelTwoFactorSetup: () -> Unit,
    onDisableTwoFactor: (String) -> Unit,
    onSecurityFeedbackDismissed: () -> Unit,
    onRefresh: () -> Unit,
    onSignOut: () -> Unit,
) {
    var confirmSignOut by rememberSaveable { mutableStateOf(false) }
    var showThemeChooser by rememberSaveable { mutableStateOf(false) }
    var showDisableTwoFactor by rememberSaveable { mutableStateOf(false) }
    var confirmationCode by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    val clipboard = LocalClipboardManager.current

    LaunchedEffect(session.user.twoFactorEnabled) {
        if (!session.user.twoFactorEnabled && showDisableTwoFactor) {
            showDisableTwoFactor = false
            password = ""
        }
    }

    if (confirmSignOut) {
        AlertDialog(
            onDismissRequest = { confirmSignOut = false },
            title = { Text("Sign out of HotFii?") },
            text = { Text("You will need to sign in again on this device.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        confirmSignOut = false
                        onSignOut()
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                ) { Text("Sign out") }
            },
            dismissButton = { TextButton(onClick = { confirmSignOut = false }) { Text("Cancel") } },
        )
    }
    if (showThemeChooser) {
        AlertDialog(
            onDismissRequest = { showThemeChooser = false },
            title = { Text("Choose theme") },
            text = {
                Column {
                    AppThemeMode.entries.forEachIndexed { index, mode ->
                        ListItem(
                            headlineContent = { Text(mode.displayName()) },
                            leadingContent = { Icon(mode.icon(), contentDescription = null) },
                            trailingContent = {
                                if (mode == themeMode) Icon(Icons.Outlined.Check, contentDescription = "Selected")
                            },
                            modifier = Modifier.clickable {
                                onThemeModeChanged(mode)
                                showThemeChooser = false
                            },
                        )
                        if (index < AppThemeMode.entries.lastIndex) HorizontalDivider()
                    }
                }
            },
            confirmButton = { TextButton(onClick = { showThemeChooser = false }) { Text("Cancel") } },
        )
    }
    twoFactorSetup?.let { setup ->
        AlertDialog(
            onDismissRequest = {
                confirmationCode = ""
                onCancelTwoFactorSetup()
            },
            title = { Text("Set up authenticator") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                    Text("Add this setup key to your authenticator app, then enter its six-digit code.")
                    SelectionContainer {
                        Text(
                            setup.secret,
                            style = MaterialTheme.typography.titleMedium,
                            fontFamily = FontFamily.Monospace,
                        )
                    }
                    OutlinedTextField(
                        value = confirmationCode,
                        onValueChange = { confirmationCode = it },
                        label = { Text("Six-digit code") },
                        isError = securityError != null,
                        supportingText = securityError?.let { message -> { Text(message) } },
                        singleLine = true,
                        enabled = !isBusy,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            },
            confirmButton = {
                TextButton(
                    onClick = { onConfirmTwoFactor(confirmationCode) },
                    enabled = !isBusy,
                ) { Text("Enable") }
            },
            dismissButton = {
                TextButton(
                    onClick = {
                        confirmationCode = ""
                        onCancelTwoFactorSetup()
                    },
                    enabled = !isBusy,
                ) { Text("Cancel") }
            },
        )
    }
    if (recoveryCodes.isNotEmpty()) {
        AlertDialog(
            onDismissRequest = onSecurityFeedbackDismissed,
            title = { Text("Save recovery codes") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Each code can be used once if your authenticator is unavailable.")
                    SelectionContainer {
                        Text(recoveryCodes.joinToString("\n"), fontFamily = FontFamily.Monospace)
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    clipboard.setText(AnnotatedString(recoveryCodes.joinToString("\n")))
                    onSecurityFeedbackDismissed()
                }) { Text("Copy and close") }
            },
        )
    }
    if (showDisableTwoFactor) {
        AlertDialog(
            onDismissRequest = {
                password = ""
                showDisableTwoFactor = false
            },
            title = { Text("Disable two-factor authentication?") },
            text = {
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Current password") },
                    visualTransformation = PasswordVisualTransformation(),
                    isError = securityError != null,
                    supportingText = securityError?.let { message -> { Text(message) } },
                    singleLine = true,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                )
            },
            confirmButton = {
                TextButton(
                    onClick = { onDisableTwoFactor(password) },
                    enabled = !isBusy,
                    colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                ) { Text("Disable") }
            },
            dismissButton = {
                TextButton(onClick = {
                    password = ""
                    showDisableTwoFactor = false
                }) { Text("Cancel") }
            },
        )
    }

    Scaffold(topBar = { TopAppBar(title = { Text("Account", fontWeight = FontWeight.Bold) }) }) { padding ->
        Box(
            Modifier.fillMaxSize().padding(padding),
            contentAlignment = Alignment.TopCenter,
        ) {
            LazyColumn(
                Modifier.fillMaxWidth().widthIn(max = 680.dp),
                contentPadding = PaddingValues(bottom = 32.dp),
            ) {
                item {
                    Row(
                        Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 18.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(14.dp),
                    ) {
                        Image(
                            painter = painterResource(R.drawable.hotfii_icon),
                            contentDescription = null,
                            modifier = Modifier.size(54.dp),
                        )
                        Column {
                            Text(session.user.name, style = MaterialTheme.typography.titleLarge)
                            Text(session.user.email, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
                if (isBusy) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
                item { SectionLabel("Organizations") }
                item {
                    Column {
                        session.organizations.forEachIndexed { index, organization ->
                            ListItem(
                                headlineContent = { Text(organization.name, fontWeight = FontWeight.SemiBold) },
                                supportingContent = { Text(organization.role.replaceFirstChar(Char::uppercase)) },
                                leadingContent = { Icon(Icons.Outlined.Business, contentDescription = null) },
                                trailingContent = {
                                    if (organization.id == selectedOrganization?.id) {
                                        Icon(Icons.Outlined.Check, contentDescription = "Selected")
                                    }
                                },
                                modifier = Modifier.clickable { onOrganizationSelected(organization.id) },
                            )
                            if (index < session.organizations.lastIndex) {
                                HorizontalDivider(modifier = Modifier.padding(start = 56.dp))
                            }
                        }
                    }
                }
                item { SectionLabel("Appearance") }
                item {
                    ListItem(
                        headlineContent = { Text("Theme") },
                        supportingContent = { Text(themeMode.displayName()) },
                        leadingContent = { Icon(themeMode.icon(), contentDescription = null) },
                        modifier = Modifier.clickable { showThemeChooser = true },
                    )
                }
                item { SectionLabel("Security") }
                item {
                    Column {
                        ListItem(
                            headlineContent = { Text("Fingerprint login") },
                            supportingContent = { Text("Protect this saved session on this device") },
                            leadingContent = { Icon(Icons.Outlined.Fingerprint, contentDescription = null) },
                            trailingContent = {
                                Switch(
                                    checked = biometricEnabled,
                                    onCheckedChange = onBiometricChanged,
                                    enabled = !isBusy,
                                )
                            },
                        )
                        HorizontalDivider(modifier = Modifier.padding(start = 56.dp))
                        ListItem(
                            headlineContent = { Text("Authenticator app") },
                            supportingContent = {
                                Text(if (session.user.twoFactorEnabled) "Two-factor authentication is on" else "Require a code after your password")
                            },
                            leadingContent = { Icon(Icons.Outlined.Security, contentDescription = null) },
                            trailingContent = {
                                TextButton(
                                    onClick = {
                                        if (session.user.twoFactorEnabled) {
                                            showDisableTwoFactor = true
                                        } else {
                                            onBeginTwoFactorSetup()
                                        }
                                    },
                                    enabled = !isBusy,
                                ) {
                                    Text(if (session.user.twoFactorEnabled) "Disable" else "Set up")
                                }
                            },
                        )
                    }
                }
                if (biometricMessage != null) {
                    item {
                        MessageRow(
                            message = biometricMessage,
                            isError = false,
                            onDismiss = onBiometricMessageDismissed,
                        )
                    }
                }
                if (securityError != null && twoFactorSetup == null && !showDisableTwoFactor) {
                    item { MessageRow(securityError, true, onSecurityFeedbackDismissed) }
                }
                if (securityNotice != null && recoveryCodes.isEmpty()) {
                    item { MessageRow(securityNotice, false, onSecurityFeedbackDismissed) }
                }
                item { SectionLabel("Account") }
                item {
                    Column {
                        ListItem(
                            headlineContent = { Text("Refresh account") },
                            leadingContent = { Icon(Icons.Outlined.Refresh, contentDescription = null) },
                            modifier = Modifier.clickable(enabled = !isBusy, onClick = onRefresh),
                        )
                        HorizontalDivider(modifier = Modifier.padding(start = 56.dp))
                        ListItem(
                            headlineContent = { Text("Sign out", color = MaterialTheme.colorScheme.error) },
                            leadingContent = {
                                Icon(
                                    Icons.Outlined.Logout,
                                    contentDescription = null,
                                    tint = MaterialTheme.colorScheme.error,
                                )
                            },
                            modifier = Modifier.clickable(enabled = !isBusy) { confirmSignOut = true },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(
        text,
        style = MaterialTheme.typography.labelLarge,
        color = MaterialTheme.colorScheme.primary,
        modifier = Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, top = 22.dp, bottom = 8.dp),
    )
}

@Composable
private fun MessageRow(message: String, isError: Boolean, onDismiss: () -> Unit) {
    Surface(
        color = if (isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer,
        contentColor = if (isError) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onSecondaryContainer,
        modifier = Modifier.padding(horizontal = 20.dp, vertical = 8.dp),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(start = 14.dp, top = 8.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(message, modifier = Modifier.weight(1f))
            IconButton(onClick = onDismiss) { Icon(Icons.Outlined.Close, contentDescription = "Dismiss") }
        }
    }
}

private fun AppThemeMode.displayName(): String = when (this) {
    AppThemeMode.System -> "Use device setting"
    AppThemeMode.Light -> "Light"
    AppThemeMode.Dark -> "Dark"
}

private fun AppThemeMode.icon(): ImageVector = when (this) {
    AppThemeMode.System -> Icons.Outlined.BrightnessAuto
    AppThemeMode.Light -> Icons.Outlined.LightMode
    AppThemeMode.Dark -> Icons.Outlined.DarkMode
}
