<?php

declare(strict_types=1);

namespace Shared\Infrastructure;

use Easy\Container\Attributes\Inject;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class IpService
{
    private const API_URL = 'https://free.freeipapi.com/api/json/';
    private const CACHE_PREFIX = 'ip_location_';
    private const CACHE_TTL = 86400; // 24 hours in seconds

    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private CacheInterface $cache,

        #[Inject('config.enable_caching')]
        private bool $enableCaching = false,
    ) {}

    /**
     * Get IP information for a specific IP or current user
     * 
     * @param string $ip IP address to look up
     * @return array{
     *     ipVersion?: int,
     *     ipAddress?: string,
     *     latitude?: float,
     *     longitude?: float,
     *     countryName?: string,
     *     countryCode?: string,
     *     capital?: string,
     *     phoneCodes?: array<int>,
     *     timeZones?: array<string>,
     *     zipCode?: string,
     *     cityName?: string,
     *     regionName?: string,
     *     continent?: string,
     *     continentCode?: string,
     *     currencies?: array<string>,
     *     languages?: array<string>,
     *     asn?: string,
     *     asnOrganization?: string,
     *     isProxy?: bool
     * }|null IP location data or null if error occurs
     */
    public function getIpInfo(string $ip): ?array
    {
        try {
            return $this->getData($ip);
        } catch (\Exception $error) {
            // Log error if needed
            return null;
        }
    }

    /**
     * Fetch IP location data from API
     * 
     * @param string $ip IP address to look up
     * @return array{
     *     ipVersion?: int,
     *     ipAddress?: string,
     *     latitude?: float,
     *     longitude?: float,
     *     countryName?: string,
     *     countryCode?: string,
     *     capital?: string,
     *     phoneCodes?: array<int>,
     *     timeZones?: array<string>,
     *     zipCode?: string,
     *     cityName?: string,
     *     regionName?: string,
     *     continent?: string,
     *     continentCode?: string,
     *     currencies?: array<string>,
     *     languages?: array<string>,
     *     asn?: string,
     *     asnOrganization?: string,
     *     isProxy?: bool
     * } IP location data
     * @throws ClientExceptionInterface When HTTP request fails
     * @throws JsonException When JSON decoding fails
     * @throws InvalidArgumentException When cache operations fail
     * @throws \RuntimeException When API returns invalid data
     */
    private function getData(string $ip): array
    {
        $cacheKey = $this->getCacheKey($ip);

        // Check cache first
        if ($this->enableCaching) {
            $cached = $this->getLocationFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Build API URL
        $url = self::API_URL . $ip;

        // Create and send request
        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->client->sendRequest($request);

        // Check response status
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('API returned %d', $response->getStatusCode())
            );
        }

        // Parse JSON response
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        // Cache the result
        if ($this->enableCaching) {
            $this->cacheLocation($cacheKey, $data);
        }

        return $data;
    }

    /**
     * Get cached location data for an IP
     * 
     * @param string $cacheKey Cache key
     * @return array<string,mixed>|null Cached location data or null if not found/expired
     * @throws InvalidArgumentException When cache operations fail
     */
    private function getLocationFromCache(string $cacheKey): ?array
    {
        if (!$this->cache->has($cacheKey)) {
            return null;
        }

        $data = $this->cache->get($cacheKey);

        return is_array($data) ? $data : null;
    }

    /**
     * Cache location data for an IP
     * 
     * @param string $cacheKey Cache key
     * @param array<string,mixed> $data Location data to cache
     * @return void
     * @throws InvalidArgumentException When cache operations fail
     */
    private function cacheLocation(string $cacheKey, array $data): void
    {
        $this->cache->set($cacheKey, $data, self::CACHE_TTL);
    }

    /**
     * Generate cache key for an IP address
     * 
     * @param string $ip IP address or 'current' for current user
     * @return string Cache key
     */
    private function getCacheKey(string $ip): string
    {
        return self::CACHE_PREFIX . $ip;
    }
}
