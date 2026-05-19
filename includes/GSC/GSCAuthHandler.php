<?php

namespace AiSeoAssistant\GSC;

use AiSeoAssistant\Support\Logger;

class GSCAuthHandler
{
    private const TOKEN_OPTION = 'aisa_gsc_token';
    private const PROPERTY_OPTION = 'aisa_gsc_property';
    private const CLIENT_ID_OPTION = 'aisa_gsc_client_id';
    private const CLIENT_SECRET_OPTION = 'aisa_gsc_client_secret';
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public static function getRedirectUri(): string
    {
        return admin_url('admin.php?page=ai-seo-assistant&gsc_callback=1');
    }

    public static function isConnected(): bool
    {
        $token = self::getToken();
        return !empty($token['access_token']);
    }

    public static function getSelectedProperty(): string
    {
        return get_option(self::PROPERTY_OPTION, '');
    }

    public static function setSelectedProperty(string $property): void
    {
        update_option(self::PROPERTY_OPTION, $property);
    }

    public static function getAuthUrl(): string
    {
        $clientId = get_option(self::CLIENT_ID_OPTION, '');
        if (empty($clientId)) {
            return '';
        }

        // Use a one-time transient for OAuth state (more secure than reusable nonces)
        $state = wp_generate_password(32, false);
        $userId = get_current_user_id();
        set_transient('aisa_gsc_oauth_state_' . $userId, $state, 10 * MINUTE_IN_SECONDS);

        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::getRedirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public static function handleCallback(): bool
    {
        if (!isset($_GET['gsc_callback']) || $_GET['gsc_callback'] !== '1') {
            return false;
        }

        // Capability check — only admins with manage_aisa can complete OAuth
        if (!current_user_can('manage_aisa')) {
            Logger::error('GSC OAuth: Unauthorized callback attempt');
            return false;
        }

        if (!isset($_GET['code'])) {
            Logger::error('GSC OAuth: No authorization code received');
            return false;
        }

        // Verify one-time state transient
        $userId = get_current_user_id();
        $storedState = get_transient('aisa_gsc_oauth_state_' . $userId);
        delete_transient('aisa_gsc_oauth_state_' . $userId);

        if (!$storedState || !isset($_GET['state']) || !hash_equals($storedState, sanitize_text_field($_GET['state']))) {
            Logger::error('GSC OAuth: Invalid or expired state parameter');
            return false;
        }

        $code = sanitize_text_field($_GET['code']);
        return self::exchangeCode($code);
    }

    private static function exchangeCode(string $code): bool
    {
        $clientId = get_option(self::CLIENT_ID_OPTION, '');
        $clientSecret = get_option(self::CLIENT_SECRET_OPTION, '');

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => self::getRedirectUri(),
                'grant_type' => 'authorization_code',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('GSC OAuth token exchange failed: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            Logger::error('GSC OAuth: No access token in response');
            return false;
        }

        $tokenData = [
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at' => time() + ($body['expires_in'] ?? 3600),
            'token_type' => $body['token_type'] ?? 'Bearer',
        ];

        self::saveToken($tokenData);
        Logger::info('GSC OAuth: Successfully connected');
        return true;
    }

    public static function getAccessToken(): ?string
    {
        $token = self::getToken();
        if (empty($token['access_token'])) {
            return null;
        }

        // Refresh if expired or within 5 minutes of expiry
        if (isset($token['expires_at']) && $token['expires_at'] < (time() + 300)) {
            if (!self::refreshToken()) {
                return null;
            }
            $token = self::getToken();
        }

        return $token['access_token'] ?? null;
    }

    private static function refreshToken(): bool
    {
        $token = self::getToken();
        if (empty($token['refresh_token'])) {
            Logger::error('GSC OAuth: No refresh token available');
            return false;
        }

        $clientId = get_option(self::CLIENT_ID_OPTION, '');
        $clientSecret = get_option(self::CLIENT_SECRET_OPTION, '');

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'refresh_token' => $token['refresh_token'],
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('GSC token refresh failed: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            Logger::error('GSC OAuth: Token refresh returned no access token');
            self::disconnect();
            return false;
        }

        $tokenData = [
            'access_token' => $body['access_token'],
            'refresh_token' => $token['refresh_token'], // Keep existing refresh token
            'expires_at' => time() + ($body['expires_in'] ?? 3600),
            'token_type' => $body['token_type'] ?? 'Bearer',
        ];

        self::saveToken($tokenData);
        return true;
    }

    public static function fetchProperties(): array
    {
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return [];
        }

        $response = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            Logger::error('GSC fetch properties failed: ' . $response->get_error_message());
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $sites = $body['siteEntry'] ?? [];

        return array_map(fn($site) => [
            'url' => $site['siteUrl'] ?? '',
            'permission' => $site['permissionLevel'] ?? '',
        ], $sites);
    }

    public static function disconnect(): void
    {
        delete_option(self::TOKEN_OPTION);
        delete_option(self::PROPERTY_OPTION);
        Logger::info('GSC OAuth: Disconnected');
    }

    private static function getToken(): array
    {
        $encrypted = get_option(self::TOKEN_OPTION, '');
        if (empty($encrypted)) {
            return [];
        }

        // Decrypt if encryption is available
        if (function_exists('sodium_crypto_secretbox_open') && defined('LOGGED_IN_KEY')) {
            $decoded = base64_decode($encrypted);
            if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
                // Not encrypted or corrupted, try as plain JSON
                $plain = json_decode($encrypted, true);
                return is_array($plain) ? $plain : [];
            }

            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $key = sodium_crypto_generichash(LOGGED_IN_KEY, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

            $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            if ($decrypted === false) {
                return [];
            }

            return json_decode($decrypted, true) ?: [];
        }

        // Fallback: plain JSON
        $plain = json_decode($encrypted, true);
        return is_array($plain) ? $plain : [];
    }

    private static function saveToken(array $tokenData): void
    {
        // Encrypt if available
        if (function_exists('sodium_crypto_secretbox') && defined('LOGGED_IN_KEY')) {
            $key = sodium_crypto_generichash(LOGGED_IN_KEY, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox(json_encode($tokenData), $nonce, $key);
            $encrypted = base64_encode($nonce . $ciphertext);
            update_option(self::TOKEN_OPTION, $encrypted);
        } else {
            update_option(self::TOKEN_OPTION, json_encode($tokenData));
        }
    }
}
