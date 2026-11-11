<!--
MIT License

Copyright (c) 2026 Ștefan-Mihai MOGA

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
-->

<?php

/**
 * Reads annual exchange rates published by the National Bank of Romania
 * from https://curs.bnr.ro/files/xml/years/nbrfxrates2026.xml
 *
 * Usage:
 *   $anual = new CursValutarAnual();
 *   echo $anual->getRate('2026-01-05', 'EUR'); // RON per 1 EUR
 *   echo $anual->getDate();                    // Latest available date
 */
class CursValutarAnual
{
	public const URL_TEMPLATE = 'https://curs.bnr.ro/files/xml/years/nbrfxrates%s.xml';
	private const NS = 'https://www.bnr.ro/xsd';

	private string $publisher = '';
	private string $publishingDate = '';
	private string $origCurrency = 'RON';
	/** @var array<string, array<string, array{value: float, multiplier: int}>> */
	private array $rates = [];

	public const CACHE_TTL = 3600;

	private string $cacheFile;

	/**
	 * @param string|null $source   URL or file path; defaults to the BNR annual feed.
	 * @param string|null $cacheDir Directory for the cached XML; defaults to the system temp dir.
	 * @throws RuntimeException on download or parse failure.
	 */
	public function __construct(?string $source = null, ?string $cacheDir = null)
	{
		$source ??= sprintf(self::URL_TEMPLATE, date('Y'));
		$cacheDir = rtrim($cacheDir ?? sys_get_temp_dir(), '/\\');
		// Keep separate cached feeds when callers use different sources.
		$this->cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'bnr_annual_' . md5($source) . '.xml';
		$this->load($source);
	}

	/**
	 * Loads the cached annual feed, refreshing it when stale or invalid.
	 *
	 * @param string $source Feed URL or local file path.
	 * @throws RuntimeException If no usable feed can be loaded.
	 */
	private function load(string $source): void
	{
		$exists = is_file($this->cacheFile);

		// Never read before: download immediately. Otherwise wait for the TTL to expire.
		if (!$exists || !$this->isFresh()) {
			try {
				$this->download($source);
			} catch (RuntimeException $e) {
				// Keep serving the stale local file if the refresh fails.
				if (!$exists) {
					throw $e;
				}
			}
		}

		try {
			$this->parse($this->cacheFile);
		} catch (RuntimeException $e) {
			// Corrupt local file: replace it with a fresh download.
			$this->download($source);
			$this->parse($this->cacheFile);
		}
	}

	/**
	 * Determines whether the cached feed is younger than the cache lifetime.
	 *
	 * @return bool True when the cached feed is fresh.
	 */
	private function isFresh(): bool
	{
		$mtime = @filemtime($this->cacheFile);
		return $mtime !== false && (time() - $mtime) < self::CACHE_TTL;
	}

	/** Downloads the feed and stores it atomically in the local XML file. */
	private function download(string $source): void
	{
		$xmlText = $this->fetch($source);
		$tmp = $this->cacheFile . '.' . uniqid('', true) . '.tmp';
		if (@file_put_contents($tmp, $xmlText) === false || !@rename($tmp, $this->cacheFile)) {
			@unlink($tmp);
			throw new RuntimeException("Unable to write {$this->cacheFile}");
		}
	}

	/**
	 * Reads and indexes all dates and rates from a local annual XML file.
	 *
	 * @param string $file Path to the cached XML file.
	 * @throws RuntimeException If the XML is invalid or has no dated rate entries.
	 */
	private function parse(string $file): void
	{
		$this->rates = [];

		// Preserve the caller's libxml error mode after suppressing parser warnings.
		$previous = libxml_use_internal_errors(true);
		$xml = simplexml_load_file($file);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if ($xml === false) {
			throw new RuntimeException('Invalid XML in ' . $file);
		}

		$xml->registerXPathNamespace('b', self::NS);

		$publisher = $xml->xpath('//b:Header/b:Publisher');
		$this->publisher = $publisher ? (string) $publisher[0] : '';

		$publishingDate = $xml->xpath('//b:Header/b:PublishingDate');
		$this->publishingDate = $publishingDate ? (string) $publishingDate[0] : '';

		$orig = $xml->xpath('//b:Body/b:OrigCurrency');
		$this->origCurrency = $orig ? (string) $orig[0] : 'RON';

		$cubes = $xml->xpath('//b:Body/b:Cube');
		if (!$cubes) {
			throw new RuntimeException('No Cube elements found in BNR annual XML.');
		}

		foreach ($cubes as $cube) {
			$date = (string) $cube['date'];
			if ($date === '') {
				throw new RuntimeException('Cube element is missing its date in BNR annual XML.');
			}

			$dayRates = [];
			foreach ($cube->children(self::NS) as $rate) {
				$attrs = $rate->attributes();
				$currency = strtoupper((string) $attrs['currency']);
				if ($currency === '') {
					continue;
				}
				// BNR omits multiplier for ordinary one-unit rates.
				$multiplier = isset($attrs['multiplier']) ? (int) $attrs['multiplier'] : 1;
				$dayRates[$currency] = [
					'value' => (float) (string) $rate,
					'multiplier' => $multiplier,
				];
			}
			$this->rates[$date] = $dayRates;
		}

		// Sorted keys make date iteration chronological and getDate() reliable.
		ksort($this->rates);
	}

