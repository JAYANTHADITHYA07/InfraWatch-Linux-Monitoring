<?php
$dataDir = dirname(__DIR__) . '/data';
$files = glob($dataDir . '/*.json') ?: [];
$servers = [];

foreach ($files as $file) {
    $raw = file_get_contents($file);
    $item = json_decode($raw, true);

    if (is_array($item)) {
        $item['_label'] = basename($file, '.json');
        $servers[] = $item;
    }
}

function esc($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function metric($server, $key): string {
    return isset($server[$key]) ? esc($server[$key]) : '—';
}

function statusClass($status): string {
    $s = strtoupper((string)$status);

    if ($s === 'NORMAL' || $s === 'HEALTHY') {
        return 'normal';
    }

    if ($s === 'WARNING') {
        return 'warning';
    }

    return 'critical';
}

$count = ['NORMAL' => 0, 'WARNING' => 0, 'CRITICAL' => 0, 'UNREACHABLE' => 0];

foreach ($servers as $s) {
    $state = strtoupper((string)($s['status'] ?? 'UNKNOWN'));

    if ($state === 'UNREACHABLE') {
        $count['UNREACHABLE']++;
    } elseif (isset($count[$state])) {
        $count[$state]++;
    } else {
        $count['CRITICAL']++;
    }
}

$lastUpdated = $servers
    ? max(array_map(fn($s) => (string)($s['collected_at'] ?? ''), $servers))
    : 'No collection data yet';

$grafanaUrl = '/grafana/d/adz7mrb/infrawatch-linux-infrastructure-monitoring?orgId=1&kiosk';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>InfraWatch | Linux Monitoring</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-mark">IW</span>
            <div>
                <strong>InfraWatch</strong>
                <small>LINUX OPERATIONS</small>
            </div>
        </div>

        <div class="nav-label">WORKSPACE</div>
        <a class="nav-item active" href="#overview"><span>▦</span> Overview</a>
        <a class="nav-item" href="#servers"><span>▤</span> Servers</a>
        <a class="nav-item" href="#incidents"><span>⚑</span> Incidents</a>
        <a class="nav-item" href="#grafana"><span>▥</span> Grafana Monitoring</a>

        <div class="sidebar-bottom">
            <span class="pulse"></span> Monitoring workspace<br>
            <small>Local VirtualBox lab</small>
        </div>
    </aside>

    <main class="main-content">
        <section id="overview">
            <header class="topbar">
                <div>
                    <div class="eyebrow">INFRASTRUCTURE / OVERVIEW</div>
                    <h1>Infrastructure overview</h1>
                    <p>Linux server health and incident visibility.</p>
                </div>

                <div class="top-actions">
                    <span class="live-pill"><i></i> LAB ENVIRONMENT</span>
                    <span class="updated">Updated: <?= esc($lastUpdated) ?></span>
                    <a class="refresh-link" href="index.php">Refresh snapshots</a>
                </div>
            </header>

            <section class="summary-grid">
                <article class="summary-card">
                    <div class="summary-head">MONITORED SERVERS <span>▤</span></div>
                    <div class="summary-value"><?= count($servers) ?></div>
                    <div class="summary-foot">Configured hosts</div>
                </article>

                <article class="summary-card">
                    <div class="summary-head">HEALTHY <span class="dot normal-bg"></span></div>
                    <div class="summary-value green"><?= $count['NORMAL'] ?></div>
                    <div class="summary-foot">Within thresholds</div>
                </article>

                <article class="summary-card">
                    <div class="summary-head">WARNING <span class="dot warning-bg"></span></div>
                    <div class="summary-value amber"><?= $count['WARNING'] ?></div>
                    <div class="summary-foot">Needs attention</div>
                </article>

                <article class="summary-card">
                    <div class="summary-head">CRITICAL / OFFLINE <span class="dot critical-bg"></span></div>
                    <div class="summary-value red"><?= $count['CRITICAL'] + $count['UNREACHABLE'] ?></div>
                    <div class="summary-foot">Requires investigation</div>
                </article>
            </section>
        </section>

        <section class="section-heading" id="servers">
            <div>
                <div class="eyebrow">FLEET HEALTH</div>
                <h2>Server inventory</h2>
            </div>
            <span class="section-note">Values from the latest collected snapshots</span>
        </section>

        <?php if (!$servers): ?>
            <div class="empty-state">
                <div class="empty-icon">⌁</div>
                <h3>Waiting for server data</h3>
                <p>Run <code>scripts/collect_all.sh</code> after configuring SSH and hosts.conf. Server snapshots will appear here.</p>
            </div>
        <?php else: ?>
            <div class="server-grid">
                <?php foreach ($servers as $s):
                    $status = strtoupper((string)($s['status'] ?? 'UNKNOWN'));
                    $cls = statusClass($status);
                ?>
                    <article class="server-card">
                        <div class="server-top">
                            <div class="server-icon">⌘</div>
                            <span class="status-badge <?= $cls ?>"><i></i><?= esc($status) ?></span>
                        </div>

                        <h3><?= metric($s, 'hostname') !== '—' ? metric($s, 'hostname') : esc($s['_label']) ?></h3>
                        <div class="server-sub"><?= metric($s, 'os') ?></div>

                        <div class="server-meta">
                            <span>IP ADDRESS</span>
                            <strong><?= metric($s, 'ips') ?></strong>
                        </div>

                        <div class="metrics-list">
                            <?php foreach ([['CPU', 'cpu_percent'], ['Memory', 'memory_percent'], ['Root filesystem', 'disk_root_percent'], ['/var filesystem', 'disk_var_percent']] as [$label, $key]):
                                $v = (float)($s[$key] ?? 0);
                                $barClass = $v >= 90 ? 'bar-red' : ($v >= 80 ? 'bar-amber' : '');
                            ?>
                                <div class="metric-row">
                                    <div class="metric-label">
                                        <span><?= esc($label) ?></span>
                                        <strong><?= isset($s[$key]) ? esc($s[$key]) . '%' : '—' ?></strong>
                                    </div>
                                    <div class="progress">
                                        <span class="<?= $barClass ?>" style="width:<?= max(0, min(100, $v)) ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="server-footer">
                            <span>Uptime <b><?= metric($s, 'uptime_days') ?> days</b></span>
                            <span>Failed services <b><?= metric($s, 'failed_services') ?></b></span>
                        </div>

                        <?php if (!empty($s['status_reason'])): ?>
                            <p class="status-reason"><?= metric($s, 'status_reason') ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="section-heading incidents-heading" id="incidents">
            <div>
                <div class="eyebrow">EVENT MANAGEMENT</div>
                <h2>Current incidents</h2>
            </div>
            <span class="section-note">Derived from latest snapshots</span>
        </section>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>HOST</th>
                        <th>CONDITION</th>
                        <th>SEVERITY</th>
                        <th>LAST CHECK</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasIncident = false;

                    foreach ($servers as $s):
                        $st = strtoupper((string)($s['status'] ?? ''));

                        if ($st === 'NORMAL' || $st === 'HEALTHY') {
                            continue;
                        }

                        $hasIncident = true;
                    ?>
                        <tr>
                            <td class="host-cell"><?= metric($s, 'hostname') !== '—' ? metric($s, 'hostname') : esc($s['_label']) ?></td>
                            <td><?= metric($s, 'status_reason') ?></td>
                            <td><span class="status-badge <?= statusClass($st) ?>"><i></i><?= esc($st) ?></span></td>
                            <td><?= metric($s, 'collected_at') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$hasIncident): ?>
                        <tr>
                            <td colspan="4" class="no-incidents">No incidents detected in the available snapshots.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <section class="grafana-section" id="grafana">
            <div class="section-heading grafana-heading">
                <div>
                    <div class="eyebrow">METRICS & OBSERVABILITY</div>
                    <h2>Grafana Monitoring</h2>
                </div>
                <a class="open-grafana" href="<?= esc($grafanaUrl) ?>" target="_blank" rel="noopener noreferrer">Open Grafana in new tab ↗</a>
            </div>

            <div class="grafana-frame-wrap">
                <iframe
                    class="grafana-frame"
                    src="<?= esc($grafanaUrl) ?>"
                    title="InfraWatch Grafana monitoring dashboard"
                    loading="lazy"
                    allowfullscreen>
                </iframe>
            </div>
            <p class="grafana-note">
                Live metrics are provided by Grafana. If the login screen does not appear or the frame is blocked,
                verify Grafana's embedding and browser cookie settings. You can also use “Open Grafana in new tab”.
            </p>
        </section>

        <footer>InfraWatch · Lab monitoring only · Values are collected from configured Linux hosts.</footer>
    </main>
</div>
</body>
</html>
