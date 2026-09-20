package com.innobytes.hotfii.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import com.innobytes.hotfii.data.preferences.AppThemeMode

private val LightColors = lightColorScheme(
    primary = HotFiiOrange,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFFFEBDD),
    onPrimaryContainer = HotFiiInk,
    secondary = Color(0xFF087FAF),
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFDDF3FC),
    onSecondaryContainer = Color(0xFF083B50),
    background = Color(0xFFFAFAFA),
    onBackground = HotFiiInk,
    surface = HotFiiSurface,
    onSurface = HotFiiInk,
    surfaceVariant = Color(0xFFF0F2F4),
    onSurfaceVariant = Color(0xFF5E646B),
    error = HotFiiError,
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFFF46F2A),
    onPrimary = Color(0xFF1B0D05),
    primaryContainer = Color(0xFF5A260B),
    onPrimaryContainer = Color(0xFFFFDBC9),
    secondary = Color(0xFF43B4E5),
    onSecondary = Color(0xFF062B3A),
    secondaryContainer = Color(0xFF123E50),
    onSecondaryContainer = Color(0xFFBDEBFF),
    background = Color(0xFF171717),
    onBackground = Color(0xFFF0F1F2),
    surface = Color(0xFF212121),
    onSurface = Color(0xFFF0F1F2),
    surfaceVariant = Color(0xFF303236),
    onSurfaceVariant = Color(0xFFB7BCC2),
)

@Composable
fun HotFiiTheme(
    themeMode: AppThemeMode = AppThemeMode.System,
    content: @Composable () -> Unit,
) {
    val darkTheme = when (themeMode) {
        AppThemeMode.System -> isSystemInDarkTheme()
        AppThemeMode.Light -> false
        AppThemeMode.Dark -> true
    }
    val colors = if (darkTheme) DarkColors else LightColors

    MaterialTheme(
        colorScheme = colors,
        typography = HotFiiTypography,
        content = content,
    )
}