	/**
	 * Reads feed contents from a local file or an HTTP(S) URL.
	 *
	 * @param string $source File path or URL to read.
	 * @return string Feed contents.
	 * @throws RuntimeException If the source cannot be read.
	 */
	private function fetch(string $source): string
	{
		if (!preg_match('#^https?://#i', $source)) {
			$data = @file_get_contents($source);
		} elseif (function_exists('curl_init')) {
			$ch = curl_init($source);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT => 15,
				CURLOPT_SSL_VERIFYPEER => true,
			]);
			$data = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($data !== false && $code >= 400) {
				$data = false;
			}
		} else {
			$ctx = stream_context_create(['http' => ['timeout' => 15]]);
			$data = @file_get_contents($source, false, $ctx);
		}

		if ($data === false || $data === '') {
			throw new RuntimeException("Unable to read BNR annual rates from $source");
		}
		return $data;
	}

	/** @return string Publisher named in the feed. */
	public function getPublisher(): string
	{
		return $this->publisher;
	}

	/** @return string Date the annual XML dataset was published (Y-m-d). */
	public function getPublishingDate(): string
	{
		return $this->publishingDate;
	}

	/** @return string Latest date with available rates (Y-m-d), or an empty string. */
	public function getDate(): string
	{
		$dates = array_keys($this->rates);
		return $dates ? $dates[count($dates) - 1] : '';
	}

	/** @return string Base currency used by the feed. */
	public function getOrigCurrency(): string
	{
		return $this->origCurrency;
	}

	/** @return string[] Available rate dates, in ascending order. */
	public function getDates(): array
	{
		return array_keys($this->rates);
	}

	/** @return string[] Available currency codes for a date (latest by default). */
	public function getCurrencies(?string $date = null): array
	{
		return array_keys($this->rates[$this->dateEntry($date)]);
	}

	/**
	 * Checks whether a currency has a published rate on the given date.
	 *
	 * @param string $date Rate date (Y-m-d).
	 * @param string $currency Currency code.
	 * @return bool True when the currency is present for the date.
	 */
	public function hasCurrency(string $date, string $currency): bool
	{
		$date = $this->dateEntry($date);
		return isset($this->rates[$date][strtoupper($currency)]);
	}

	/** Raw published value (RON per multiplier units of currency). */
	public function getRawRate(string $date, string $currency): float
	{
		return $this->entry($date, $currency)['value'];
	}

	/**
	 * Returns the number of currency units represented by the raw published rate.
	 *
	 * @param string $date Rate date (Y-m-d).
	 * @param string $currency Currency code.
	 * @return int Published rate multiplier.
	 */
	public function getMultiplier(string $date, string $currency): int
	{
		return $this->entry($date, $currency)['multiplier'];
	}

	/** Normalized rate: RON per 1 unit of currency. */
	public function getRate(string $date, string $currency): float
	{
		$entry = $this->entry($date, $currency);
		return $entry['value'] / $entry['multiplier'];
	}

	/**
	 * Converts an amount between two currencies using the rates for a date.
	 *
	 * @param string $date Rate date (Y-m-d).
	 * @param float $amount Amount to convert.
	 * @param string $from Source currency code.
	 * @param string $to Target currency code.
	 * @return float Converted amount.
	 */
	public function convert(string $date, float $amount, string $from, string $to): float
	{
		$from = strtoupper($from);
		$to = strtoupper($to);
		// Convert through the base currency to support any currency pair.
		$inRon = $from === $this->origCurrency ? $amount : $amount * $this->getRate($date, $from);
		return $to === $this->origCurrency ? $inRon : $inRon / $this->getRate($date, $to);
	}

	/**
	 * Returns every indexed date and its published currency rates.
	 *
	 * @return array<string, array<string, array{value: float, multiplier: int}>>
	 */
	public function getAll(): array
	{
		return $this->rates;
	}

	/**
	 * Returns the rates for a date, defaulting to the latest available date.
	 *
	 * @param string|null $date Rate date (Y-m-d), or null for the latest date.
	 * @return array<string, array{value: float, multiplier: int}>
	 */
	public function getRates(?string $date = null): array
	{
		return $this->rates[$this->dateEntry($date)];
	}

	/**
	 * Resolves an optional date and verifies that rates exist for it.
	 *
	 * @param string|null $date Requested date, or null for the latest date.
	 * @return string Resolved date.
	 * @throws InvalidArgumentException If the requested date is unavailable.
	 */
	private function dateEntry(?string $date): string
	{
		$date ??= $this->getDate();
		if (!isset($this->rates[$date])) {
			throw new InvalidArgumentException("Unknown rate date: $date");
		}
		return $date;
	}

	/**
	 * Resolves a currency rate entry for a date.
	 *
	 * @param string $date Rate date (Y-m-d).
	 * @param string $currency Currency code.
	 * @return array{value: float, multiplier: int} Published rate and multiplier.
	 * @throws InvalidArgumentException If the date or currency is unavailable.
	 */
	private function entry(string $date, string $currency): array
	{
		$date = $this->dateEntry($date);
		$currency = strtoupper($currency);
		if (!isset($this->rates[$date][$currency])) {
			throw new InvalidArgumentException("Unknown currency: $currency");
		}
		return $this->rates[$date][$currency];
	}
}
