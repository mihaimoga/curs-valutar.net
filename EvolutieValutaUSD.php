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
 * Displays the USD/RON rate for the latest ten days and its variation from
 * the previous published rate.
 */
require __DIR__ . '/CursValutar10days.php';

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

$error = null;
$bnr = null;
$evolution = [];

try {
	$bnr = new CursValutar10days();
	$dates = $bnr->getDates();
	$previousRate = null;

	foreach ($dates as $date) {
		if (!$bnr->hasCurrency($date, 'USD')) {
			continue;
		}

		$rate = $bnr->getRate($date, 'USD');
		$variation = $previousRate === null ? null : $rate - $previousRate;
		$evolution[] = [
			'date' => $date,
			'rate' => $rate,
			'variation' => $variation,
			'percentage' => $previousRate !== null && $previousRate != 0
				? ($variation / $previousRate) * 100
				: null,
		];
		$previousRate = $rate;
	}

	if (!$evolution) {
		throw new RuntimeException('No USD rates found in BNR 10-day feed.');
	}
} catch (Throwable $e) {
	http_response_code(503);
	$error = 'Cursul valutar nu este disponibil momentan. Va rugam reveniti.';
}
?>
<!DOCTYPE html>
<html lang="ro">
	<head>
		<meta charset="utf-8">
		<title>Evoluția cursului USD/RON (BNR)</title>
		<link rel="icon" type="image/png" href="romania-flag-square-icon-256.png">
		<link rel="apple-touch-icon" href="romania-flag-square-icon-256.png">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
			<strong>Evoluția cursului USD/RON (Banca Națională a României)</strong>
		</header>
		<main class="container">
			<?php if ($error !== null): ?>
				<div class="alert alert-danger text-center"><?= h($error) ?></div>
			<?php else: ?>
				<p class="text-center">
					Cursul USD/RON din ultimele 10 zile și variația față de cursul publicat anterior.
				</p>
				<div class="table-responsive">
					<table class="table table-striped table-hover w-auto mx-auto">
						<thead>
							<tr>
								<th>Data</th>
								<th class="text-end">USD (RON)</th>
								<th class="text-end">Variație (RON)</th>
								<th class="text-end">Variație (%)</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach (array_reverse($evolution) as $entry): ?>
								<?php
								$variation = $entry['variation'];
								$variationClass = $variation === null || $variation == 0
									? 'text-muted'
									: ($variation > 0 ? 'text-success' : 'text-danger');
								?>
								<tr>
									<td><?= h($entry['date']) ?></td>
									<td class="text-end"><?= number_format($entry['rate'], 4, ',', '.') ?></td>
									<td class="text-end <?= $variationClass ?>">
										<?= $variation === null ? '—' : ($variation > 0 ? '+' : '') . number_format($variation, 4, ',', '.') ?>
									</td>
									<td class="text-end <?= $variationClass ?>">
										<?= $entry['percentage'] === null ? '—' : ($entry['percentage'] > 0 ? '+' : '') . number_format($entry['percentage'], 2, ',', '.') . '%' ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="text-center text-muted small">Sursa: <?= h($bnr->getPublisher()) ?></p>
			<?php endif; ?>
            <p class="text-center"><a href="CursulMediuValutar.php">Cursuri medii lunare EUR, USD și GBP</a> | <a href="EvolutieValutaEUR.php">Evoluție EUR/RON</a> | <a href="EvolutieValutaUSD.php">Evoluție USD/RON</a> | <a href="EvolutieValutaGBP.php">Evoluție GBP/RON</a></p>
		</main>
		<footer>
			Website dezvoltat de <a href="https://www.moga.doctor/" target="_blank">Stefan-Mihai MOGA</a>
		</footer>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9JcYn3v7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
	</body>
</html>
