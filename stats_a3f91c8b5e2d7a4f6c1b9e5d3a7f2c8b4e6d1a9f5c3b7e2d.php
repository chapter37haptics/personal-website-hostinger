<?php
require_once(__DIR__ . '/../db_config.php');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die('<h2 style="font-family:monospace;color:red">DB connection failed: ' . htmlspecialchars($e->getMessage()) . '</h2>');
}

// Aggregates
$total      = $pdo->query('SELECT COUNT(*) FROM visits')->fetchColumn();
$unique_ips = $pdo->query('SELECT COUNT(DISTINCT ip) FROM visits')->fetchColumn();
$today      = $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visited_at) = CURDATE()")->fetchColumn();

function top_breakdown(PDO $pdo, string $col, int $limit = 10): array {
    $col_safe = preg_replace('/[^a-z_]/', '', $col); // whitelist
    $stmt = $pdo->query(
        "SELECT COALESCE({$col_safe}, 'Unknown') AS label, COUNT(*) AS cnt
         FROM visits GROUP BY label ORDER BY cnt DESC LIMIT {$limit}"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$by_country = top_breakdown($pdo, 'country');
$by_city    = top_breakdown($pdo, 'city');
$by_device  = top_breakdown($pdo, 'device_type');
$by_browser = top_breakdown($pdo, 'browser');
$by_os      = top_breakdown($pdo, 'os');
$by_page    = top_breakdown($pdo, 'page');

// Last 50 visits
$recent = $pdo->query(
    'SELECT visited_at, page, referrer, country, city, device_type, browser, os
     FROM visits ORDER BY visited_at DESC LIMIT 50'
)->fetchAll(PDO::FETCH_ASSOC);

function bar(int $val, int $max): string {
    $pct = $max > 0 ? round(($val / $max) * 160) : 0;
    return '<span style="display:inline-block;height:12px;background:#4ade80;width:' . $pct . 'px;border-radius:2px;vertical-align:middle;margin-right:6px"></span>';
}

function breakdown_table(array $rows, string $title): void {
    if (!$rows) return;
    $max = (int)$rows[0]['cnt'];
    echo "<h3 style='margin-top:2rem;margin-bottom:.5rem'>{$title}</h3>";
    echo '<table><tr><th>Label</th><th>Visits</th><th>Bar</th></tr>';
    foreach ($rows as $r) {
        $label = htmlspecialchars($r['label']);
        $cnt   = (int)$r['cnt'];
        echo "<tr><td>{$label}</td><td>{$cnt}</td><td>" . bar($cnt, $max) . "</td></tr>";
    }
    echo '</table>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Analytics — adnanqzs.me</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; font-size: 14px; }
  h1 { color: #4ade80; margin-bottom: 1.5rem; font-size: 1.4rem; }
  h3 { color: #94a3b8; font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; }
  .stats-grid { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
  .stat-card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.2rem 2rem; min-width: 140px; }
  .stat-card .num { font-size: 2rem; color: #4ade80; font-weight: bold; }
  .stat-card .lbl { color: #64748b; font-size: .8rem; margin-top: .2rem; }
  table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; margin-bottom: 1rem; }
  th, td { padding: .5rem .8rem; text-align: left; border-bottom: 1px solid #334155; }
  th { background: #0f172a; color: #64748b; font-size: .75rem; text-transform: uppercase; }
  tr:last-child td { border-bottom: none; }
  .breakdowns { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem; }
  .breakdown-section { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1rem; }
  .breakdown-section h3 { margin-bottom: .75rem; }
  .breakdown-section table { background: transparent; }
  .recent-section { margin-top: 2rem; }
  .recent-section table { font-size: .8rem; }
  .ts { color: #64748b; white-space: nowrap; }
  a { color: #38bdf8; text-decoration: none; }
  @media (max-width: 600px) { body { padding: 1rem; } .stats-grid { flex-direction: column; } }
</style>
</head>
<body>
<h1>Analytics — adnanqzs.me</h1>

<div class="stats-grid">
  <div class="stat-card"><div class="num"><?= number_format((int)$total) ?></div><div class="lbl">Total Visits</div></div>
  <div class="stat-card"><div class="num"><?= number_format((int)$unique_ips) ?></div><div class="lbl">Unique IPs</div></div>
  <div class="stat-card"><div class="num"><?= number_format((int)$today) ?></div><div class="lbl">Visits Today</div></div>
</div>

<div class="breakdowns">

<div class="breakdown-section">
<?php breakdown_table($by_country, 'Country'); ?>
</div>

<div class="breakdown-section">
<?php breakdown_table($by_city, 'City'); ?>
</div>

<div class="breakdown-section">
<?php breakdown_table($by_device, 'Device'); ?>
</div>

<div class="breakdown-section">
<?php breakdown_table($by_browser, 'Browser'); ?>
</div>

<div class="breakdown-section">
<?php breakdown_table($by_os, 'OS'); ?>
</div>

<div class="breakdown-section">
<?php breakdown_table($by_page, 'Page'); ?>
</div>

</div>

<div class="recent-section">
<h3 style="margin-bottom:.75rem">Last 50 Visits</h3>
<table>
<tr>
  <th>Time (UTC)</th>
  <th>Page</th>
  <th>Referrer</th>
  <th>Country</th>
  <th>City</th>
  <th>Device</th>
  <th>Browser</th>
  <th>OS</th>
</tr>
<?php foreach ($recent as $r): ?>
<tr>
  <td class="ts"><?= htmlspecialchars($r['visited_at']) ?></td>
  <td><?= htmlspecialchars($r['page'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['referrer'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['country'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['city'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['device_type'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['browser'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['os'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<p style="margin-top:2rem;color:#334155;font-size:.75rem">Generated <?= date('Y-m-d H:i:s') ?> UTC</p>
</body>
</html>
