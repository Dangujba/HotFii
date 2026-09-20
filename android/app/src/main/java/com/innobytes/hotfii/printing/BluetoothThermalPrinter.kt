package com.innobytes.hotfii.printing

import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import com.innobytes.hotfii.domain.VoucherShare
import java.io.ByteArrayOutputStream
import java.text.Normalizer
import java.util.UUID
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

data class ThermalPrinterDevice(val name: String, val address: String)

enum class ThermalPaperWidth(val label: String, val columns: Int) {
    Mm58("58 mm", 32),
    Mm88("88 mm", 48),
}

class BluetoothThermalPrinter(context: Context) {
    private val adapter = context.getSystemService(BluetoothManager::class.java)?.adapter

    fun isAvailable(): Boolean = adapter != null

    @SuppressLint("MissingPermission")
    fun isEnabled(): Boolean = adapter?.isEnabled == true

    @SuppressLint("MissingPermission")
    fun pairedPrinters(): List<ThermalPrinterDevice> = adapter
        ?.bondedDevices
        ?.map { device -> ThermalPrinterDevice(device.name ?: "Bluetooth printer", device.address) }
        ?.sortedBy { it.name.lowercase() }
        .orEmpty()

    @SuppressLint("MissingPermission")
    suspend fun print(
        address: String,
        batch: VoucherShare,
        paperWidth: ThermalPaperWidth,
    ) = withContext(Dispatchers.IO) {
        val bluetoothAdapter = adapter ?: throw ThermalPrinterException("Bluetooth is not available on this device.")
        if (!bluetoothAdapter.isEnabled) {
            throw ThermalPrinterException("Turn on Bluetooth before printing.")
        }

        val device = runCatching { bluetoothAdapter.getRemoteDevice(address) }
            .getOrElse { throw ThermalPrinterException("Choose a valid paired Bluetooth printer.", it) }
        val socket = device.createRfcommSocketToServiceRecord(SPP_UUID)

        try {
            socket.connect()
            socket.outputStream.use { output ->
                output.write(EscPosVoucherFormatter.format(batch, paperWidth))
                output.flush()
            }
        } catch (error: Exception) {
            throw ThermalPrinterException(
                "HotFii could not print to ${device.name ?: "the selected printer"}. Check that it is on and paired.",
                error,
            )
        } finally {
            runCatching { socket.close() }
        }
    }

    private companion object {
        val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }
}

class ThermalPrinterException(message: String, cause: Throwable? = null) : Exception(message, cause)

object EscPosVoucherFormatter {
    fun format(
        batch: VoucherShare,
        paperWidth: ThermalPaperWidth = ThermalPaperWidth.Mm58,
    ): ByteArray = ByteArrayOutputStream().use { output ->
        output.command(0x1B, 0x40)
        batch.codes.forEach { voucher ->
            output.align(1)
            output.bold(true)
            output.text(center(batch.organizationName, paperWidth.columns))
            output.bold(false)
            output.text(center("Wi-Fi access | HotFii", paperWidth.columns))
            output.text(divider(paperWidth.columns))
            output.bold(true)
            output.text(center(batch.planName, paperWidth.columns))
            output.bold(false)
            output.text(center("VOUCHER PIN", paperWidth.columns))
            output.size(1, 1)
            output.bold(true)
            output.text(center(voucher.code, paperWidth.columns))
            output.bold(false)
            output.size(0, 0)
            output.text(center(voucher.serialNumber, paperWidth.columns))
            output.feed(1)
            output.qr(voucher.code)
            output.feed(1)
            output.align(0)
            output.text(detail("Access", batch.access, paperWidth.columns))
            output.text(detail("Validity", batch.validity, paperWidth.columns))
            output.text(detail("Value", price(batch.priceKobo), paperWidth.columns))
            output.text(detail("Valid on", batch.coverage, paperWidth.columns))
            output.text(divider(paperWidth.columns))
            output.align(1)
            output.text(center("Scan QR or enter the PIN", paperWidth.columns))
            output.text(center("Validity starts on first use", paperWidth.columns))
            output.text(center(batch.reference, paperWidth.columns))
            output.feed(4)
            output.command(0x1D, 0x56, 0x42, 0x00)
        }
        output.toByteArray()
    }

    private fun ByteArrayOutputStream.command(vararg values: Int) {
        values.forEach(::write)
    }

    private fun ByteArrayOutputStream.text(value: String) {
        write(ascii(value).toByteArray(Charsets.US_ASCII))
        write('\n'.code)
    }

    private fun ByteArrayOutputStream.align(value: Int) = command(0x1B, 0x61, value)
    private fun ByteArrayOutputStream.bold(enabled: Boolean) = command(0x1B, 0x45, if (enabled) 1 else 0)
    private fun ByteArrayOutputStream.size(width: Int, height: Int) = command(0x1D, 0x21, (width shl 4) or height)
    private fun ByteArrayOutputStream.feed(lines: Int) = command(0x1B, 0x64, lines)

    private fun ByteArrayOutputStream.qr(value: String) {
        val data = ascii(value).toByteArray(Charsets.US_ASCII)
        command(0x1D, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00)
        command(0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, 0x06)
        command(0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x31)
        val length = data.size + 3
        command(0x1D, 0x28, 0x6B, length and 0xFF, (length shr 8) and 0xFF, 0x31, 0x50, 0x30)
        write(data)
        command(0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30)
    }

    private fun center(value: String, columns: Int): String {
        val clean = ascii(value).take(columns)
        return clean.padStart(clean.length + ((columns - clean.length) / 2))
    }

    private fun detail(label: String, value: String, columns: Int): String {
        val cleanLabel = ascii(label).take(10)
        val cleanValue = ascii(value)
        val available = (columns - cleanLabel.length - 1).coerceAtLeast(1)
        return "$cleanLabel ${cleanValue.take(available)}"
    }

    private fun divider(columns: Int): String = "-".repeat(columns)

    private fun price(kobo: Long): String = if (kobo % 100 == 0L) {
        "NGN ${kobo / 100}"
    } else {
        "NGN %.2f".format(java.util.Locale.US, kobo / 100.0)
    }

    private fun ascii(value: String): String = Normalizer.normalize(value, Normalizer.Form.NFD)
        .replace(Regex("\\p{M}+"), "")
        .map { character -> if (character.code in 32..126) character else ' ' }
        .joinToString("")
        .replace(Regex("\\s+"), " ")
        .trim()
}
