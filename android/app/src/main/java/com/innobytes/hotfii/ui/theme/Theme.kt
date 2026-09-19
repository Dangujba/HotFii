package com.innobytes.hotfii.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val LightColors = lightColorScheme(
    primary = HotFiiOrange,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFFFF0E8),
    onPrimaryContainer = HotFiiInk,
    secondary = HotFiiAmber,
    onSecondary = HotFiiInk,
    secondaryContainer = Color(0xFFFEF9E3),
    onSecondaryContainer = HotFiiInk,
    background = HotFiiWarmWhite,
    onBackground = HotFiiInk,
    surface = HotFiiSurface,
    onSurface = HotFiiInk,
    surfaceVariant = Color(0xFFF5F0EB),
    onSurfaceVariant = HotFiiMuted,
    error = HotFiiError,
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFFF46F2A),
    onPrimary = Color(0xFF1A0E08),
    secondary = HotFiiAmber,
    onSecondary = Color(0xFF1A1410),
    secondaryContainer = Color(0xFF353029),
    onSecondaryContainer = Color(0xFFF4B942),
    background = Color(0xFF231E1B),
    onBackground = Color(0xFFD8CFCA),
    surface = Color(0xFF2D2622),
    onSurface = Color(0xFFD8CFCA),
    surfaceVariant = Color(0xFF353029),
    onSurfaceVariant = Color(0xFF8A7F78),
)

@Composable
fun HotFiiTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    val colors = if (darkTheme) DarkColors else LightColors

    MaterialTheme(
        colorScheme = colors,
        typography = HotFiiTypography,
        content = content,
    )
}
