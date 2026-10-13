<?php

/*
 * This file is part of the Google Maps Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/*
 * The settings page declaration. Core renders the form, checks CSRF and the admin's
 * capability, validates each field and stores it under the "google_geocoder" section.
 */

return array(
    'title'  => __('Google Maps Geocoder', 'google-geocoder'),
    'menu'   => 'plugins',
    'intro'  => __('Finds the exact map position of a listing from its street address. Pick Google Maps under Settings > General > Maps > Address lookup to use it.', 'google-geocoder'),
    'groups' => array(
        array(
            'title'  => __('Account', 'google-geocoder'),
            'fields' => array(
                array(
                    'type'       => 'secret',
                    'name'       => 'api_key',
                    'label'      => __('Server API key', 'google-geocoder'),
                    'help'       => __('A separate key with the Geocoding API turned on, restricted to this server\'s IP address. Do not reuse the Maps JavaScript key from Settings > General: that one is public and limited to your site\'s web address, so Google refuses it for lookups made by the server. It is never shown again; leave the box blank to keep the saved key.', 'google-geocoder'),
                    'write_only' => true,
                    'masked'     => true,
                    'attrs'      => array('autocomplete' => 'new-password', 'spellcheck' => 'false'),
                    'persist'    => static fn ($value) => trim((string) $value) === '' ? null : trim((string) $value),
                    'validate'   => static fn ($value) => preg_match('/^[A-Za-z0-9._-]*$/', trim((string) $value)) ? null : __('This does not look like a Google API key', 'google-geocoder'),
                ),
            ),
        ),
        array(
            'title'  => __('Terms', 'google-geocoder'),
            'fields' => array(
                array(
                    'type'   => 'custom',
                    'name'   => 'terms_info',
                    'label'  => __('Using the results', 'google-geocoder'),
                    'render' => static function (): void {
                        echo '<p>' . osc_esc_html(__('Positions are kept for 30 days, the longest Google allows. Google\'s terms ask that results be shown on a Google map, so use this with the Google Maps display and not with another map.', 'google-geocoder')) . '</p>';
                    },
                ),
            ),
        ),
    ),
);
