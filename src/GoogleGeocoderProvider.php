<?php

/*
 * This file is part of the Google Maps Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace mindstellar\googlegeocoder;

use mindstellar\googlegeocoder\Http\HttpClient;
use mindstellar\googlegeocoder\Http\HttpException;
use mindstellar\location\GeocodeProvider;
use RuntimeException;

/**
 * Finds the coordinates of an address through the Google Geocoding API.
 */
final class GoogleGeocoderProvider implements GeocodeProvider
{
    public const ID = 'google-geocoder';

    /** Google lets latitude and longitude be cached for up to 30 days. */
    public const TTL = 2592000;

    /** Statuses that mean the request itself or the key is wrong, not the address. */
    private const REFUSED = array('REQUEST_DENIED', 'INVALID_REQUEST', 'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT', 'UNKNOWN_ERROR');

    private string $apiKey;

    public function __construct(string $apiKey, private HttpClient $http)
    {
        $this->apiKey = trim($apiKey);
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'Google Maps';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function cacheTtl(): ?int
    {
        return self::TTL;
    }

    public function attribution(): string
    {
        return '';
    }

    public function endpoint(): string
    {
        return 'https://maps.googleapis.com/maps/api/geocode/json';
    }

    /**
     * @return array<string,string>
     */
    public function params(string $address, ?string $countryCode = null): array
    {
        $query = array('address' => $address, 'key' => $this->apiKey);
        if ($countryCode !== null && preg_match('/^[A-Za-z]{2}\z/', $countryCode)) {
            $code = strtoupper($countryCode);
            // Google's region bias uses the country's top-level domain, which is "uk" for GB.
            $query['region']     = $code === 'GB' ? 'uk' : strtolower($code);
            $query['components'] = 'country:' . $code;
        }

        return $query;
    }

    /** The request URL, key included. For tests; the lookup itself never builds it here. */
    public function url(string $address, ?string $countryCode = null): string
    {
        return $this->endpoint() . '?' . http_build_query($this->params($address, $countryCode), '', '&', PHP_QUERY_RFC3986);
    }

    public function geocode(string $address, ?string $countryCode = null): ?array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Maps Geocoder has no API key');
        }

        try {
            $response = $this->http->get($this->endpoint(), $this->params($address, $countryCode));
        } catch (HttpException $e) {
            throw new RuntimeException($this->mask($e->getMessage()));
        }

        $httpStatus = $response['status'];
        if ($httpStatus === 429) {
            throw new RuntimeException('Google rate limit reached (HTTP 429)');
        }
        if ($httpStatus >= 500) {
            throw new RuntimeException('Google answered HTTP ' . $httpStatus);
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data) || !isset($data['status']) || !is_string($data['status'])) {
            throw new RuntimeException('Google answered HTTP ' . $httpStatus . ' with an answer that is not a geocoding result');
        }

        $status = $data['status'];
        if ($status === 'ZERO_RESULTS') {
            return null;
        }
        if ($status !== 'OK') {
            $reason = in_array($status, self::REFUSED, true) ? $status : 'unexpected status ' . substr(preg_replace('/[^A-Za-z_]/', '', $status), 0, 40);
            throw new RuntimeException($this->mask('Google refused the lookup: ' . $reason . $this->detail($data)));
        }

        $location = $data['results'][0]['geometry']['location'] ?? null;
        if (!is_array($location) || !isset($location['lat'], $location['lng']) || !is_numeric($location['lat']) || !is_numeric($location['lng'])) {
            throw new RuntimeException('Google sent an answer without coordinates');
        }

        return array((float) $location['lat'], (float) $location['lng']);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function detail(array $data): string
    {
        $text = $data['error_message'] ?? '';

        return is_string($text) && $text !== '' ? ' (' . substr($this->mask($text), 0, 200) . ')' : '';
    }

    private function mask(string $text): string
    {
        return $this->apiKey === '' ? $text : str_replace($this->apiKey, '***', $text);
    }
}
