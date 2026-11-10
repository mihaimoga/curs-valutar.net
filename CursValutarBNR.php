<?php

/**
 * Reads exchange rates published by the National Bank of Romania
 * from https://curs.bnr.ro/nbrfxrates.xml
 *
 * Usage:
 *   $bnr = new CursValutarBNR();
 *   echo $bnr->getRate('EUR');      // RON per 1 EUR
 *   echo $bnr->getDate();           // 2026-11-10
 */
class CursValutarBNR
{
    public const URL = 'https://curs.bnr.ro/nbrfxrates.xml';
    private const NS = 'https://www.bnr.ro/xsd';

    private string $publisher = '';
    private string $date = '';
    private string $origCurrency = 'RON';
    /** @var array<string, array{value: float, multiplier: int}> */
    private array $rates = [];

    public const CACHE_TTL = 3600; // 1 hour

    private string $cacheFile;

    /**
     * @param string|null $source   URL or file path; defaults to the BNR feed.
     * @param string|null $cacheDir Directory for the cached XML; defaults to the system temp dir.
     * @throws RuntimeException on download or parse failure.
     */
    public function __construct(?string $source = null, ?string $cacheDir = null)
    {
        $source ??= self::URL;
        $cacheDir = rtrim($cacheDir ?? sys_get_temp_dir(), '/\\');
        $this->cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'bnr_' . md5($source) . '.xml';
        $this->load($source);
    }

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

    /** Reads the values from the local XML file. */
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

        $orig = $xml->xpath('//b:Body/b:OrigCurrency');
        $this->origCurrency = $orig ? (string) $orig[0] : 'RON';

        $cube = $xml->xpath('//b:Body/b:Cube');
        if (!$cube) {
            throw new RuntimeException('No Cube element found in BNR XML.');
        }
        $this->date = (string) $cube[0]['date'];

        foreach ($cube[0]->children(self::NS) as $rate) {
            $attrs = $rate->attributes();
            $currency = (string) $attrs['currency'];
            $multiplier = isset($attrs['multiplier']) ? (int) $attrs['multiplier'] : 1;
            $this->rates[$currency] = [
                'value' => (float) (string) $rate,
                'multiplier' => $multiplier,
            ];
        }
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
            throw new RuntimeException("Unable to read BNR rates from $source");
        }
        return $data;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    /** Publishing date (Y-m-d). */
    public function getDate(): string
    {
        return $this->date;
    }

    public function getOrigCurrency(): string
    {
        return $this->origCurrency;
    }

    /** @return string[] Available currency codes. */
    public function getCurrencies(): array
    {
        return array_keys($this->rates);
    }

    public function hasCurrency(string $currency): bool
    {
        return isset($this->rates[strtoupper($currency)]);
    }

    /**
     * Raw published value (RON per $multiplier units of currency).
     */
    public function getRawRate(string $currency): float
    {
        return $this->entry($currency)['value'];
    }

    public function getMultiplier(string $currency): int
    {
        return $this->entry($currency)['multiplier'];
    }

    /**
     * Normalized rate: RON per 1 unit of currency.
     */
    public function getRate(string $currency): float
    {
        $e = $this->entry($currency);
        return $e['value'] / $e['multiplier'];
    }

    /**
     * Convert an amount between two currencies (RON included).
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $inRon = $from === $this->origCurrency ? $amount : $amount * $this->getRate($from);
        return $to === $this->origCurrency ? $inRon : $inRon / $this->getRate($to);
    }

    /** @return array<string, array{value: float, multiplier: int}> */
    public function getAll(): array
    {
        return $this->rates;
    }

    private function entry(string $currency): array
    {
        $currency = strtoupper($currency);
        if (!isset($this->rates[$currency])) {
            throw new InvalidArgumentException("Unknown currency: $currency");
        }
        return $this->rates[$currency];
    }
}
