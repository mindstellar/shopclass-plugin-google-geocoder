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
 * The wiring, against core's real GeocodeProviderRegistry and Geocoder.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\googlegeocoder\GoogleGeocoderProvider;
use mindstellar\googlegeocoder\Plugin;
use mindstellar\location\GeocodeProviderRegistry;
use mindstellar\location\Geocoder;

const SECTION = 'google_geocoder';

harness_section('registry');
osc_add_hook(GeocodeProviderRegistry::HOOK, array(Plugin::class, 'register'));
$GLOBALS['prefs'][SECTION] = array('api_key' => 'AIzaTEST');
$registry = GeocodeProviderRegistry::getInstance();
$provider = $registry->get('google-geocoder');
check('the hook registers the provider', $provider !== null);
pin('and it is the only one', array('google-geocoder'), array_keys($registry->all()));
pin('cache lifetime is 30 days', 2592000, $provider->cacheTtl());
check('built from the saved key', str_contains($provider->url('x'), 'key=AIzaTEST'));

harness_section('active only when chosen and configured');
pin('nothing chosen', null, $registry->active());
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = 'google-geocoder';
check('chosen and configured', $registry->active() !== null);
$GLOBALS['prefs'][SECTION]['api_key'] = '';
Plugin::register($registry);
pin('chosen without a key', null, $registry->active());

harness_section('admin notice');
ob_start();
Plugin::adminNotice();
$out = ob_get_clean();
check('warns when chosen with no key', str_contains($out, 'no API key') && str_contains($out, 'flashmessage-warning'));
$GLOBALS['prefs'][SECTION]['api_key'] = 'AIzaTEST';
ob_start();
Plugin::adminNotice();
pin('quiet with a key', '', ob_get_clean());
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = 'other';
$GLOBALS['prefs'][SECTION]['api_key'] = '';
ob_start();
Plugin::adminNotice();
pin('quiet when another provider is chosen', '', ob_get_clean());

harness_section('uninstall');
$GLOBALS['prefs'][SECTION] = array('api_key' => 'k');
Plugin::uninstall();
pin('key removed', array(), $GLOBALS['prefs'][SECTION]);

harness_section('settings page declaration');
$spec  = require __DIR__ . '/../settings.php';
$names = array();
foreach ($spec['groups'] as $group) {
    foreach ($group['fields'] as $field) {
        $names[$field['name']] = $field;
    }
}
check('has the key field', isset($names['api_key']));
pin('the key is a secret', 'secret', $names['api_key']['type']);
pin('the key is write-only', true, $names['api_key']['write_only']);
pin('a blank box keeps the key', null, ($names['api_key']['persist'])('  '));
pin('a new key is trimmed', 'AIzaNew', ($names['api_key']['persist'])(' AIzaNew '));
check('a key with a space is rejected', ($names['api_key']['validate'])('a b') !== null);
pin('a normal key passes', null, ($names['api_key']['validate'])('AIzaSy_abc-123'));
check('the help says the key is separate from the JavaScript key', str_contains($names['api_key']['help'], 'Maps JavaScript key'));

harness_section('lookup through core\'s Geocoder');
if (!function_exists('osc_apply_filter')) {
    function osc_apply_filter($hook, $value = null, ...$args)
    {
        return $value;
    }
}
$http     = (new FakeHttp())->answer(200, '{"status":"OK","results":[{"geometry":{"location":{"lat":18.5,"lng":73.8}}}]}');
$geocoder = new Geocoder(new GoogleGeocoderProvider('AIzaTEST', $http));
pin('first lookup hits the service', array(18.5, 73.8), $geocoder->geocode('12 Main St, Pune', 'IN'));
pin('second lookup comes from the cache', array(18.5, 73.8), $geocoder->geocode('12 Main St, Pune', 'IN'));
pin('only one request was made', 1, count($http->urls));

exit(harness_result());
