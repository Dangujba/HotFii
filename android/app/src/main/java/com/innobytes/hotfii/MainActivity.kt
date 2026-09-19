package com.innobytes.hotfii

import android.os.Bundle
import android.os.Process
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import com.innobytes.hotfii.ui.HotFiiApp
import com.innobytes.hotfii.ui.MainViewModel
import com.innobytes.hotfii.ui.theme.HotFiiTheme

class MainActivity : ComponentActivity() {
    private val viewModel: MainViewModel by viewModels {
        val application = application as HotFiiApplication
        MainViewModel.factory(
            application.container.sessionRepository,
            application.container.dashboardRepository,
            application.container.voucherRepository,
        )
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            HotFiiTheme {
                HotFiiApp(
                    viewModel = viewModel,
                    onCloseApp = ::closeApp,
                )
            }
        }
    }

    private fun closeApp() {
        finishAndRemoveTask()
        Process.killProcess(Process.myPid())
    }
}
