# Google Maps Geocoder

Finds the exact map position of a listing from its street address, using the
[Google Geocoding API](https://developers.google.com/maps/documentation/geocoding). Distance
search then uses that point instead of the city or region centre.

## Requirements

- Shopclass 7.0.0 or later
- PHP 8.0 or later, with the curl extension
- A Google Cloud project with billing on and the Geocoding API enabled

## Install

1. Open Plugins → Browse (or upload the zip from the GitHub release).
2. Install.
3. Activate.

## Set up

1. In Google Cloud, create an API key, enable the Geocoding API for it, and restrict it by IP address to your server.
2. Open Plugins → Google Maps Geocoder and paste the key into *Server API key*.
3. Open Settings → General → Maps and choose Google Maps under *Address lookup*.

Use a separate key, not the Maps JavaScript key from Settings → General. That key is public and limited to your site's web address, so Google refuses it for lookups made by the server.

## How it works

- A lookup runs from the job queue after a listing with a street address is saved. Visitors do not wait for it.
- Cron (or auto-cron) must be running. A queue worker makes it faster.
- Nothing runs on a normal page load.

## Notes / limits

- The saved key is never shown again. Leave the box blank to keep it.
- A quota error, a refused key or an unreachable service makes the lookup fail. Shopclass tries again later.
- An address Google cannot find is remembered for a day.
- Positions are kept for 30 days, the longest Google allows.
- Google's terms want results shown on a Google map. Use this plugin with the Google Maps display, not another map. The plugin prints no attribution.
- On uninstall, the settings are removed. If *Address lookup* named this plugin, it is reset to none.
- Works on shared hosting.

## For developers

Run the tests with `./tests/run.sh`. They need no network. Core is read from `../shopclass`, or from `SHOPCLASS_CORE`.

## Support

https://github.com/mindstellar/shopclass-plugin-google-geocoder/issues

## License

GPL-3.0-or-later
