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

namespace mindstellar\googlegeocoder\Http;

use RuntimeException;

/**
 * No answer came back from the service.
 */
class HttpException extends RuntimeException
{
}
