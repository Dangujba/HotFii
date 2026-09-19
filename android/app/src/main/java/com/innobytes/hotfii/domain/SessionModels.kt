package com.innobytes.hotfii.domain

data class SessionUser(
    val id: String,
    val name: String,
    val email: String,
    val phone: String?,
    val timezone: String,
)

data class OrganizationSummary(
    val id: String,
    val name: String,
    val slug: String,
    val role: String,
    val mode: String,
    val status: String,
    val currency: String,
    val timezone: String,
    val permissions: Set<String>,
)

data class UserSession(
    val user: SessionUser,
    val organizations: List<OrganizationSummary>,
    val defaultOrganizationId: String?,
)
