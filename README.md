# Google Maps Geocoder

Finds the exact map position of a Shopclass listing from its street address, using the
[Google Geocoding API](https://developers.google.com/maps/documentation/geocoding). Distance
search then uses that point instead of the city or region centre.

## Requirements

- Shopclass 7.0.0 or later
- PHP 8.0 or later, with the curl extension
- A Google Cloud project with billing on and the Geocoding API enabled

It works on shared hosting. Nothing runs on a normal page load; the plugin is built only when
an address is looked up.

## Setup

1. In Google Cloud, create an API key, enable the **Geocoding API** for it, and restrict it by
   **IP address** to your server.
2. Install and activate the plugin, then open **Plugins → Google Maps Geocoder** and paste the key.
3. Open **Settings → General → Maps** and choose *Google Maps* under *Address lookup*.

Use a separate key, not the Maps JavaScript key in Settings → General. That key is public and
limited to your site's web address, so Google refuses it for lookups made by the server.

The saved key is never shown again. Leave the box blank to keep it.

## When lookups run

A lookup runs from the job queue after a listing with a street address is saved, not while the
visitor waits. Cron (or auto-cron) must be running; that is enough on shared hosting, and a
queue worker makes it faster.

## Errors

A quota error, a refused key or an unreachable service makes the lookup fail, and Shopclass
tries again later. An address Google cannot find is remembered for a day.

## Google's terms

Positions are kept for 30 days, the longest Google allows. Google's terms want the results
shown with a Google map, so use this plugin with the Google Maps display, not another map.
The plugin prints no attribution of its own.

## Uninstall

The plugin's settings are removed. If *Address lookup* in Settings → General → Maps named this
plugin, it is reset to none.

## Tests

```
./tests/run.sh
```

They need no network. Core is read from `../osclass`, or from `SHOPCLASS_CORE`.

## License

GPL-3.0-or-later.
