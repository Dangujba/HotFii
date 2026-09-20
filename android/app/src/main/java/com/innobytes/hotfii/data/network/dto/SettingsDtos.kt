package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.AuditRecord
import com.innobytes.hotfii.domain.DeviceSession
import com.innobytes.hotfii.domain.OrganizationSettings
import com.innobytes.hotfii.domain.OrganizationSettingsCatalog
import com.innobytes.hotfii.domain.OrganizationSettingsInput
import com.innobytes.hotfii.domain.PaymentProfile
import com.innobytes.hotfii.domain.PaymentProfileInput
import com.innobytes.hotfii.domain.SettingsBank
import com.innobytes.hotfii.domain.SettingsChoice
import com.innobytes.hotfii.domain.SettingsPermissions
import com.innobytes.hotfii.domain.TeamCatalog
import com.innobytes.hotfii.domain.TeamMember
import com.innobytes.hotfii.domain.TeamPermissions

data class OrganizationSettingsCatalogDto(
    val organization: OrganizationSettingsDto,
    val paymentProfile: PaymentProfileDto?,
    val auditLogs: List<AuditRecordDto>,
    val auditPagination: NetworkPaginationDto,
    val options: OrganizationSettingsOptionsDto,
    val permissions: SettingsPermissionsDto,
) {
    fun toDomain() = OrganizationSettingsCatalog(
        organization.toDomain(),
        paymentProfile?.toDomain(),
        auditLogs.map(AuditRecordDto::toDomain),
        auditPagination.toDomain(),
        options.timezones,
        options.banks.map(SettingsBankDto::toDomain),
        options.identityTypes.map(SettingsChoiceDto::toDomain),
        permissions.toDomain(),
    )
}

data class OrganizationSettingsDto(
    val id: String,
    val name: String,
    val timezone: String,
    val mode: String,
    val status: String,
    val portalName: String,
    val portalPrimaryColor: String,
    val livePaymentsEnabled: Boolean,
) {
    fun toDomain() = OrganizationSettings(id, name, timezone, mode, status, portalName, portalPrimaryColor, livePaymentsEnabled)
}

data class OrganizationSettingsRequestDto(
    val name: String,
    val timezone: String,
    val portalName: String,
    val portalPrimaryColor: String,
) {
    companion object {
        fun from(input: OrganizationSettingsInput) = OrganizationSettingsRequestDto(
            input.name,
            input.timezone,
            input.portalName,
            input.portalPrimaryColor,
        )
    }
}

data class OrganizationSettingsUpdateDto(val organization: OrganizationSettingsDto)

data class PaymentProfileDto(
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
) {
    fun toDomain() = PaymentProfile(
        businessName,
        contactName,
        contactPhone,
        bankName,
        bankCode,
        accountName,
        accountNumberHint,
        identityType,
        identityNumberHint,
        status,
        reviewNotes,
        submittedAt,
    )
}

data class PaymentProfileRequestDto(
    val businessName: String,
    val contactName: String,
    val contactPhone: String,
    val bankName: String?,
    val bankCode: String?,
    val accountName: String,
    val accountNumber: String?,
    val identityType: String,
    val identityNumber: String?,
) {
    companion object {
        fun from(input: PaymentProfileInput) = PaymentProfileRequestDto(
            input.businessName,
            input.contactName,
            input.contactPhone,
            input.bankName,
            input.bankCode,
            input.accountName,
            input.accountNumber,
            input.identityType,
            input.identityNumber,
        )
    }
}

data class PaymentProfileUpdateDto(val paymentProfile: PaymentProfileDto)
data class AuditRecordDto(val id: String, val action: String, val actorName: String, val reason: String?, val createdAt: String?) {
    fun toDomain() = AuditRecord(id, action, actorName, reason, createdAt)
}
data class OrganizationSettingsOptionsDto(
    val timezones: List<String>,
    val banks: List<SettingsBankDto>,
    val identityTypes: List<SettingsChoiceDto>,
)
data class SettingsBankDto(val name: String, val code: String, val slug: String? = null) {
    fun toDomain() = SettingsBank(name, code)
}
data class SettingsChoiceDto(val value: String, val label: String) {
    fun toDomain() = SettingsChoice(value, label)
}
data class SettingsPermissionsDto(val canManageOrganization: Boolean, val canManagePaymentProfile: Boolean) {
    fun toDomain() = SettingsPermissions(canManageOrganization, canManagePaymentProfile)
}

data class TeamCatalogDto(
    val members: List<TeamMemberDto>,
    val pagination: NetworkPaginationDto,
    val roles: List<SettingsChoiceDto>,
    val permissions: TeamPermissionsDto,
) {
    fun toDomain() = TeamCatalog(
        members.map(TeamMemberDto::toDomain),
        pagination.toDomain(),
        roles.map(SettingsChoiceDto::toDomain),
        permissions.toDomain(),
    )
}
data class TeamMemberDto(
    val id: String,
    val name: String,
    val email: String,
    val role: String,
    val joinedAt: String?,
    val isCurrentUser: Boolean,
) {
    fun toDomain() = TeamMember(id, name, email, role, joinedAt, isCurrentUser)
}
data class TeamPermissionsDto(val canAdd: Boolean, val canChangeRoles: Boolean, val assignableRoles: List<String>) {
    fun toDomain() = TeamPermissions(canAdd, canChangeRoles, assignableRoles)
}
data class TeamMemberRequestDto(val email: String, val role: String)
data class TeamRoleRequestDto(val role: String)
data class TeamMemberResponseDto(val member: TeamMemberDto)

data class DeviceSessionCatalogDto(val sessions: List<DeviceSessionDto>) {
    fun toDomain() = sessions.map(DeviceSessionDto::toDomain)
}
data class DeviceSessionDto(
    val id: String,
    val name: String,
    val platform: String,
    val appVersion: String?,
    val osVersion: String?,
    val lastSeenAt: String?,
    val createdAt: String?,
    val expiresAt: String?,
    val isCurrent: Boolean,
) {
    fun toDomain() = DeviceSession(id, name, platform, appVersion, osVersion, lastSeenAt, createdAt, expiresAt, isCurrent)
}
