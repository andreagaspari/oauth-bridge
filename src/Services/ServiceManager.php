<?php

namespace Immaginificio\OAuthProxyBridge\Services;

/**
 * ServiceManager: factory semplice per recuperare i servizi provider
 *
 * @package Immaginificio\OAuthProxyBridge\Services
 * @since 0.0.1
 */
class ServiceManager
{
    protected array $map = [];
    /**
     * Costruisce il ServiceManager con le mappature di default.
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        // mappatura di default: provider => class FQCN
        $this->map = [
            'google' => GoogleService::class,
        ];
    }

    /**
     * Recupera l'istanza del servizio per il provider richiesto.
     *
     * @param string $provider
     * @return ServiceInterface|null
     * @since 0.0.1
     */
    public function get(string $provider): ?ServiceInterface
    {
        $key = strtolower($provider);
        if (!isset($this->map[$key])) {
            return null;
        }
        $class = $this->map[$key];
        if (!class_exists($class)) {
            return null;
        }
        return new $class();
    }

    /**
     * Restituisce la lista dei provider disponibili (chiavi mappate).
     *
     * @return array
     * @since 0.0.1
     */
    public function availableProviders(): array
    {
        return array_keys($this->map);
    }
}
