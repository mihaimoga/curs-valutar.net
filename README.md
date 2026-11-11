# curs-valutar.net

[curs-valutar.net](https://www.curs-valutar.net/) displays exchange rates published by the National Bank of Romania (BNR), including monthly average EUR, USD, and GBP rates.

## Current features

- Shows the BNR publication date, publisher, and available currency rates, including each rate's published multiplier.
- Formats rates using Romanian number conventions and displays country flags where available.
- Reads the official BNR XML feed. The feed is cached for one hour; if a refresh fails, a previously cached feed is used.
- Shows an unavailable message and returns HTTP 503 if rates cannot be loaded and no usable cached data is available.
- The `CursValutarBNR` class also provides methods for retrieving individual rates and converting amounts, but the current page is a rates table only.
- The `CursValutarAnual` class reads the current year's BNR annual XML feed, caches it for one hour, and indexes rates by publication date.
- `CursulMediuValutar.php` displays the monthly average EUR/RON, USD/RON, and GBP/RON rates for the current year, calculated independently from the available daily BNR rates in each month, along with the number of published rates used for each currency.

Charts, CSV/JSON exports, intraday rates, and a browser-based currency converter are not currently implemented.

## Run locally

Requirements: PHP with the `SimpleXML` and `curl` (optional, falls back to `file_get_contents`) extensions, outbound access to the BNR feed, and write permission to the system temporary directory (or a custom cache directory) for caching.

Clone the repository and start PHP's built-in development server from the project directory:

```sh
git clone https://github.com/mihaimoga/curs-valutar.net.git
cd curs-valutar.net
php -S localhost:8000
```

Open <http://localhost:8000> for current rates or <http://localhost:8000/CursulMediuValutar.php> for monthly average EUR, USD, and GBP rates.

## Tech stack

- PHP
- HTML and Bootstrap 5
- Flag Icons

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the MIT License.
