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

require_once __DIR__ . '/CursValutarAnual.php';

/**
 * Reads the latest ten days of exchange rates published by the National Bank
 * of Romania from https://curs.bnr.ro/nbrfxrates10days.xml.
 *
 * Usage:
 *   $rates = new CursValutar10days();
 *   echo $rates->getRate('2026-11-10', 'EUR'); // RON per 1 EUR
 *   echo $rates->getDate();                    // Latest available date
 */
class CursValutar10days extends CursValutarAnual
{
	public const URL = 'https://curs.bnr.ro/nbrfxrates10days.xml';

	/**
	 * @param string|null $source   URL or file path; defaults to the BNR 10-day feed.
	 * @param string|null $cacheDir Directory for the cached XML; defaults to the system temp dir.
	 * @throws RuntimeException on download or parse failure.
	 */
	public function __construct(?string $source = null, ?string $cacheDir = null)
	{
		parent::__construct($source ?? self::URL, $cacheDir);
	}
}
