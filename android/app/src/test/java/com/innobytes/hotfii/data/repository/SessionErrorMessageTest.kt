package com.innobytes.hotfii.data.repository

import com.google.gson.FieldNamingPolicy
import com.google.gson.GsonBuilder
import org.junit.Assert.assertEquals
import org.junit.Test

class SessionErrorMessageTest {
    private val gson = GsonBuilder()
        .setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
        .create()

    @Test
    fun `uses the first validation error returned by HotFii`() {
        val message = sessionApiErrorMessage(
            gson = gson,
            statusCode = 422,
            responseBody = """{"message":"Validation failed.","errors":{"email":["The supplied credentials are incorrect."]}}""",
            fallback = "Sign in failed.",
        )

        assertEquals("The supplied credentials are incorrect.", message)
    }

    @Test
    fun `uses the API message when no field error exists`() {
        val message = sessionApiErrorMessage(
            gson = gson,
            statusCode = 403,
            responseBody = """{"message":"This account is suspended."}""",
            fallback = "Sign in failed.",
        )

        assertEquals("This account is suspended.", message)
    }

    @Test
    fun `uses a specific status message when the body is not JSON`() {
        val message = sessionApiErrorMessage(
            gson = gson,
            statusCode = 429,
            responseBody = "Too many requests",
            fallback = "Sign in failed.",
        )

        assertEquals("Too many attempts. Wait a moment and try again.", message)
    }
}
