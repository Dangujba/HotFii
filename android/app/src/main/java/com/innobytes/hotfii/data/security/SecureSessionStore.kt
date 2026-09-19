package com.innobytes.hotfii.data.security

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import java.util.UUID
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class SecureSessionStore(context: Context) {
    private val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)

    @Synchronized
    fun saveToken(token: String) {
        val cipher = Cipher.getInstance(TRANSFORMATION).apply {
            init(Cipher.ENCRYPT_MODE, secretKey())
        }
        val encrypted = cipher.doFinal(token.toByteArray(Charsets.UTF_8))

        preferences.edit()
            .putString(KEY_TOKEN, Base64.encodeToString(encrypted, Base64.NO_WRAP))
            .putString(KEY_TOKEN_IV, Base64.encodeToString(cipher.iv, Base64.NO_WRAP))
            .apply()
    }

    @Synchronized
    fun readToken(): String? {
        val encrypted = preferences.getString(KEY_TOKEN, null) ?: return null
        val iv = preferences.getString(KEY_TOKEN_IV, null) ?: return null

        return runCatching {
            val cipher = Cipher.getInstance(TRANSFORMATION).apply {
                init(
                    Cipher.DECRYPT_MODE,
                    secretKey(),
                    GCMParameterSpec(128, Base64.decode(iv, Base64.NO_WRAP)),
                )
            }
            val clear = cipher.doFinal(Base64.decode(encrypted, Base64.NO_WRAP))
            clear.toString(Charsets.UTF_8)
        }.getOrElse {
            clearToken()
            null
        }
    }

    @Synchronized
    fun clearToken() {
        preferences.edit()
            .remove(KEY_TOKEN)
            .remove(KEY_TOKEN_IV)
            .remove(KEY_SELECTED_ORGANIZATION)
            .apply()
    }

    fun deviceId(): String {
        preferences.getString(KEY_DEVICE_ID, null)?.let { return it }
        return UUID.randomUUID().toString().also {
            preferences.edit().putString(KEY_DEVICE_ID, it).apply()
        }
    }

    fun selectedOrganizationId(): String? =
        preferences.getString(KEY_SELECTED_ORGANIZATION, null)

    fun selectOrganization(id: String) {
        preferences.edit().putString(KEY_SELECTED_ORGANIZATION, id).apply()
    }

    private fun secretKey(): SecretKey {
        val keyStore = KeyStore.getInstance(ANDROID_KEY_STORE).apply { load(null) }
        (keyStore.getKey(KEY_ALIAS, null) as? SecretKey)?.let { return it }

        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, ANDROID_KEY_STORE).run {
            init(
                KeyGenParameterSpec.Builder(
                    KEY_ALIAS,
                    KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
                )
                    .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                    .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                    .build(),
            )
            generateKey()
        }
    }

    private companion object {
        const val PREFERENCES_NAME = "hotfii_secure_session"
        const val KEY_TOKEN = "access_token"
        const val KEY_TOKEN_IV = "access_token_iv"
        const val KEY_DEVICE_ID = "device_id"
        const val KEY_SELECTED_ORGANIZATION = "selected_organization"
        const val KEY_ALIAS = "hotfii_mobile_session"
        const val ANDROID_KEY_STORE = "AndroidKeyStore"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
    }
}
