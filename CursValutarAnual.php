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
        $this->cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'bnr_annual_' . md5($source) . '.xml';
        $this->load($source);
    }

    private function load(string $source): void
    {
        $exists = is_file($this->cacheFile);

        if (!$exists || !$this->isFresh()) {
            try {
                $this->download($source);
            } catch (RuntimeException $e) {
                if (!$exists) {
                    throw $e;
                }
            }
        }

        try {
            $this->parse($this->cacheFile);
        } catch (RuntimeException $e) {
            $this->download($source);
            $this->parse($this->cacheFile);
        }
    }

    private function isFresh(): bool
    {
        $mtime = @filemtime($this->cacheFile);
        return $mtime !== false && (time() - $mtime) < self::CACHE_TTL;
    }

    private function download(string $source): void
    {
        $xmlText = $this->fetch($source);
        $tmp = $this->cacheFile . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $xmlText) === false || !@rename($tmp, $this->cacheFile)) {
            @unlink($tmp);
            throw new RuntimeException("Unable to write {$this->cacheFile}");
        }
    }

    private function parse(string $file): void
    {
        $this->rates = [];

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
                $multiplier = isset($attrs['multiplier']) ? (int) $attrs['multiplier'] : 1;
                $dayRates[$currency] = [
                    'value' => (float) (string) $rate,
                    'multiplier' => $multiplier,
                ];
            }
            $this->rates[$date] = $dayRates;
        }

        ksort($this->rates);
    }

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

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    /** Date the annual XML dataset was published (Y-m-d). */
    public function getPublishingDate(): string
    {
        return $this->publishingDate;
    }

    /** Latest date with available rates (Y-m-d). */
    public function getDate(): string
    {
        $dates = array_keys($this->rates);
        return $dates ? $dates[count($dates) - 1] : '';
    }

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

    /** Convert an amount between two currencies (RON included) for a date. */
    public function convert(string $date, float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $inRon = $from === $this->origCurrency ? $amount : $amount * $this->getRate($date, $from);
        return $to === $this->origCurrency ? $inRon : $inRon / $this->getRate($date, $to);
    }

    /** @return array<string, array<string, array{value: float, multiplier: int}>> */
    public function getAll(): array
    {
        return $this->rates;
    }

    /** @return array<string, array{value: float, multiplier: int}> */
    public function getRates(?string $date = null): array
    {
        return $this->rates[$this->dateEntry($date)];
    }

    private function dateEntry(?string $date): string
    {
        $date ??= $this->getDate();
        if (!isset($this->rates[$date])) {
            throw new InvalidArgumentException("Unknown rate date: $date");
        }
        return $date;
    }

    /** @return array{value: float, multiplier: int} */
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
