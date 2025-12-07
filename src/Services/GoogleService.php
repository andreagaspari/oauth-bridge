<?php

namespace Immaginificio\OAuthProxyBridge\Services;

/**
 * Google OAuth service implementation
 *
 * @package Immaginificio\OAuthProxyBridge\Services
 * @since 0.0.1
 */
class GoogleService implements ServiceInterface
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;

    /**
        * Initialize Google client credentials from `.env` or defined constants.
        *
        * @since 0.0.1
        */
    public function __construct()
    {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '');
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '');
    }

    /**
        * Build the authorization URL for Google.
        *
        * @param array $options
        * @return string
        * @since 0.0.1
        */
    public function getAuthUrl(array $options = []): string
    {
        $params = array_merge([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => $options['scope'] ?? 'openid email profile',
            'access_type' => $options['access_type'] ?? 'offline',
            'prompt' => $options['prompt'] ?? 'consent',
            'state' => $options['state'] ?? bin2hex(random_bytes(16)),
        ], $options);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
        * Exchange the authorization code for tokens via POST to Google.
        *
        * @param array $params
        * @return array
        * @since 0.0.1
        */
    public function exchangeCode(array $params): array
    {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postFields = [
            'code' => $params['code'] ?? null,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ];

        return $this->postForm($tokenUrl, $postFields);
    }

    /**
        * Request new tokens using a refresh token.
        *
        * @param string $refreshToken
        * @return array
        * @since 0.0.1
        */
    public function refreshToken(string $refreshToken): array
    {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postFields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        return $this->postForm($tokenUrl, $postFields);
    }
/**
 * postForm
 *
 * @param mixed $url
 * @param mixed $postFields
 * @return mixed
 * @since 0.0.1
 */

    protected function postForm(string $url, array $postFields): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['error' => 'invalid_response', 'http_code' => $http_code, 'raw' => $response];
        }
        return $data;
    }
}
