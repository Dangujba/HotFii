package com.innobytes.hotfii.data.network.dto

import com.innobytes.hotfii.domain.OrganizationSummary
import com.innobytes.hotfii.domain.SessionUser
import com.innobytes.hotfii.domain.UserSession

data class ApiEnvelope<T>(
    val data: T,
)

data class LoginRequestDto(
    val email: String,
    val password: String,
    val deviceName: String,
    val deviceId: String,
)

data class LoginSessionDto(
    val token: String,
    val tokenType: String,
    val expiresAt: String,
    val user: SessionUserDto,
    val organizations: List<OrganizationDto>,
    val defaultOrganizationId: String?,
) {
    fun toDomain(): UserSession = UserSession(
        user = user.toDomain(),
        organizations = organizations.map(OrganizationDto::toDomain),
        defaultOrganizationId = defaultOrganizationId,
    )
}

data class SessionDto(
    val user: SessionUserDto,
    val organizations: List<OrganizationDto>,
    val defaultOrganizationId: String?,
) {
    fun toDomain(): UserSession = UserSession(
        user = user.toDomain(),
        organizations = organizations.map(OrganizationDto::toDomain),
        defaultOrganizationId = defaultOrganizationId,
    )
}

data class SessionUserDto(
    val id: String,
    val name: String,
    val email: String,
    val phone: String?,
    val timezone: String,
) {
    fun toDomain() = SessionUser(
        id = id,
        name = name,
        email = email,
        phone = phone,
        timezone = timezone,
    )
}

data class OrganizationDto(
    val id: String,
    val name: String,
    val slug: String,
    val role: String,
    val mode: String,
    val status: String,
    val currency: String,
    val timezone: String,
    val permissions: List<String>,
) {
    fun toDomain() = OrganizationSummary(
        id = id,
        name = name,
        slug = slug,
        role = role,
        mode = mode,
        status = status,
        currency = currency,
        timezone = timezone,
        permissions = permissions.toSet(),
    )
}

data class ApiErrorDto(
    val message: String?,
    val errors: Map<String, List<String>>?,
)
