<?php

namespace Immaginificio\OAuthProxyBridge\Services;

/**
 * ServiceInterface for OAuth providers
 *
 * @package Immaginificio\OAuthProxyBridge\Services
 * @since 0.0.1
 */
interface ServiceInterface
{
    /**
     * Return the authorization (login) URL for the provider.
     *
     * @param array $options
     * @return string
     */
    public function getAuthUrl(array $options = []): string;

    /**
     * Exchange an authorization code for token data.
     *
     * @param array $params
     * @return array Token data
     */
    public function exchangeCode(array $params): array;

    /**
     * Refresh an access token using a refresh token.
     *
     * @param string $refreshToken
     * @return array
     */
    public function refreshToken(string $refreshToken): array;
}
