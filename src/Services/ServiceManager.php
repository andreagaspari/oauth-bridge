<?php

namespace Immaginificio\OAuthProxyBridge\Services;

/**
 * ServiceManager: simple factory to retrieve provider services
 *
 * @package Immaginificio\OAuthProxyBridge\Services
 * @since 0.0.1
 */
class ServiceManager
{
    protected array $map = [];
    /**
     * Construct the ServiceManager with default mappings.
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        // default mapping: provider => class FQCN
        $this->map = [
            'google' => GoogleService::class,
        ];
    }

    /**
     * Retrieve the service instance for the requested provider.
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
     * Return the list of available providers (mapped keys).
     *
     * @return array
     * @since 0.0.1
     */
    public function availableProviders(): array
    {
        return array_keys($this->map);
    }
}
