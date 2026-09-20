package com.innobytes.hotfii.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.innobytes.hotfii.R

@Composable
fun BiometricLockScreen(
    message: String?,
    onUnlock: () -> Unit,
    onSignOut: () -> Unit,
    onMessageDismissed: () -> Unit,
) {
    Column(
        Modifier.fillMaxSize().padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Image(painterResource(R.drawable.hotfii_icon), null, Modifier.size(80.dp))
        Text("HotFii is locked", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        Text(
            message ?: "Use your fingerprint or device biometric to continue.",
            textAlign = TextAlign.Center,
            color = if (message == null) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.error,
            modifier = Modifier.padding(vertical = 18.dp),
        )
        Button(onClick = {
            onMessageDismissed()
            onUnlock()
        }) {
            Icon(Icons.Outlined.Fingerprint, contentDescription = null)
            Text("Unlock", Modifier.padding(start = 8.dp))
        }
        TextButton(onClick = onSignOut) { Text("Sign out and use password") }
    }
}
