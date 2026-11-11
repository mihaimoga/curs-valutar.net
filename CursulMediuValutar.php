<?php
require __DIR__ . '/CursValutarAnual.php';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = null;
$anual = null;
$monthlyAverages = [];
$year = '';
$currencies = ['EUR', 'USD', 'GBP'];

try {
    $anual = new CursValutarAnual();
    $dates = $anual->getDates();
    $year = substr($anual->getDate(), 0, 4);

    foreach ($dates as $date) {
        $month = substr($date, 5, 2);
        foreach ($currencies as $currency) {
            if (!$anual->hasCurrency($date, $currency)) {
                continue;
            }

            $monthlyAverages[$month][$currency]['sum'] =
                ($monthlyAverages[$month][$currency]['sum'] ?? 0) + $anual->getRate($date, $currency);
            $monthlyAverages[$month][$currency]['count'] =
                ($monthlyAverages[$month][$currency]['count'] ?? 0) + 1;
        }
    }
} catch (Throwable $e) {
    http_response_code(503);
    $error = 'Cursul valutar nu este disponibil momentan. Va rugam reveniti.';
}

$months = [
    '01' => 'Ianuarie',
    '02' => 'Februarie',
    '03' => 'Martie',
    '04' => 'Aprilie',
    '05' => 'Mai',
    '06' => 'Iunie',
    '07' => 'Iulie',
    '08' => 'August',
    '09' => 'Septembrie',
    '10' => 'Octombrie',
    '11' => 'Noiembrie',
    '12' => 'Decembrie',
];
?>
<!DOCTYPE html>
<html lang="ro">
	<head>
		<meta charset="utf-8">
		<title>Cursuri medii lunare EUR, USD și GBP (BNR)</title>
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
			<strong>Cursuri medii lunare EUR, USD și GBP (Banca Națională a României)</strong>
		</header>
		<main class="container">
			<?php if ($error !== null): ?>
				<div class="alert alert-danger text-center"><?= h($error) ?></div>
			<?php else: ?>
				<p class="text-center">
					Cursurile medii lunare EUR, USD și GBP pentru anul <strong><?= h($year) ?></strong>,
					calculate ca media cursurilor publicate de BNR în fiecare lună.
				</p>
				<div class="table-responsive">
					<table class="table table-striped table-hover w-auto mx-auto">
						<thead>
							<tr>
								<th>Luna</th>
								<?php foreach ($currencies as $currency): ?>
									<th class="text-center"><?= h($currency) ?></th>
								<?php endforeach; ?>
							</tr>
							<tr>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($months as $month => $name): ?>
								<tr>
									<td><?= h($name) ?></td>
									<?php foreach ($currencies as $currency): ?>
										<?php
										$count = $monthlyAverages[$month][$currency]['count'] ?? 0;
										$average = $count > 0 ? $monthlyAverages[$month][$currency]['sum'] / $count : null;
										?>
										<td class="text-end"><?= $average !== null ? number_format($average, 4, ',', '.') : '—' ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="text-center text-muted small">Sursa: <?= h($anual->getPublisher()) ?></p>
			<?php endif; ?>
		</main>
		<footer>
			Website dezvoltat de <a href="https://www.moga.doctor/" target="_blank">Stefan-Mihai MOGA</a>
		</footer>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3v7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
	</body>
</html>
