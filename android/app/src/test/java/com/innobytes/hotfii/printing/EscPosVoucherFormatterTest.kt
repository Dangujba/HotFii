package com.innobytes.hotfii.printing

import com.innobytes.hotfii.domain.VoucherShare
import com.innobytes.hotfii.domain.VoucherShareCode
import java.nio.charset.StandardCharsets
import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class EscPosVoucherFormatterTest {
    @Test
    fun `formats complete vouchers with QR commands and a cut command`() {
        val batch = VoucherShare(
            reference = "VB-260920-ABC123",
            organizationName = "BALA STARLINK",
            planName = "1 DAY",
            access = "Unlimited",
            validity = "1 calendar day, expires at midnight",
            coverage = "All routers",
            priceKobo = 50_000,
            codes = listOf(VoucherShareCode("voucher-1", "HF-000001", "1234-5678")),
        )

        val bytes = EscPosVoucherFormatter.format(batch)
        val printable = String(bytes, StandardCharsets.ISO_8859_1)

        assertArrayEquals(byteArrayOf(0x1B, 0x40), bytes.take(2).toByteArray())
        assertTrue(printable.contains("BALA STARLINK"))
        assertTrue(printable.contains("1234-5678"))
        assertTrue(printable.contains("HF-000001"))
        assertTrue(bytes.containsSequence(byteArrayOf(0x1D, 0x28, 0x6B)))
        assertTrue(bytes.containsSequence(byteArrayOf(0x1D, 0x56, 0x42, 0x00)))

        val wide = String(
            EscPosVoucherFormatter.format(batch, ThermalPaperWidth.Mm88),
            StandardCharsets.ISO_8859_1,
        )
        assertTrue(wide.contains("-".repeat(48)))
    }

    private fun ByteArray.containsSequence(sequence: ByteArray): Boolean =
        indices.any { start ->
            start + sequence.size <= size && sequence.indices.all { offset ->
                this[start + offset] == sequence[offset]
            }
        }
}
