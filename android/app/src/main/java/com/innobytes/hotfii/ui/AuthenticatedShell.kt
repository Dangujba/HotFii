package com.innobytes.hotfii.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.Dashboard
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.R
import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.UserSession
import com.innobytes.hotfii.ui.vouchers.VoucherScreen
import com.innobytes.hotfii.ui.workspace.WorkspaceScreen

private enum class MainDestination(val label: String, val icon: ImageVector) {
    Dashboard("Dashboard", Icons.Outlined.Dashboard),
    Vouchers("Vouchers", Icons.Outlined.ConfirmationNumber),
    Account("Account", Icons.Outlined.Person),
}

@Composable
fun AuthenticatedShell(
    state: MainUiState,
    viewModel: MainViewModel,
) {
    val session = requireNotNull(state.session)
    var destination by rememberSaveable { mutableStateOf(MainDestination.Dashboard) }

    Scaffold(
        bottomBar = {
            NavigationBar {
                MainDestination.entries.forEach { item ->
                    NavigationBarItem(
                        selected = destination == item,
                        onClick = { destination = item },
                        icon = { Icon(item.icon, contentDescription = null) },
                        label = { Text(item.label) },
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

                MainDestination.Vouchers -> VoucherScreen(
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
                    onFeedbackDismissed = viewModel::clearVoucherFeedback,
                )

                MainDestination.Account -> AccountScreen(
                    session = session,
                    selectedOrganization = state.selectedOrganization,
                    isBusy = state.isSubmitting,
                    onOrganizationSelected = viewModel::selectOrganization,
                    onRefresh = viewModel::refresh,
                    onSignOut = viewModel::signOut,
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AccountScreen(
    session: UserSession,
    selectedOrganization: OrganizationSummary?,
    isBusy: Boolean,
    onOrganizationSelected: (String) -> Unit,
    onRefresh: () -> Unit,
    onSignOut: () -> Unit,
) {
    var confirmSignOut by rememberSaveable { mutableStateOf(false) }
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
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = androidx.compose.material3.MaterialTheme.colorScheme.error,
                    ),
                ) { Text("Sign out") }
            },
            dismissButton = { TextButton(onClick = { confirmSignOut = false }) { Text("Cancel") } },
        )
    }

    Scaffold(
        topBar = { TopAppBar(title = { Text("Account", fontWeight = FontWeight.Bold) }) },
    ) { padding ->
        Box(
            Modifier.fillMaxSize().padding(padding).padding(20.dp),
            contentAlignment = Alignment.TopCenter,
        ) {
            Column(
                Modifier.fillMaxWidth().widthIn(max = 680.dp),
                verticalArrangement = Arrangement.spacedBy(18.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                    Image(
                        painter = painterResource(R.drawable.hotfii_icon),
                        contentDescription = null,
                        modifier = Modifier.size(54.dp),
                    )
                    Column {
                        Text(session.user.name, style = androidx.compose.material3.MaterialTheme.typography.titleLarge)
                        Text(session.user.email, color = androidx.compose.material3.MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                HorizontalDivider()
                Text("Organization", style = androidx.compose.material3.MaterialTheme.typography.titleMedium)
                session.organizations.forEach { organization ->
                    Surface(
                        onClick = { onOrganizationSelected(organization.id) },
                        color = if (organization.id == selectedOrganization?.id) {
                            androidx.compose.material3.MaterialTheme.colorScheme.secondaryContainer
                        } else androidx.compose.material3.MaterialTheme.colorScheme.surface,
                        border = androidx.compose.foundation.BorderStroke(
                            1.dp,
                            androidx.compose.material3.MaterialTheme.colorScheme.outlineVariant,
                        ),
                        shape = androidx.compose.foundation.shape.RoundedCornerShape(8.dp),
                    ) {
                        Row(
                            Modifier.fillMaxWidth().padding(14.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                        ) {
                            Text(organization.name, fontWeight = FontWeight.SemiBold)
                            Text(organization.role.replaceFirstChar(Char::uppercase))
                        }
                    }
                }
                TextButton(onClick = onRefresh, enabled = !isBusy) { Text("Refresh account") }
                TextButton(
                    onClick = { confirmSignOut = true },
                    enabled = !isBusy,
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = androidx.compose.material3.MaterialTheme.colorScheme.error,
                    ),
                ) { Text("Sign out") }
            }
        }
    }
}
