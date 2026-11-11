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
 * Displays the latest exchange rates published by the BNR.
 */
require __DIR__ . '/CursValutarBNR.php';

/**
 * Escapes a string for safe insertion into HTML.
 *
 * @param string $s String to escape.
 * @return string HTML-escaped string.
 */
function h(string $s): string
{
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Maps currency codes to flag-icons country codes; XAU and XDR have no country flag.
 */
const CURRENCY_FLAGS = [
	'AED' => 'ae', 'AUD' => 'au', 'BRL' => 'br', 'CAD' => 'ca', 'CHF' => 'ch',
	'CNY' => 'cn', 'CZK' => 'cz', 'DKK' => 'dk', 'EGP' => 'eg', 'EUR' => 'eu',
	'GBP' => 'gb', 'HKD' => 'hk', 'HUF' => 'hu', 'IDR' => 'id', 'ILS' => 'il',
	'INR' => 'in', 'ISK' => 'is', 'JPY' => 'jp', 'KRW' => 'kr', 'MDL' => 'md',
	'MXN' => 'mx', 'MYR' => 'my', 'NOK' => 'no', 'NZD' => 'nz', 'PHP' => 'ph',
	'PLN' => 'pl', 'RSD' => 'rs', 'RUB' => 'ru', 'SEK' => 'se', 'SGD' => 'sg',
	'THB' => 'th', 'TRY' => 'tr', 'UAH' => 'ua', 'USD' => 'us', 'ZAR' => 'za',
];

$error = null;
$bnr = null;
try {
	$bnr = new CursValutarBNR();
} catch (Throwable $e) {
	http_response_code(503);
	$error = 'Cursul valutar nu este disponibil momentan. Va rugam reveniti.';
}
?>
<!DOCTYPE html>
<html lang="ro">
	<head>
		<meta charset="utf-8">
		<title>Curs Valutar (Banca Națională a României)</title>
		<link rel="icon" type="image/png" href="romania-flag-square-icon-256.png">
		<link rel="apple-touch-icon" href="romania-flag-square-icon-256.png">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
		<link href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.3.2/css/flag-icons.min.css" rel="stylesheet">
		<style type="text/css">
			body { display: flex; flex-direction: column; min-height: 100vh; }
			header, footer { background: black; color: white; padding: 1rem; text-align: center; position: sticky; z-index: 1000; }
			header { top: 0; }
			footer { bottom: 0; }
			main { flex: 1; padding: 1rem; }
		</style>
	</head>
	<body>
		<header>
			<strong>Curs Valutar (Banca Națională a României)</strong>
		</header>
		<main class="container">
			<?php if ($error !== null): ?>
				<div class="alert alert-danger text-center"><?= h($error) ?></div>
			<?php else: ?>
				<p class="text-center">
					Cursul de referinta la data de <strong><?= h($bnr->getDate()) ?></strong>
					(valoarea in <?= h($bnr->getOrigCurrency()) ?> pentru multiplicatorul indicat)
				</p>
				<div class="table-responsive">
					<table class="table table-striped table-hover w-auto mx-auto">
						<thead>
							<tr>
								<th>Moneda</th>
								<th class="text-end">Multiplicator</th>
								<th class="text-end">Curs (<?= h($bnr->getOrigCurrency()) ?>)</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($bnr->getAll() as $currency => $rate): ?>
								<tr>
									<td>
										<?php if (isset(CURRENCY_FLAGS[$currency])): ?>
											<span class="fi fi-<?= h(CURRENCY_FLAGS[$currency]) ?> me-2"></span>
										<?php else: ?>
											<span class="fi me-2"></span>
										<?php endif; ?>
										<?= h($currency) ?>
									</td>
									<td class="text-end"><?= (int) $rate['multiplier'] ?></td>
									<td class="text-end"><?= number_format($rate['value'], 4, ',', '.') ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="text-center text-muted small">Sursa: <?= h($bnr->getPublisher()) ?></p>
			<?php endif; ?>
			<p class="text-center"><a href="CursulMediuValutar.php">Cursuri medii lunare EUR, USD și GBP</a></p>
		</main>
		<footer>
			Website dezvoltat de <a href="https://www.moga.doctor/" target="_blank">Stefan-Mihai MOGA</a>
		</footer>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
	</body>
</html>
