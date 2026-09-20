package com.innobytes.hotfii.domain

data class OrganizationSettings(
    val id: String,
    val name: String,
    val timezone: String,
    val mode: String,
    val status: String,
    val portalName: String,
    val portalPrimaryColor: String,
    val livePaymentsEnabled: Boolean,
)

data class OrganizationSettingsInput(
    val name: String,
    val timezone: String,
    val portalName: String,
    val portalPrimaryColor: String,
)

data class PaymentProfile(
    val businessName: String,
    val contactName: String,
    val contactPhone: String,
    val bankName: String,
    val bankCode: String?,
    val accountName: String,
    val accountNumberHint: String?,
    val identityType: String,
    val identityNumberHint: String?,
    val status: String,
    val reviewNotes: String?,
    val submittedAt: String?,
)

data class PaymentProfileInput(
    val businessName: String,
    val contactName: String,
    val contactPhone: String,
    val bankName: String?,
    val bankCode: String?,
    val accountName: String,
    val accountNumber: String?,
    val identityType: String,
    val identityNumber: String?,
)

data class SettingsBank(val name: String, val code: String)
data class SettingsChoice(val value: String, val label: String)
data class SettingsPermissions(val canManageOrganization: Boolean, val canManagePaymentProfile: Boolean)

data class AuditRecord(
    val id: String,
    val action: String,
    val actorName: String,
    val reason: String?,
    val createdAt: String?,
)

data class OrganizationSettingsCatalog(
    val organization: OrganizationSettings,
    val paymentProfile: PaymentProfile?,
    val auditLogs: List<AuditRecord>,
    val auditPagination: NetworkPagination,
    val timezones: List<String>,
    val banks: List<SettingsBank>,
    val identityTypes: List<SettingsChoice>,
    val permissions: SettingsPermissions,
)

data class TeamMember(
    val id: String,
    val name: String,
    val email: String,
    val role: String,
    val joinedAt: String?,
    val isCurrentUser: Boolean,
)

data class TeamPermissions(
    val canAdd: Boolean,
    val canChangeRoles: Boolean,
    val assignableRoles: List<String>,
)

data class TeamCatalog(
    val members: List<TeamMember>,
    val pagination: NetworkPagination,
    val roles: List<SettingsChoice>,
    val permissions: TeamPermissions,
)

data class DeviceSession(
    val id: String,
    val name: String,
    val platform: String,
    val appVersion: String?,
    val osVersion: String?,
    val lastSeenAt: String?,
    val createdAt: String?,
    val expiresAt: String?,
    val isCurrent: Boolean,
)
