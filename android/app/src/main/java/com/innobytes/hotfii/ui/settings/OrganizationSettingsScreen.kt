package com.innobytes.hotfii.ui.settings

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Business
import androidx.compose.material.icons.outlined.ChevronRight
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Devices
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.History
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.domain.OrganizationSettingsInput
import com.innobytes.hotfii.domain.OrganizationSettingsCatalog
import com.innobytes.hotfii.domain.PaymentProfileInput
import com.innobytes.hotfii.domain.SettingsChoice
import com.innobytes.hotfii.domain.TeamMember
import com.innobytes.hotfii.ui.SettingsUiState
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

private enum class SettingsSection(val title: String) {
    Hub("Settings"),
    Organization("Organization"),
    Payment("Payment profile"),
    Team("Team & roles"),
    Audit("Audit history"),
    Devices("Device sessions"),
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrganizationSettingsScreen(
    organizationId: String?,
    organizationName: String?,
    state: SettingsUiState,
    onBack: () -> Unit,
    onLoad: () -> Unit,
    onLoadAuditPage: (Int) -> Unit,
    onLoadTeamPage: (Int) -> Unit,
    onUpdateOrganization: (OrganizationSettingsInput) -> Unit,
    onSubmitPaymentProfile: (PaymentProfileInput) -> Unit,
    onAddTeamMember: (String, String) -> Unit,
    onUpdateTeamRole: (String, String) -> Unit,
    onRevokeDeviceSession: (String) -> Unit,
    onFeedbackDismissed: () -> Unit,
) {
    var sectionName by rememberSaveable { mutableStateOf(SettingsSection.Hub.name) }
    val section = SettingsSection.valueOf(sectionName)

    LaunchedEffect(organizationId) {
        if (organizationId != null) {
            sectionName = SettingsSection.Hub.name
            onLoad()
        }
    }
    BackHandler {
        if (section == SettingsSection.Hub) onBack() else sectionName = SettingsSection.Hub.name
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(section.title, fontWeight = FontWeight.Bold)
                        organizationName?.let { Text(it, style = MaterialTheme.typography.labelMedium) }
                    }
                },
                navigationIcon = {
                    IconButton(onClick = {
                        if (section == SettingsSection.Hub) onBack() else sectionName = SettingsSection.Hub.name
                    }) {
                        Icon(Icons.Outlined.ArrowBack, contentDescription = "Back")
                    }
                },
            )
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.TopCenter) {
            Column(Modifier.fillMaxWidth().widthIn(max = 720.dp)) {
                if (state.isLoading || state.isActionRunning) {
                    LinearProgressIndicator(Modifier.fillMaxWidth())
                }
                state.error?.let { SettingsMessage(it, true, onFeedbackDismissed) }
                state.notice?.let { SettingsMessage(it, false, onFeedbackDismissed) }
                when (section) {
                    SettingsSection.Hub -> SettingsHub(
                        state = state,
                        onOpen = { sectionName = it.name },
                    )
                    SettingsSection.Organization -> OrganizationForm(state, onUpdateOrganization)
                    SettingsSection.Payment -> PaymentForm(state, onSubmitPaymentProfile)
                    SettingsSection.Team -> TeamList(state, onLoadTeamPage, onAddTeamMember, onUpdateTeamRole)
                    SettingsSection.Audit -> AuditList(state, onLoadAuditPage)
                    SettingsSection.Devices -> DeviceSessionList(state, onRevokeDeviceSession)
                }
            }
        }
    }
}

