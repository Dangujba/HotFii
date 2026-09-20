package com.innobytes.hotfii.ui.auth

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class LoginValidationTest {
    @Test
    fun `requires both login fields`() {
        val errors = validateLoginInputs("", "")

        assertEquals("Email address is required.", errors.email)
        assertEquals("Password is required.", errors.password)
        assertTrue(errors.hasErrors)
    }

    @Test
    fun `rejects a malformed email address`() {
        val errors = validateLoginInputs("not-an-email", "password")

        assertEquals("Enter a valid email address.", errors.email)
        assertNull(errors.password)
        assertTrue(errors.hasErrors)
    }

    @Test
    fun `accepts populated valid credentials`() {
        val errors = validateLoginInputs("owner@example.com", "password")

        assertNull(errors.email)
        assertNull(errors.password)
        assertFalse(errors.hasErrors)
    }
}
