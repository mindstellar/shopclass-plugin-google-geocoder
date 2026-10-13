<?php

/*
 * This file is part of the Google Maps Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * URL building, parsing, errors, cache lifetime and key handling, against a fake HTTP client.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\googlegeocoder\GoogleGeocoderProvider;
use mindstellar\location\GeocodeProvider;

function make(FakeHttp $http, string $key = 'AIzaTESTKEY'): GoogleGeocoderProvider
{
    return new GoogleGeocoderProvider($key, $http);
}

function thrown(callable $fn): ?string
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    return null;
}

function ok(float $lat, float $lng): string
{
    return json_encode(array('status' => 'OK', 'results' => array(array('geometry' => array('location' => array('lat' => $lat, 'lng' => $lng))))));
}

harness_section('identity');
$p = make(new FakeHttp());
check('implements the core interface', $p instanceof GeocodeProvider);
pin('id', 'google-geocoder', $p->id());
pin('label', 'Google Maps', $p->label());
pin('configured with a key', true, $p->isConfigured());
pin('not configured without a key', false, make(new FakeHttp(), '')->isConfigured());
pin('a blank key is not a key', false, make(new FakeHttp(), '  ')->isConfigured());
pin('cache lifetime is 30 days', 2592000, $p->cacheTtl());
pin('no attribution markup', '', $p->attribution());

harness_section('URL');
$url = $p->url('12 Main St, Pune', 'IN');
check('host and path', str_starts_with($url, 'https://maps.googleapis.com/maps/api/geocode/json?'));
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
pin('address', '12 Main St, Pune', $q['address']);
pin('key', 'AIzaTESTKEY', $q['key']);
pin('region is the lower-case code', 'in', $q['region']);
pin('components restrict to the country', 'country:IN', $q['components']);
parse_str((string) parse_url($p->url('x', 'gb'), PHP_URL_QUERY), $q2);
pin('GB uses the uk region', 'uk', $q2['region']);
pin('GB components', 'country:GB', $q2['components']);
parse_str((string) parse_url($p->url('x'), PHP_URL_QUERY), $q3);
check('no country, no region or components', !isset($q3['region']) && !isset($q3['components']));
parse_str((string) parse_url($p->url('x', 'India'), PHP_URL_QUERY), $q4);
check('a bad country code is dropped', !isset($q4['region']) && !isset($q4['components']));
check('spaces and ampersands are encoded', str_contains($p->url('a & b'), 'address=a%20%26%20b'));

harness_section('answers');
$http = (new FakeHttp())->answer(200, ok(18.5204, 73.8567));
pin('first result', array(18.5204, 73.8567), make($http)->geocode('Pune', 'IN'));
pin('one request', 1, count($http->urls));
pin('no match: ZERO_RESULTS', null, make((new FakeHttp())->answer(200, '{"status":"ZERO_RESULTS","results":[]}'))->geocode('zzzz'));

harness_section('errors throw');
foreach (array('OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT', 'REQUEST_DENIED', 'UNKNOWN_ERROR', 'INVALID_REQUEST', 'SOMETHING_NEW') as $status) {
    $body = json_encode(array('status' => $status, 'results' => array()));
    $msg  = thrown(fn () => make((new FakeHttp())->answer(200, $body))->geocode('x'));
    check("$status throws", $msg !== null && str_contains($msg, $status === 'SOMETHING_NEW' ? 'unexpected' : $status), (string) $msg);
}
check('HTTP 429 throws', thrown(fn () => make((new FakeHttp())->answer(429, ''))->geocode('x')) !== null);
check('HTTP 500 throws', thrown(fn () => make((new FakeHttp())->answer(500, ''))->geocode('x')) !== null);
check('HTTP 503 throws', thrown(fn () => make((new FakeHttp())->answer(503, ''))->geocode('x')) !== null);
check('HTTP 403 with no JSON throws', thrown(fn () => make((new FakeHttp())->answer(403, 'Forbidden'))->geocode('x')) !== null);
check('bad JSON throws', thrown(fn () => make((new FakeHttp())->answer(200, '<html>'))->geocode('x')) !== null);
check('OK without coordinates throws', thrown(fn () => make((new FakeHttp())->answer(200, '{"status":"OK","results":[]}'))->geocode('x')) !== null);
check('unreachable throws', thrown(fn () => make((new FakeHttp())->fail('timeout'))->geocode('x')) !== null);
check('no key throws', thrown(fn () => make(new FakeHttp(), '')->geocode('x')) !== null);

harness_section('the key never leaks');
$msg = (string) thrown(fn () => make((new FakeHttp())->fail('could not connect to https://maps.googleapis.com/maps/api/geocode/json?key=AIzaTESTKEY&address=x'))->geocode('x'));
check('curl text is masked', !str_contains($msg, 'AIzaTESTKEY') && str_contains($msg, '***'), $msg);
$body = json_encode(array('status' => 'REQUEST_DENIED', 'error_message' => 'The key AIzaTESTKEY is not authorized.'));
$msg  = (string) thrown(fn () => make((new FakeHttp())->answer(200, $body))->geocode('x'));
check('Google error text is masked', !str_contains($msg, 'AIzaTESTKEY') && str_contains($msg, 'REQUEST_DENIED'), $msg);

harness_section('hardening');
function caught(callable $fn): ?Throwable
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        return $e;
    }

    return null;
}
$e = caught(fn () => make((new FakeHttp())->fail('boom AIzaTESTKEY'))->geocode('x'));
pin('the HTTP error is not chained', null, $e->getPrevious());
check('no stack frame argument holds the key', !str_contains(json_encode(array_map(fn ($f) => $f['args'] ?? array(), $e->getTrace()), JSON_PARTIAL_OUTPUT_ON_ERROR), 'AIzaTESTKEY'));
check('the trace text has no key', !str_contains($e->getTraceAsString(), 'AIzaTESTKEY'));
$spy = new FakeHttp();
$spy->answer(200, '{"status":"ZERO_RESULTS"}');
make($spy)->geocode('addr', 'IN');
pin('timeouts and size cap are passed', array('connect_timeout' => 3, 'timeout' => 5, 'max_bytes' => 65536), $spy->options[0]);


$body = json_encode(array('status' => 'REQUEST_DENIED', 'error_message' => str_repeat('a', 195) . 'AIzaTESTKEY tail'));
$msg  = (string) thrown(fn () => make((new FakeHttp())->answer(200, $body))->geocode('x'));
check('a key cut by the 200 character limit is still masked', !str_contains($msg, 'AIza'), $msg);

exit(harness_result());