@Composable
private fun SettingsHub(state: SettingsUiState, onOpen: (SettingsSection) -> Unit) {
    val catalog = state.catalog
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        item {
            SettingsRow(
                "Organization",
                catalog?.let { "${it.organization.name} · ${it.organization.timezone}" } ?: "Profile and captive portal branding",
                Icons.Outlined.Business,
            ) { onOpen(SettingsSection.Organization) }
        }
        if (catalog?.permissions?.canManagePaymentProfile == true) {
            item {
                SettingsRow(
                    "Payment profile",
                    catalog.paymentProfile?.status?.replaceFirstChar(Char::uppercase) ?: "Settlement and identity details",
                    Icons.Outlined.Payments,
                ) { onOpen(SettingsSection.Payment) }
            }
        }
        item {
            SettingsRow(
                "Team & roles",
                state.team?.let { "${it.pagination.total} members" } ?: "Members and permissions",
                Icons.Outlined.Group,
            ) { onOpen(SettingsSection.Team) }
        }
        item {
            SettingsRow(
                "Audit history",
                catalog?.let { "${it.auditPagination.total} recorded actions" } ?: "Organization activity",
                Icons.Outlined.History,
            ) { onOpen(SettingsSection.Audit) }
        }
        item {
            SettingsRow(
                "Device sessions",
                "${state.deviceSessions.size} Android sessions",
                Icons.Outlined.Devices,
            ) { onOpen(SettingsSection.Devices) }
        }
    }
}

