<?php

namespace Immaginificio\OAuthProxyBridge\Services;

/**
 * ServiceInterface per provider OAuth
 *
 * @package Immaginificio\OAuthProxyBridge\Services
 * @since 0.0.1
 */
interface ServiceInterface
{
    /**
     * Restituisce l'URL di autorizzazione (login) per il provider
     *
     * @param array $options
     * @return string
     */
    public function getAuthUrl(array $options = []): string;

    /**
     * Scambia il code con token (authorization code exchange)
     *
     * @param array $params
     * @return array Token data
     */
    public function exchangeCode(array $params): array;

    /**
     * Esegue il refresh del token
     *
     * @param string $refreshToken
     * @return array
     */
    public function refreshToken(string $refreshToken): array;
}