@Composable
private fun SettingsRow(title: String, subtitle: String, icon: ImageVector, onClick: () -> Unit) {
    ListItem(
        headlineContent = { Text(title, fontWeight = FontWeight.SemiBold) },
        supportingContent = { Text(subtitle) },
        leadingContent = { Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
        trailingContent = { Icon(Icons.Outlined.ChevronRight, contentDescription = null) },
        modifier = Modifier.clickable(onClick = onClick),
    )
    HorizontalDivider(Modifier.padding(start = 56.dp))
}

@Composable
private fun OrganizationForm(
    state: SettingsUiState,
    onSave: (OrganizationSettingsInput) -> Unit,
) {
    val organization = state.catalog?.organization ?: return
    val editable = state.catalog.permissions.canManageOrganization
    var name by remember(organization) { mutableStateOf(organization.name) }
    var timezone by remember(organization) { mutableStateOf(organization.timezone) }
    var portalName by remember(organization) { mutableStateOf(organization.portalName) }
    var portalColor by remember(organization) { mutableStateOf(organization.portalPrimaryColor) }
    var chooseTimezone by remember { mutableStateOf(false) }

    if (chooseTimezone) {
        ChoiceDialog(
            title = "Choose timezone",
            choices = state.catalog.optionsTimezones(),
            selected = timezone,
            onSelect = {
                timezone = it
                chooseTimezone = false
            },
            onDismiss = { chooseTimezone = false },
        )
    }
    LazyColumn(
        Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 20.dp, vertical = 18.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item { SectionText("Organization profile") }
        item { SettingsField(name, { name = it }, "Organization name", editable) }
        item {
            SettingsField(timezone, { timezone = it }, "Timezone", editable)
            if (editable) TextButton(onClick = { chooseTimezone = true }) { Text("Choose timezone") }
        }
        item { SectionText("Captive portal") }
        item { SettingsField(portalName, { portalName = it }, "Portal display name", editable) }
        item { SettingsField(portalColor, { portalColor = it }, "Brand colour (#RRGGBB)", editable) }
        item {
            if (editable) {
                Button(
                    onClick = { onSave(OrganizationSettingsInput(name, timezone, portalName, portalColor)) },
                    enabled = !state.isActionRunning,
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Save changes") }
            } else {
                Text("Your role has read-only access to these settings.", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun PaymentForm(state: SettingsUiState, onSubmit: (PaymentProfileInput) -> Unit) {
    val catalog = state.catalog ?: return
    val profile = catalog.paymentProfile
    var businessName by remember(profile) { mutableStateOf(profile?.businessName.orEmpty()) }
    var contactName by remember(profile) { mutableStateOf(profile?.contactName.orEmpty()) }
    var contactPhone by remember(profile) { mutableStateOf(profile?.contactPhone.orEmpty()) }
    var bankName by remember(profile) { mutableStateOf(profile?.bankName.orEmpty()) }
    var bankCode by remember(profile) { mutableStateOf(profile?.bankCode) }
    var accountName by remember(profile) { mutableStateOf(profile?.accountName.orEmpty()) }
    var accountNumber by remember(profile) { mutableStateOf("") }
    var identityType by remember(profile) { mutableStateOf(profile?.identityType ?: "nin") }
    var identityNumber by remember(profile) { mutableStateOf("") }
    var chooseBank by remember { mutableStateOf(false) }
    var chooseIdentity by remember { mutableStateOf(false) }

    if (chooseBank) {
        ChoiceDialog(
            "Choose settlement bank",
            catalog.banks.map { SettingsChoice(it.code, it.name) },
            bankCode,
            onSelect = { selected ->
                val bank = catalog.banks.first { it.code == selected }
                bankCode = bank.code
                bankName = bank.name
                chooseBank = false
            },
            onDismiss = { chooseBank = false },
        )
    }
    if (chooseIdentity) {
        ChoiceDialog(
            "Choose identity type",
            catalog.identityTypes,
            identityType,
            onSelect = {
                identityType = it
                chooseIdentity = false
            },
            onDismiss = { chooseIdentity = false },
        )
    }

    LazyColumn(
        Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 20.dp, vertical = 18.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        profile?.let {
            item {
                Text(
                    "Status: ${it.status.replaceFirstChar(Char::uppercase)}",
                    color = if (it.status == "approved") MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                    fontWeight = FontWeight.SemiBold,
                )
            }
            it.reviewNotes?.let { note -> item { Text(note, color = MaterialTheme.colorScheme.onSurfaceVariant) } }
        }
        item { SettingsField(businessName, { businessName = it }, "Business name", true) }
        item { SettingsField(contactName, { contactName = it }, "Contact name", true) }
        item {
            OutlinedTextField(
                value = contactPhone,
                onValueChange = { contactPhone = it },
                label = { Text("Contact phone") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        item {
            if (catalog.banks.isEmpty()) {
                SettingsField(bankName, { bankName = it }, "Settlement bank", true)
            } else {
                SettingsField(bankName, {}, "Settlement bank", false)
                TextButton(onClick = { chooseBank = true }) { Text("Choose bank") }
            }
        }
        item { SettingsField(accountName, { accountName = it }, "Account name", true) }
        item {
            OutlinedTextField(
                value = accountNumber,
                onValueChange = { accountNumber = it.filter(Char::isDigit) },
                label = { Text(profile?.accountNumberHint?.let { "Account number ($it on file)" } ?: "Account number") },
                supportingText = if (profile != null) ({ Text("Leave blank to keep the saved account number.") }) else null,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        item {
            SettingsField(
                catalog.identityTypes.firstOrNull { it.value == identityType }?.label ?: identityType.uppercase(),
                {},
                "Identity type",
                false,
            )
            TextButton(onClick = { chooseIdentity = true }) { Text("Choose identity type") }
        }
        item {
            OutlinedTextField(
                value = identityNumber,
                onValueChange = { identityNumber = it },
                label = { Text(profile?.identityNumberHint?.let { "Identity number ($it on file)" } ?: "Identity number") },
                supportingText = if (profile != null) ({ Text("Leave blank to keep the saved identity number.") }) else null,
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        item {
            Button(
                onClick = {
                    onSubmit(
                        PaymentProfileInput(
                            businessName,
                            contactName,
                            contactPhone,
                            bankName.ifBlank { null },
                            bankCode,
                            accountName,
                            accountNumber.ifBlank { null },
                            identityType,
                            identityNumber.ifBlank { null },
                        ),
                    )
                },
                enabled = !state.isActionRunning,
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (profile == null) "Submit payment details" else "Resubmit payment details") }
        }
    }
}

@Composable
private fun TeamList(
    state: SettingsUiState,
    onPage: (Int) -> Unit,
    onAdd: (String, String) -> Unit,
    onUpdateRole: (String, String) -> Unit,
) {
    val team = state.team ?: return
    var showAdd by remember { mutableStateOf(false) }
    var editingMember by remember { mutableStateOf<TeamMember?>(null) }

    if (showAdd) {
        AddMemberDialog(
            roles = team.roles.filter { it.value in team.permissions.assignableRoles },
            busy = state.isActionRunning,
            onAdd = { email, role ->
                onAdd(email, role)
                showAdd = false
            },
            onDismiss = { showAdd = false },
        )
    }
    editingMember?.let { member ->
        ChoiceDialog(
            title = "Change ${member.name}'s role",
            choices = team.roles,
            selected = member.role,
            onSelect = {
                onUpdateRole(member.id, it)
                editingMember = null
            },
            onDismiss = { editingMember = null },
        )
    }

    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (team.permissions.canAdd) {
            item {
                ListItem(
                    headlineContent = { Text("Add team member", fontWeight = FontWeight.SemiBold) },
                    leadingContent = { Icon(Icons.Outlined.Add, contentDescription = null, tint = MaterialTheme.colorScheme.primary) },
                    modifier = Modifier.clickable(enabled = !state.isActionRunning) { showAdd = true },
                )
                HorizontalDivider()
            }
        }
        items(team.members, key = { it.id }) { member ->
            ListItem(
                headlineContent = {
                    Text(member.name + if (member.isCurrentUser) " (you)" else "", fontWeight = FontWeight.SemiBold)
                },
                supportingContent = { Text("${member.email}\n${member.role.replaceFirstChar(Char::uppercase)}") },
                trailingContent = {
                    if (team.permissions.canChangeRoles) {
                        IconButton(onClick = { editingMember = member }, enabled = !state.isActionRunning) {
                            Icon(Icons.Outlined.Edit, contentDescription = "Change role")
                        }
                    }
                },
            )
            HorizontalDivider(Modifier.padding(start = 20.dp))
        }
        item { PaginationRow(team.pagination.currentPage, team.pagination.lastPage, onPage) }
    }
}

@Composable
private fun AuditList(state: SettingsUiState, onPage: (Int) -> Unit) {
    val catalog = state.catalog ?: return
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (catalog.auditLogs.isEmpty()) {
            item { EmptyText("No organization actions have been recorded yet.") }
        }
        items(catalog.auditLogs, key = { it.id }) { record ->
            ListItem(
                headlineContent = {
                    Text(record.action.replace('.', ' ').replace('-', ' ').replaceFirstChar(Char::uppercase), fontWeight = FontWeight.SemiBold)
                },
                supportingContent = {
                    Text(listOfNotNull(record.actorName, friendlyDate(record.createdAt), record.reason).joinToString(" · "))
                },
            )
            HorizontalDivider(Modifier.padding(start = 20.dp))
        }
        item { PaginationRow(catalog.auditPagination.currentPage, catalog.auditPagination.lastPage, onPage) }
    }
}

@Composable
private fun DeviceSessionList(state: SettingsUiState, onRevoke: (String) -> Unit) {
    var selected by remember { mutableStateOf<com.innobytes.hotfii.domain.DeviceSession?>(null) }
    selected?.let { session ->
        AlertDialog(
            onDismissRequest = { selected = null },
            title = { Text("Revoke this device?") },
            text = { Text("${session.name} will need to sign in again.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        onRevoke(session.id)
                        selected = null
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = MaterialTheme.colorScheme.error),
                ) { Text("Revoke") }
            },
            dismissButton = { TextButton(onClick = { selected = null }) { Text("Cancel") } },
        )
    }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(bottom = 28.dp)) {
        if (state.deviceSessions.isEmpty()) item { EmptyText("No Android device sessions were found.") }
        items(state.deviceSessions, key = { it.id }) { session ->
            ListItem(
                headlineContent = {
                    Text(session.name + if (session.isCurrent) " (this device)" else "", fontWeight = FontWeight.SemiBold)
                },
                supportingContent = {
                    Text(listOfNotNull(
                        session.osVersion?.let { "Android $it" },
                        session.appVersion?.let { "HotFii $it" },
                        friendlyDate(session.lastSeenAt)?.let { "Last active $it" },
                    ).joinToString(" · "))
                },
                trailingContent = {
                    if (!session.isCurrent) {
                        TextButton(onClick = { selected = session }, enabled = !state.isActionRunning) { Text("Revoke") }
                    }
                },
            )
            HorizontalDivider(Modifier.padding(start = 20.dp))
        }
    }
}

@Composable
private fun AddMemberDialog(
    roles: List<SettingsChoice>,
    busy: Boolean,
    onAdd: (String, String) -> Unit,
    onDismiss: () -> Unit,
) {
    var email by remember { mutableStateOf("") }
    var role by remember { mutableStateOf(roles.firstOrNull()?.value.orEmpty()) }
    var chooseRole by remember { mutableStateOf(false) }
    if (chooseRole) {
        ChoiceDialog(
            "Choose role",
            roles,
            role,
            onSelect = {
                role = it
                chooseRole = false
            },
            onDismiss = { chooseRole = false },
        )
    }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Add team member") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(
                    value = email,
                    onValueChange = { email = it },
                    label = { Text("Existing HotFii account email") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                SettingsField(
                    roles.firstOrNull { it.value == role }?.label.orEmpty(),
                    {},
                    "Role",
                    false,
                )
                TextButton(onClick = { chooseRole = true }) { Text("Choose role") }
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onAdd(email.trim(), role) },
                enabled = !busy && email.isNotBlank() && role.isNotBlank(),
            ) { Text("Add") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@Composable
private fun ChoiceDialog(
    title: String,
    choices: List<SettingsChoice>,
    selected: String?,
    onSelect: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            LazyColumn(Modifier.fillMaxWidth().widthIn(max = 520.dp)) {
                items(choices, key = { it.value }) { choice ->
                    ListItem(
                        headlineContent = { Text(choice.label) },
                        trailingContent = {
                            if (choice.value == selected) Text("Selected", color = MaterialTheme.colorScheme.primary)
                        },
                        modifier = Modifier.clickable { onSelect(choice.value) },
                    )
                    HorizontalDivider()
                }
            }
        },
        confirmButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@Composable
private fun SettingsField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    enabled: Boolean,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        enabled = enabled,
        singleLine = true,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun SectionText(text: String) {
    Text(text, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
}

@Composable
private fun PaginationRow(current: Int, last: Int, onPage: (Int) -> Unit) {
    if (last <= 1) return
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 14.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        TextButton(onClick = { onPage(current - 1) }, enabled = current > 1) { Text("Previous") }
        Text("$current of $last")
        TextButton(onClick = { onPage(current + 1) }, enabled = current < last) { Text("Next") }
    }
}

@Composable
private fun EmptyText(text: String) {
    Text(
        text,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier.fillMaxWidth().padding(20.dp),
    )
}

@Composable
private fun SettingsMessage(message: String, isError: Boolean, onDismiss: () -> Unit) {
    Surface(
        color = if (isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer,
        contentColor = if (isError) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onSecondaryContainer,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(start = 16.dp, top = 8.dp, bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(message, modifier = Modifier.weight(1f))
            IconButton(onClick = onDismiss) { Icon(Icons.Outlined.Close, contentDescription = "Dismiss") }
        }
    }
}

private fun OrganizationSettingsCatalog.optionsTimezones(): List<SettingsChoice> =
    timezones.map { SettingsChoice(it, it.replace('_', ' ')) }

private fun friendlyDate(value: String?): String? {
    if (value == null) return null
    return runCatching {
        OffsetDateTime.parse(value).format(DateTimeFormatter.ofPattern("d MMM yyyy, h:mm a"))
    }.getOrDefault(value)
}
