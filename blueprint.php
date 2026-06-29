<?php
/**
 * Bus & Truck Body Build — Production System Blueprint
 * A login-gated, editable blueprint the client reviews and annotates.
 * Client input is saved to data/blueprint_input.json (and appended to history).
 */
session_start();

// ---- users (override via secret.php) ----
$users = [
    ['user' => 'tito', 'pass' => 'CHANGE_ME_DEV', 'name' => 'Tito (developer)', 'role' => 'admin'],
    ['user' => 'ann',  'pass' => 'CHANGE_ME_ANN', 'name' => 'Ann',             'role' => 'editor'],
];
if (is_file(__DIR__ . '/secret.php')) {
    $s = require __DIR__ . '/secret.php';
    if (is_array($s) && !empty($s['users']) && is_array($s['users'])) $users = $s['users'];
}

$DATA_DIR  = __DIR__ . '/data';
$DATA_FILE = $DATA_DIR . '/blueprint_input.json';
$HIST_FILE = $DATA_DIR . '/blueprint_history.jsonl';
$ACT_FILE  = $DATA_DIR . '/activity.jsonl';
$CHG_FILE  = $DATA_DIR . '/changes.jsonl';

/** Append one JSON line to a log file. */
function bp_log($file, array $entry) {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
}
function bp_ip() { return $_SERVER['REMOTE_ADDR'] ?? ''; }

/** Friendly label for a field key. */
function bp_field_label($k) {
    static $m = [
        'client_name' => 'Business / shop name', 'reviewer' => 'Reviewer name & role',
        'missed' => 'Extra notes', 'signoff_v' => 'Overall sign-off', 'signoff_date' => 'Sign-off date',
        'ph1_v' => 'Step 1 · Sales & order', 'ph1_note' => 'Step 1 note',
        'ph2_v' => 'Step 2 · Job card & start', 'ph2_note' => 'Step 2 note',
        'ph3_v' => 'Step 3 · Procurement', 'ph3_note' => 'Step 3 note',
        'ph4_v' => 'Step 4 · Production floor', 'ph4_note' => 'Step 4 note',
        'ph5_v' => 'Step 5 · Quality gates', 'ph5_note' => 'Step 5 note',
        'ph6_v' => 'Step 6 · Delivery & finance', 'ph6_note' => 'Step 6 note',
        'd1_v' => 'Decision · job card on invoice', 'd1_note' => 'Decision 1 note',
        'd2_v' => 'Decision · enforced QC gates', 'd2_note' => 'Decision 2 note',
        'd3_v' => 'Decision · Zoho v1 scope', 'd3_note' => 'Decision 3 note',
        'd4_v' => 'Decision · bus + truck', 'd4_note' => 'Decision 4 note',
    ];
    return $m[$k] ?? $k;
}
function bp_verdict($v) { return $v === 'approve' ? 'Looks right / Agree' : ($v === 'change' ? 'Needs a change' : ($v === '' ? '(blank)' : $v)); }

/** Build a human-readable list of changes between two saved blueprint states. */
function bp_changes($old, $new) {
    $old = is_array($old) ? $old : []; $new = is_array($new) ? $new : [];
    $out = [];
    $of = $old['fields'] ?? []; $nf = $new['fields'] ?? [];
    foreach (array_keys($nf + $of) as $k) {
        $ov = (string)($of[$k] ?? ''); $nv = (string)($nf[$k] ?? '');
        if ($ov === $nv) continue;
        $label = bp_field_label($k);
        if (substr($k, -2) === '_v') $out[] = "$label — " . bp_verdict($ov) . " → " . bp_verdict($nv);
        elseif ($ov === '')         $out[] = "$label — filled in";
        elseif ($nv === '')         $out[] = "$label — cleared";
        else                        $out[] = "$label — edited";
    }
    foreach (['busStages' => 'Bus', 'truckStages' => 'Truck'] as $key => $lbl) {
        $os = $old[$key] ?? []; $ns = $new[$key] ?? [];
        $n = max(count($os), count($ns));
        for ($i = 0; $i < $n; $i++) {
            $o = $os[$i] ?? null; $z = $ns[$i] ?? null;
            if ($o === null && $z !== null) { $out[] = "$lbl section added: " . ($z['name'] ?: '(unnamed)'); continue; }
            if ($z === null && $o !== null) { $out[] = "$lbl section removed: " . ($o['name'] ?: '(unnamed)'); continue; }
            if ($o && $z) {
                if (($o['name'] ?? '') !== ($z['name'] ?? '')) $out[] = "$lbl section " . ($i + 1) . " renamed: '" . ($o['name'] ?? '') . "' → '" . ($z['name'] ?? '') . "'";
                if (!!($o['qc'] ?? false) !== !!($z['qc'] ?? false)) $out[] = "$lbl section '" . ($z['name'] ?? '') . "' QC gate " . (($z['qc'] ?? false) ? 'turned ON' : 'turned OFF');
                if (($o['notes'] ?? '') !== ($z['notes'] ?? '')) $out[] = "$lbl section '" . ($z['name'] ?? '') . "' description edited";
            }
        }
    }
    return $out;
}

/** Identity Auto logo: real logo.png if present, otherwise a vector recreation. */
function identity_logo($class = '') {
    $c = $class ? ' class="' . $class . '"' : '';
    if (is_file(__DIR__ . '/logo.png')) {
        return '<img src="logo.png" alt="Identity Auto Fabricators Limited"' . $c . '>';
    }
    return '<svg' . $c . ' viewBox="0 0 300 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Identity Auto Fabricators Limited">'
        . '<rect x="6" y="6" width="288" height="80" rx="12" fill="#ED1C24"/>'
        . '<rect x="8" y="8" width="284" height="76" rx="10" fill="none" stroke="#ffffff" stroke-width="2.5"/>'
        . '<text x="150" y="51" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="800" font-style="italic" font-size="42" fill="#ffffff" letter-spacing="1" textLength="240" lengthAdjust="spacingAndGlyphs">IDENTITY</text>'
        . '<rect x="24" y="60" width="252" height="20" rx="3" fill="#15239B"/>'
        . '<text x="150" y="74.5" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="12" fill="#ffffff" letter-spacing="2" textLength="232" lengthAdjust="spacingAndGlyphs">AUTO FABRICATORS LIMITED</text>'
        . '<text x="150" y="106" text-anchor="middle" font-family="Georgia,\'Times New Roman\',serif" font-style="italic" font-size="15" fill="#111111">Bus, Trucks, &amp; Tipper Fabricators</text>'
        . '</svg>';
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf   = $_SESSION['csrf'];
$action = $_GET['action'] ?? '';
$authed = !empty($_SESSION['bp_auth']);

// ---- logout ----
if ($action === 'logout') {
    if (!empty($_SESSION['bp_auth'])) {
        bp_log($ACT_FILE, ['at' => date('c'), 'user' => $_SESSION['bp_user'] ?? '', 'name' => $_SESSION['bp_name'] ?? '', 'action' => 'logout', 'ip' => bp_ip()]);
    }
    $_SESSION = [];
    session_destroy();
    header('Location: blueprint.php');
    exit;
}

// ---- login ----
$loginError = '';
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inU = (string)($_POST['username'] ?? '');
    $inP = (string)($_POST['password'] ?? '');
    foreach ($users as $u) {
        if (hash_equals((string)$u['user'], $inU) && hash_equals((string)$u['pass'], $inP)) {
            session_regenerate_id(true);
            $_SESSION['bp_auth'] = true;
            $_SESSION['bp_user'] = $u['user'];
            $_SESSION['bp_name'] = $u['name'] ?? $u['user'];
            $_SESSION['bp_role'] = $u['role'] ?? 'editor';
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            bp_log($ACT_FILE, ['at' => date('c'), 'user' => $u['user'], 'name' => $_SESSION['bp_name'], 'action' => 'login', 'ip' => bp_ip()]);
            header('Location: blueprint.php');
            exit;
        }
    }
    bp_log($ACT_FILE, ['at' => date('c'), 'user' => $inU, 'name' => '', 'action' => 'login-failed', 'ip' => bp_ip()]);
    $loginError = 'Wrong username or password.';
}

// ---- save (auth + csrf required) ----
if ($action === 'save') {
    header('Content-Type: application/json');
    if (!$authed) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in again.']); exit; }
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !hash_equals($csrf, (string)($body['csrf'] ?? ''))) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Session expired — reload the page.']); exit;
    }
    if (!is_dir($DATA_DIR)) @mkdir($DATA_DIR, 0775, true);
    $prev = null;
    if (is_file($DATA_FILE)) { $pj = json_decode(file_get_contents($DATA_FILE), true); if (is_array($pj)) $prev = $pj['data'] ?? null; }
    $newData = $body['data'] ?? [];
    $changes = bp_changes($prev, $newData);
    $record = ['saved_at' => date('c'), 'by' => ($_SESSION['bp_name'] ?? 'unknown'), 'data' => $newData];
    file_put_contents($DATA_FILE, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    file_put_contents($HIST_FILE, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    if ($prev === null || $changes) {
        bp_log($CHG_FILE, ['at' => date('c'), 'by' => ($_SESSION['bp_name'] ?? 'unknown'), 'user' => $_SESSION['bp_user'] ?? '', 'first' => ($prev === null), 'changes' => $changes]);
    }
    bp_log($ACT_FILE, ['at' => date('c'), 'user' => $_SESSION['bp_user'] ?? '', 'name' => $_SESSION['bp_name'] ?? '', 'action' => 'save', 'ip' => bp_ip(), 'count' => count($changes)]);
    echo json_encode(['ok' => true, 'saved_at' => $record['saved_at'], 'changes' => count($changes)]);
    exit;
}

// ---- admin logs view ----
if ($action === 'logs') {
    if (empty($_SESSION['bp_auth'])) { header('Location: blueprint.php'); exit; }
    if (($_SESSION['bp_role'] ?? '') !== 'admin') { http_response_code(403); echo 'Forbidden — admins only.'; exit; }

    $activity = [];
    if (is_file($ACT_FILE)) {
        foreach (array_reverse(array_filter(explode("\n", file_get_contents($ACT_FILE)))) as $ln) {
            $e = json_decode($ln, true); if (is_array($e)) $activity[] = $e;
        }
    }
    $saves = [];
    if (is_file($HIST_FILE)) {
        foreach (array_reverse(array_filter(explode("\n", file_get_contents($HIST_FILE)))) as $ln) {
            $e = json_decode($ln, true); if (is_array($e)) $saves[] = $e;
        }
    }
    $changesLog = [];
    if (is_file($CHG_FILE)) {
        foreach (array_reverse(array_filter(explode("\n", file_get_contents($CHG_FILE)))) as $ln) {
            $e = json_decode($ln, true); if (is_array($e)) $changesLog[] = $e;
        }
    }
    $actIcon = ['login' => 'log-in', 'logout' => 'log-out', 'save' => 'save', 'login-failed' => 'shield-alert'];
    function when($iso) { $t = strtotime($iso); return $t ? date('j M Y, g:i:s a', $t) : h($iso); }
    ?><!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity logs — Identity Auto Fabricators</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
      *{box-sizing:border-box} body{margin:0;font-family:'Inter',system-ui,Arial,sans-serif;background:#f4f6fb;color:#16202e;line-height:1.6}
      .wrap{max-width:920px;margin:0 auto;padding:0 18px 60px}
      .top{background:linear-gradient(135deg,#13427e,#1f6feb);color:#fff;padding:26px 0}
      .top .wrap{padding-bottom:0;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
      .top h1{margin:0;font-size:22px} .top a{color:#fff;border:1px solid rgba(255,255,255,.45);padding:7px 13px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:500}
      .card{background:#fff;border:1px solid #e3e8f0;border-radius:16px;box-shadow:0 8px 24px rgba(16,32,55,.06);padding:20px;margin:18px 0}
      h2{font-size:17px;margin:0 0 12px} .muted{color:#5b6b80;font-size:13px}
      table{width:100%;border-collapse:collapse;font-size:14px} th,td{text-align:left;padding:9px 10px;border-bottom:1px solid #eef2f8;vertical-align:top}
      th{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#5b6b80;font-weight:600}
      .tag{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;border-radius:20px;padding:2px 10px}
      .tag .lucide{width:13px;height:13px}
      .tag.login{background:#e6f7f1;color:#0c7a5c} .tag.logout{background:#eef2f8;color:#5b6b80}
      .tag.save{background:#e9f1ff;color:#13427e} .tag.login-failed{background:#fbeaea;color:#b3261e}
      .empty{text-align:center;color:#5b6b80;padding:24px}
      details{margin:6px 0;border:1px solid #eef2f8;border-radius:10px;padding:8px 12px}
      summary{cursor:pointer;font-weight:500;font-size:14px} pre{background:#f7f9fc;border-radius:8px;padding:12px;overflow:auto;font-size:12px;margin:10px 0 0}
      .tl{position:relative;margin:6px 0 0;padding-left:26px}
      .tl::before{content:"";position:absolute;left:8px;top:6px;bottom:6px;width:2px;background:#dfe6f0}
      .ev{position:relative;padding:10px 0 14px}
      .ev::before{content:"";position:absolute;left:-22px;top:14px;width:12px;height:12px;border-radius:50%;background:#1f6feb;border:2px solid #fff;box-shadow:0 0 0 2px #b5d4f4}
      .ev.first::before{background:#0f9d58;box-shadow:0 0 0 2px #c0dd97}
      .ev .head{font-size:14px;font-weight:600} .ev .sub{font-size:12px;color:#5b6b80;margin-bottom:6px}
      .chg{font-size:13px;color:#34465c;padding:4px 0 4px 16px;position:relative}
      .chg::before{content:"›";position:absolute;left:2px;color:#1f6feb;font-weight:700}
      .who{display:inline-block;font-size:12px;font-weight:600;background:#e9f1ff;color:#13427e;border-radius:20px;padding:1px 9px}
    </style></head><body>
    <div class="top"><div class="wrap"><div><div style="background:#fff;border-radius:10px;padding:6px 10px;display:inline-block;margin-bottom:8px"><?= identity_logo('hlogoimg') ?></div><h1>Activity logs</h1></div><a href="blueprint.php">← Back to blueprint</a></div></div>
    <style>.top .hlogoimg{width:150px;height:auto;display:block}</style>
    <div class="wrap">
      <div class="card">
        <h2>Change map (<?= count($changesLog) ?> updates)</h2>
        <p class="muted">A running map of every change to the blueprint, newest first — who changed what, and when.</p>
        <?php if (!$changesLog): ?><div class="empty">No changes yet — the map fills in as the blueprint is edited and saved.</div><?php else: ?>
        <div class="tl">
          <?php foreach ($changesLog as $c): ?>
            <div class="ev<?= !empty($c['first']) ? ' first' : '' ?>">
              <div class="head"><span class="who"><?= h($c['by'] ?? 'unknown') ?></span> &nbsp;<?= !empty($c['first']) ? 'created the first version' : (count($c['changes'] ?? []) . ' change' . (count($c['changes'] ?? []) === 1 ? '' : 's')) ?></div>
              <div class="sub"><?= when($c['at'] ?? '') ?></div>
              <?php foreach (($c['changes'] ?? []) as $line): ?>
                <div class="chg"><?= h($line) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="card">
        <h2>Activity (<?= count($activity) ?>)</h2>
        <p class="muted">Every sign-in, sign-out, save and failed login — newest first.</p>
        <?php if (!$activity): ?><div class="empty">No activity yet.</div><?php else: ?>
        <table><thead><tr><th>When</th><th>Action</th><th>Who</th><th>IP</th></tr></thead><tbody>
        <?php foreach ($activity as $e): $a = $e['action'] ?? ''; ?>
          <tr>
            <td><?= when($e['at'] ?? '') ?></td>
            <td><span class="tag <?= h($a) ?>"><i data-lucide="<?= h($actIcon[$a] ?? 'dot') ?>"></i> <?= h($a) ?></span></td>
            <td><?= h(($e['name'] ?? '') ?: ($e['user'] ?? '')) ?></td>
            <td class="muted"><?= h($e['ip'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
      </div>
      <div class="card">
        <h2>Saved blueprint versions (<?= count($saves) ?>)</h2>
        <p class="muted">Each time someone saves, the full blueprint is stored. Expand to see what was saved.</p>
        <?php if (!$saves): ?><div class="empty">No saves yet.</div><?php else: ?>
        <?php foreach ($saves as $i => $rec): ?>
          <details<?= $i === 0 ? ' open' : '' ?>>
            <summary><?= when($rec['saved_at'] ?? '') ?> — by <?= h($rec['by'] ?? 'unknown') ?><?= $i === 0 ? ' · latest' : '' ?></summary>
            <pre><?= h(json_encode($rec['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
          </details>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>window.lucide&&lucide.createIcons();</script>
    </body></html><?php
    exit;
}

// ---- load saved input ----
$saved = null; $savedAt = null;
if (is_file($DATA_FILE)) {
    $j = json_decode(file_get_contents($DATA_FILE), true);
    if (is_array($j)) { $saved = $j['data'] ?? null; $savedAt = $j['saved_at'] ?? null; }
}

// ---- default content ----
$defaultBus = [
    ['name' => 'Frame',     'qc' => true, 'notes' => 'Cross members, side & roof structure, rear/front fabrication, MIG welding, grinding & alignment'],
    ['name' => 'Sheeting',  'qc' => true, 'notes' => 'Roof/floor/exterior panelling, doors, boot flaps & bumpers, sheet welding, insulation, interior sheeting'],
    ['name' => 'Filler',    'qc' => true, 'notes' => 'Paint preparation, body filler application'],
    ['name' => 'Paint',     'qc' => true, 'notes' => 'Red oxide, primer, main colour, branding, exterior blacks, undercarriage'],
    ['name' => 'Finishing', 'qc' => true, 'notes' => 'Electrical & mechanical accessories, seats & belts, glass, interior trimming, floor & clear paint'],
    ['name' => 'PDI',       'qc' => true, 'notes' => 'Vehicle cleaning, pre-delivery inspection, water test'],
];
$defaultTruck = [
    ['name' => 'Frame',     'qc' => true, 'notes' => 'Sub-frame & floor panels, side/front/roof structure, rear & side doors, MIG welding'],
    ['name' => 'Panelling', 'qc' => true, 'notes' => 'Sheet MIG welding, panelling, u-bolts/stabilizers/propeller, protective covers, toolbox & number plate'],
    ['name' => 'Filler',    'qc' => true, 'notes' => 'Grinding & surface preparation, body filler, red oxide application'],
    ['name' => 'Paint',     'qc' => true, 'notes' => 'Red oxide, body painting & branding, underbody & floor painting'],
    ['name' => 'Finishing', 'qc' => true, 'notes' => 'Chevrons, reflectors & lights'],
    ['name' => 'PDI',       'qc' => true, 'notes' => 'Vehicle cleaning, inspection, pre-delivery inspection'],
];

$phases = [
    ['n' => 1, 'title' => 'Sales & order',        'sys' => 'Sales · Zoho Books', 'body' => 'A customer order arrives with the vehicle specification. Your team raises a <b>quote</b>, created as an <b>estimate in Zoho Books</b>.'],
    ['n' => 2, 'title' => 'Job card & build start','sys' => 'Sales · Zoho Books', 'body' => 'When the customer approves, the quote is <b>converted into an invoice</b> in Zoho Books. That conversion creates the <b>job card</b> and opens the build on the production floor.'],
    ['n' => 3, 'title' => 'Procurement',           'sys' => 'Procurement',        'body' => 'From the job card the system knows the <b>bill of materials</b>. Materials are procured and received. <i>(Auto-creating Zoho Purchase Orders is planned for phase 2.)</i>'],
    ['n' => 4, 'title' => 'Production floor',       'sys' => 'Production',         'body' => 'The vehicle moves through the build sections in order — see the editable stage lists below. The system always shows exactly which section every vehicle is in.'],
    ['n' => 5, 'title' => 'Quality gates',          'sys' => 'Quality control',    'body' => 'After each section there is an <b>enforced quality gate</b>. A vehicle cannot advance until QC passes; a failure <b>loops it back for rework</b> and is logged.'],
    ['n' => 6, 'title' => 'Delivery & finance',     'sys' => 'Finance · Zoho Books','body' => 'Final QC and the <b>water test</b>. The Zoho invoice is settled, the vehicle is delivered, and the build is marked complete.'],
];

$decisions = [
    ['k' => 'd1', 'title' => 'Job card starts when the quote is converted into an invoice'],
    ['k' => 'd2', 'title' => 'Quality gates are enforced — a vehicle cannot skip a failed check; it loops back for rework'],
    ['k' => 'd3', 'title' => 'For version 1, the system links to Zoho Books at the quote (estimate) and invoice only — Purchase Orders come later'],
    ['k' => 'd4', 'title' => 'The system supports two vehicle types now: bus and truck'],
];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Identity Auto Fabricators — Production Blueprint</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f4f6fb; --card:#ffffff; --ink:#16202e; --muted:#5b6b80; --line:#e3e8f0;
    --accent:#1f6feb; --accent-d:#13427e; --teal:#0f9d76; --amber:#b9760f; --amber-bg:#fdf4e4;
    --ok:#0f9d58; --shadow:0 1px 2px rgba(16,32,55,.06),0 8px 24px rgba(16,32,55,.06);
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:'Inter',system-ui,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink);line-height:1.6}
  a{color:var(--accent)}
  .wrap{max-width:880px;margin:0 auto;padding:0 18px 140px}
  /* header */
  .top{background:linear-gradient(135deg,#13427e,#1f6feb);color:#fff;padding:34px 0 30px;margin-bottom:26px}
  .top .wrap{padding-bottom:0}
  .eyebrow{font-size:12px;letter-spacing:.14em;text-transform:uppercase;opacity:.82;font-weight:600}
  .top h1{margin:.35rem 0 .5rem;font-size:27px;font-weight:700;letter-spacing:-.01em}
  .top p{margin:0;opacity:.92;max-width:60ch}
  .top .bar{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
  .hlogo{display:inline-block;background:#fff;border-radius:11px;padding:8px 12px;margin-bottom:12px;box-shadow:0 6px 16px rgba(8,18,40,.25)}
  .hlogoimg{width:172px;height:auto;display:block}
  .logout{color:#fff;border:1px solid rgba(255,255,255,.45);padding:7px 13px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:500;white-space:nowrap}
  .logout:hover{background:rgba(255,255,255,.14)}
  /* cards */
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:22px 22px;margin:16px 0}
  .card h2{font-size:18px;margin:0 0 4px;font-weight:600}
  .lead{color:var(--muted);font-size:14px;margin:0 0 18px}
  .sectlabel{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);font-weight:600;margin:30px 4px 2px}
  /* phase */
  .phase{display:flex;gap:16px;align-items:flex-start}
  .pnum{flex:0 0 38px;height:38px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px}
  .pbody{flex:1;min-width:0}
  .ptitle{font-weight:600;font-size:16px;margin:6px 0 2px}
  .ptag{display:inline-block;font-size:11px;font-weight:600;color:var(--accent-d);background:#e9f1ff;border-radius:20px;padding:2px 10px;margin-left:8px;vertical-align:middle}
  .pdesc{color:#34465c;font-size:14px;margin:2px 0 12px}
  /* feedback controls */
  .fb{background:#f8fafd;border:1px solid var(--line);border-radius:12px;padding:12px 14px}
  .seg{display:inline-flex;border:1px solid var(--line);border-radius:9px;overflow:hidden;background:#fff;margin-bottom:8px}
  .seg label{font-size:13px;padding:6px 14px;cursor:pointer;font-weight:500;color:var(--muted);user-select:none}
  .seg input{display:none}
  .seg label.approve.on{background:var(--ok);color:#fff}
  .seg label.change.on{background:var(--amber);color:#fff}
  textarea,input[type=text]{width:100%;font:inherit;font-size:14px;color:var(--ink);border:1px solid var(--line);border-radius:10px;padding:9px 11px;background:#fff;resize:vertical}
  textarea:focus,input[type=text]:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(31,111,235,.12)}
  .fb textarea{min-height:46px}
  label.fld{display:block;font-size:12px;font-weight:600;color:var(--muted);margin:10px 0 4px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:620px){.grid2{grid-template-columns:1fr}}
  /* stage editor */
  .stagehead{display:flex;justify-content:space-between;align-items:center;margin:4px 0 10px;gap:10px;flex-wrap:wrap}
  .pill{font-size:12px;font-weight:600;border-radius:20px;padding:3px 12px}
  .pill.bus{background:#e6f7f1;color:#0c7a5c}
  .pill.truck{background:#fff0e0;color:#9a5e08}
  .srow{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:12px;padding:10px;margin-bottom:9px;background:#fff}
  .sidx{flex:0 0 26px;height:26px;border-radius:7px;background:#eef2f8;color:var(--muted);font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:center;margin-top:3px}
  .sfields{flex:1;min-width:0}
  .sname{font-weight:600}
  .snotes{margin-top:7px;min-height:38px;font-size:13px;color:#34465c}
  .srow .qc{flex:0 0 auto;display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);font-weight:600;background:var(--amber-bg);border-radius:8px;padding:6px 9px;margin-top:2px;cursor:pointer;white-space:nowrap}
  .srow .qc input{accent-color:var(--amber)}
  .del{flex:0 0 auto;border:none;background:#fbeaea;color:#b3261e;border-radius:8px;width:30px;height:30px;font-size:16px;cursor:pointer;margin-top:2px}
  .del:hover{background:#f7d4d4}
  .addbtn{border:1px dashed var(--accent);background:#f1f6ff;color:var(--accent-d);font-weight:600;font-size:13px;border-radius:10px;padding:9px 14px;cursor:pointer;width:100%}
  .addbtn:hover{background:#e6f0ff}
  .hint{font-size:13px;color:var(--muted)}
  /* decisions */
  .drow{padding:13px 0;border-top:1px solid var(--line)}
  .drow:first-child{border-top:none}
  .dtitle{font-size:14px;font-weight:500;margin-bottom:8px}
  /* save bar */
  .savebar{position:fixed;left:0;right:0;bottom:0;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-top:1px solid var(--line);padding:12px 0;z-index:20}
  .savebar .wrap{padding-bottom:0;display:flex;align-items:center;justify-content:space-between;gap:14px}
  .savestatus{font-size:13px;color:var(--muted)}
  .btn{background:var(--accent);color:#fff;border:none;border-radius:11px;padding:12px 26px;font:inherit;font-weight:600;font-size:15px;cursor:pointer;box-shadow:var(--shadow)}
  .btn:hover{background:var(--accent-d)}
  .btn:disabled{opacity:.6;cursor:default}
  .toast{position:fixed;bottom:84px;left:50%;transform:translateX(-50%) translateY(20px);background:#16202e;color:#fff;padding:11px 20px;border-radius:11px;font-size:14px;font-weight:500;opacity:0;transition:.25s;z-index:30;pointer-events:none}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
  .toast.err{background:#b3261e}
  /* ---- animated login ---- */
  .authpage{position:fixed;inset:0;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:20px;
    background:linear-gradient(120deg,#1f6feb,#0f9d76,#b9760f,#7a3ff2,#1f6feb);background-size:300% 300%;animation:grad 14s ease infinite}
  @keyframes grad{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
  .blob{position:absolute;border-radius:50%;filter:blur(38px);opacity:.55;mix-blend-mode:screen;animation:floaty 12s ease-in-out infinite}
  .b1{width:260px;height:260px;background:#ff5d8f;top:-60px;left:-40px}
  .b2{width:300px;height:300px;background:#34d1bf;bottom:-80px;right:-60px;animation-delay:-3s}
  .b3{width:200px;height:200px;background:#ffd166;top:28%;right:10%;animation-delay:-6s}
  .b4{width:220px;height:220px;background:#8a5cff;bottom:8%;left:6%;animation-delay:-9s}
  @keyframes floaty{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(24px,-30px) scale(1.08)}66%{transform:translate(-20px,20px) scale(.95)}}
  .authcard{position:relative;z-index:2;width:100%;max-width:400px;background:rgba(255,255,255,.96);border-radius:22px;
    padding:30px 30px 24px;box-shadow:0 24px 70px rgba(8,18,40,.45);animation:rise .7s cubic-bezier(.2,.8,.2,1) both;-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
  @keyframes rise{from{opacity:0;transform:translateY(28px) scale(.97)}to{opacity:1;transform:none}}
  .brandwrap{text-align:center;margin-bottom:10px;animation:bob 4s ease-in-out infinite}
  @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
  .brandlogo{width:212px;max-width:78%;height:auto;display:block;margin:0 auto}
  .authtitle{text-align:center;margin:0;font-size:20px;font-weight:700;background:linear-gradient(90deg,#13427e,#0f9d76,#b9760f);-webkit-background-clip:text;background-clip:text;color:transparent}
  .sub{color:#5b6b80;font-size:14px;margin:8px 0 18px;text-align:center}
  .stages{margin:0 0 22px}
  .track{position:relative;height:7px;border-radius:6px;background:#eef2f8;overflow:hidden}
  .fill{position:absolute;top:0;bottom:0;left:0;right:100%;border-radius:6px;background:linear-gradient(90deg,#1f6feb,#34d1bf,#ffd166,#ff5d8f,#8a5cff);background-size:200% 100%;animation:fillbar 4s ease-in-out infinite,hue 4s linear infinite}
  @keyframes fillbar{0%{right:100%}60%{right:0}100%{right:0;opacity:.85}}
  @keyframes hue{to{background-position:200% 0}}
  .dots{display:flex;justify-content:space-between;margin-top:10px}
  .dot{width:14px;height:14px;border-radius:50%;background:#cdd6e3;transform:scale(.7);animation:pop 4s ease-in-out infinite}
  .d1{animation-delay:0s}.d2{animation-delay:.5s}.d3{animation-delay:1s}.d4{animation-delay:1.5s}.d5{animation-delay:2s}.d6{animation-delay:2.5s}
  @keyframes pop{0%,100%{transform:scale(.7);background:#cdd6e3}40%,60%{transform:scale(1.25)}50%{background:var(--dc,#1f6feb)}}
  .stagelabels{display:flex;justify-content:space-between;margin-top:7px}
  .stagelabels span{font-size:10px;color:#8a97a8;font-weight:600}
  .authcard label{display:block;font-size:13px;font-weight:600;margin:12px 0 5px;color:#34465c}
  .inp{width:100%;font:inherit;font-size:15px;border:1.5px solid #e3e8f0;border-radius:12px;padding:12px 13px;transition:.2s;background:#fff}
  .inp:focus{outline:none;border-color:#1f6feb;box-shadow:0 0 0 4px rgba(31,111,235,.15)}
  .bigbtn{position:relative;width:100%;margin-top:20px;padding:14px;font:inherit;font-size:16px;font-weight:700;border:none;border-radius:13px;color:#fff;cursor:pointer;overflow:hidden;
    background:linear-gradient(90deg,#1f6feb,#0f9d76);background-size:200% 100%;animation:hue 5s linear infinite;box-shadow:0 12px 26px rgba(15,157,118,.4);transition:transform .15s}
  .bigbtn:hover{transform:translateY(-2px)}
  .bigbtn:active{transform:translateY(0)}
  .bigbtn::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.5),transparent);transform:skewX(-20deg);animation:shine 3.2s ease-in-out infinite}
  @keyframes shine{0%{left:-60%}55%,100%{left:130%}}
  .foot{text-align:center;font-size:12px;color:#8a97a8;margin-top:16px}
  .err{background:#fbeaea;color:#b3261e;border-radius:10px;padding:9px 12px;font-size:13px;font-weight:500;margin-bottom:6px}
  @media(prefers-reduced-motion:reduce){.authpage,.blob,.logo,.fill,.dot,.bigbtn,.bigbtn::after,.authcard{animation:none}.fill{right:0}}
  /* ---- guided review UX ---- */
  .pstrip{position:sticky;top:0;z-index:15;background:rgba(255,255,255,.94);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
  .pstrip-inner{max-width:880px;margin:0 auto;display:flex;align-items:center;gap:14px;padding:11px 18px}
  .pcount{font-size:13px;font-weight:600;color:#34465c;white-space:nowrap;min-width:104px}
  .ptrack{flex:1;height:9px;border-radius:6px;background:#eef2f8;overflow:hidden}
  .pfill{height:100%;width:0;border-radius:6px;background:linear-gradient(90deg,#1f6feb,#0f9d76);transition:width .55s cubic-bezier(.2,.8,.2,1)}
  .pfill.done{background:linear-gradient(90deg,#0f9d58,#0f9d76)}
  .pnext{border:1px solid var(--accent);background:#fff;color:var(--accent-d);font:inherit;font-weight:600;font-size:13px;border-radius:9px;padding:8px 13px;cursor:pointer;white-space:nowrap;transition:.15s}
  .pnext:hover{background:#eef5ff} .pnext:disabled{opacity:.55;cursor:default;border-color:var(--line);color:var(--muted)}
  .welcome{background:#e6f7f1;border:1px solid #b6e6d5;color:#0c7a5c;border-radius:12px;padding:11px 15px;font-size:14px;font-weight:500;margin:18px 0 0}
  .helper{background:#eef5ff;border:1px solid #cfe0fb;border-radius:14px;padding:16px 18px;margin:16px 0 6px}
  .helper h3{margin:0 0 8px;font-size:15px;color:#13427e;font-weight:600}
  .helper ol{margin:0;padding-left:20px;color:#34465c;font-size:14px} .helper li{margin:4px 0}
  .ritag{display:inline-flex;align-items:center;font-size:12px;font-weight:600;border-radius:20px;padding:3px 11px;margin-bottom:10px}
  .ritag.todo{background:#fef3e2;color:#9a5e08} .ritag.done{background:#e6f7f1;color:#0c7a5c}
  .seg{vertical-align:middle}
  .resetbtn{margin-left:10px;vertical-align:middle;border:1px solid var(--line);background:#fff;color:var(--muted);font:inherit;font-size:12px;font-weight:600;border-radius:9px;padding:7px 12px;cursor:pointer;transition:.15s}
  .resetbtn:hover{border-color:#f0b35a;color:#9a5e08;background:#fff8ee}
  .card.attention{box-shadow:0 0 0 2px #f0b35a, var(--shadow);transition:box-shadow .25s}
  .celebrate{display:none;background:linear-gradient(135deg,#0f9d58,#0f9d76);color:#fff;border-radius:14px;padding:16px 18px;margin:18px 0;font-size:15px;font-weight:500}
  .celebrate.show{display:block;animation:rise .5s both}
  .celebrate b{font-weight:700}
  /* ---- lucide icons ---- */
  .lucide{width:18px;height:18px;flex:0 0 auto}
  .btn,.bigbtn,.pnext,.logout,.addbtn,.resetbtn,.celebrate.show{display:inline-flex;align-items:center;gap:8px;justify-content:center}
  .celebrate.show{justify-content:flex-start}
  .helper h3{display:flex;align-items:center;gap:8px} .helper h3 .lucide{width:18px;height:18px;color:#1f6feb}
  .sectlabel{display:flex;align-items:center;gap:7px} .sectlabel .lucide{width:15px;height:15px}
  .ritag .lucide{width:14px;height:14px;margin-right:5px}
  .foot{display:flex;align-items:center;justify-content:center;gap:6px} .foot .lucide{width:14px;height:14px}
</style>
</head>
<body>

<?php if (!$authed): ?>
  <div class="authpage">
    <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div><div class="blob b4"></div>
    <div class="authcard">
      <div class="brandwrap"><?= identity_logo('brandlogo') ?></div>
      <h1 class="authtitle">Production blueprint</h1>
      <p class="sub">Welcome! Sign in to help shape how the bus &amp; truck build system will work.</p>
      <div class="stages" aria-hidden="true">
        <div class="track"><div class="fill"></div></div>
        <div class="dots">
          <span class="dot d1" style="--dc:#1f6feb"></span>
          <span class="dot d2" style="--dc:#34d1bf"></span>
          <span class="dot d3" style="--dc:#0f9d76"></span>
          <span class="dot d4" style="--dc:#ffd166"></span>
          <span class="dot d5" style="--dc:#ff5d8f"></span>
          <span class="dot d6" style="--dc:#8a5cff"></span>
        </div>
        <div class="stagelabels"><span>Frame</span><span>Sheeting</span><span>Filler</span><span>Paint</span><span>Finishing</span><span>PDI</span></div>
      </div>
      <?php if ($loginError): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
      <form method="post" action="blueprint.php?action=login">
        <label>Username</label>
        <input class="inp" type="text" name="username" autocomplete="username" autofocus required>
        <label>Password</label>
        <input class="inp" type="password" name="password" autocomplete="current-password" required>
        <button class="bigbtn" type="submit">Sign in &nbsp;→</button>
      </form>
      <div class="foot"><i data-lucide="lock"></i> Private — access by invite only</div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>window.lucide&&lucide.createIcons();</script>

<?php else: ?>

  <div class="top">
    <div class="wrap">
      <div class="bar">
        <div>
          <div class="hlogo"><?= identity_logo('hlogoimg') ?></div>
          <div class="eyebrow">Identity Auto Fabricators Limited</div>
          <h1>Bus &amp; truck build — production blueprint</h1>
          <p>Please review each part of the proposed system. Edit anything that is wrong, mark whether you approve or want a change, then press <b>Save</b> at the bottom. Your notes go straight to the developer.</p>
        </div>
        <div style="text-align:right;white-space:nowrap">
          <div style="font-size:12px;opacity:.85;margin-bottom:6px">Signed in as <?= h($_SESSION['bp_name'] ?? '') ?></div>
          <?php if (($_SESSION['bp_role'] ?? '') === 'admin'): ?>
            <a class="logout" href="blueprint.php?action=logs"><i data-lucide="scroll-text"></i> View logs</a>
          <?php endif; ?>
          <a class="logout" href="blueprint.php?action=logout"><i data-lucide="log-out"></i> Sign out</a>
        </div>
      </div>
    </div>
  </div>

  <div class="pstrip">
    <div class="pstrip-inner">
      <span class="pcount" id="pcount">0 of 0 reviewed</span>
      <div class="ptrack"><div class="pfill" id="pfill"></div></div>
      <button class="pnext" id="pnext" type="button">Jump to next →</button>
    </div>
  </div>

  <div class="wrap">

    <div class="welcome" id="welcome" style="display:none"></div>

    <div class="helper">
      <h3><i data-lucide="info"></i> How this works — about 10 minutes</h3>
      <ol>
        <li>Read each step of the proposed system below.</li>
        <li>Tap <b>Looks right</b> or <b>Needs a change</b> — add a note if you'd like something different.</li>
        <li>Correct the bus &amp; truck section names so they match your real workshop.</li>
        <li>Press <b>Save</b> at the bottom. Your progress is kept — you can come back and edit anytime.</li>
      </ol>
    </div>

    <div class="sectlabel"><i data-lucide="route"></i> The workflow, step by step</div>

    <?php foreach ($phases as $p): ?>
      <div class="card">
        <div class="phase">
          <div class="pnum"><?= $p['n'] ?></div>
          <div class="pbody">
            <div class="ptitle"><?= h($p['title']) ?><span class="ptag"><?= h($p['sys']) ?></span></div>
            <div class="pdesc"><?= $p['body'] ?></div>
            <div class="fb review-item" data-group="ph<?= $p['n'] ?>_v">
              <div class="seg" data-seg>
                <label class="approve"><input type="radio" name="ph<?= $p['n'] ?>_v" value="approve">Looks right</label>
                <label class="change"><input type="radio" name="ph<?= $p['n'] ?>_v" value="change">Needs a change</label>
              </div>
              <textarea name="ph<?= $p['n'] ?>_note" placeholder="Anything to change about this step? (optional)"></textarea>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="sectlabel"><i data-lucide="layers"></i> Production stages — please correct these to match your shop</div>

    <div class="card">
      <h2>Bus build — sections in order</h2>
      <p class="lead">These are the sections a bus passes through. Rename them to your real section names, fix the order (top to bottom), tick <b>QC gate</b> where a quality check must be passed before moving on, add or remove rows as needed.</p>
      <div class="stagehead"><span class="pill bus">Bus</span><span class="hint">Edit names, tick QC gate, add or remove rows. If the order is wrong, note it below.</span></div>
      <div id="busList"></div>
      <button type="button" class="addbtn" data-add="busList"><i data-lucide="plus"></i> Add a section</button>
    </div>

    <div class="card">
      <h2>Truck build — sections in order</h2>
      <p class="lead">Same idea for trucks. The truck flow is usually a little shorter than the bus flow.</p>
      <div class="stagehead"><span class="pill truck">Truck</span></div>
      <div id="truckList"></div>
      <button type="button" class="addbtn" data-add="truckList"><i data-lucide="plus"></i> Add a section</button>
    </div>

    <div class="sectlabel"><i data-lucide="circle-help"></i> Key decisions — do you agree?</div>

    <div class="card">
      <p class="lead">These are the choices that shape how the system behaves. Tell us if any should be different.</p>
      <?php foreach ($decisions as $d): ?>
        <div class="drow review-item" data-group="<?= $d['k'] ?>_v">
          <div class="dtitle"><?= h($d['title']) ?></div>
          <div class="seg" data-seg>
            <label class="approve"><input type="radio" name="<?= $d['k'] ?>_v" value="approve">Agree</label>
            <label class="change"><input type="radio" name="<?= $d['k'] ?>_v" value="change">Change it</label>
          </div>
          <textarea name="<?= $d['k'] ?>_note" placeholder="If you want it different, tell us how (optional)"></textarea>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="sectlabel"><i data-lucide="message-square-plus"></i> Anything we missed</div>

    <div class="card">
      <h2>Your notes</h2>
      <p class="lead">Anything about your process that isn't captured above — special steps, who signs off, paperwork, customer types, anything at all.</p>
      <textarea name="missed" style="min-height:120px" placeholder="Type freely…"></textarea>
      <div class="grid2" style="margin-top:14px">
        <div class="review-item" data-group="signoff_v">
          <label class="fld">Overall, is this blueprint ready to build?</label>
          <div class="seg" data-seg>
            <label class="approve"><input type="radio" name="signoff_v" value="approve">Approved — build it</label>
            <label class="change"><input type="radio" name="signoff_v" value="change">Not yet</label>
          </div>
        </div>
        <div><label class="fld">Date</label><input type="text" name="signoff_date" placeholder="e.g. 30 June 2026"></div>
      </div>
    </div>

    <div class="celebrate" id="celebrate"><i data-lucide="party-popper"></i> <b>All reviewed — nicely done!</b> Press <b>Save my changes</b> to send it to the developer. You can still come back and edit anything later.</div>

  </div>

  <div class="savebar">
    <div class="wrap">
      <div class="savestatus" id="status"><?php if ($savedAt): ?>Last saved <?= h(date('j M Y, g:i a', strtotime($savedAt))) ?><?php else: ?>Not saved yet — your changes are not stored until you press Save.<?php endif; ?></div>
      <button class="btn" id="saveBtn"><i data-lucide="save"></i> Save my changes</button>
    </div>
  </div>
  <div class="toast" id="toast"></div>

  <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>
    function icons(){ if(window.lucide) try{ lucide.createIcons(); }catch(e){} }
    const CSRF = <?= json_encode($csrf) ?>;
    const SAVED = <?= json_encode($saved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null' ?>;
    const DEFAULTS = {
      bus: <?= json_encode($defaultBus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      truck: <?= json_encode($defaultTruck, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };

    function esc(s){ const d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

    // ---- stage rows ----
    function stageRow(idx, stage){
      const wrap = document.createElement('div');
      wrap.className = 'srow';
      wrap.innerHTML =
        '<div class="sidx">'+idx+'</div>'+
        '<div class="sfields">'+
          '<input type="text" class="sname" value="'+esc(stage.name)+'" placeholder="Section name">'+
          '<textarea class="snotes" placeholder="What happens in this section">'+esc(stage.notes||'')+'</textarea>'+
        '</div>'+
        '<label class="qc"><input type="checkbox" class="sqc" '+(stage.qc?'checked':'')+'>QC gate</label>'+
        '<button type="button" class="del" title="Remove">&times;</button>';
      wrap.querySelector('.del').onclick = ()=>{ wrap.remove(); renumber(wrap.parentElement); };
      return wrap;
    }
    function renumber(list){ [...list.querySelectorAll('.srow')].forEach((r,i)=> r.querySelector('.sidx').textContent = i+1); }
    function fillList(id, stages){
      const list = document.getElementById(id);
      list.innerHTML = '';
      stages.forEach((s,i)=> list.appendChild(stageRow(i+1, s)));
    }
    document.querySelectorAll('[data-add]').forEach(btn=>{
      btn.onclick = ()=>{
        const list = document.getElementById(btn.getAttribute('data-add'));
        list.appendChild(stageRow(list.querySelectorAll('.srow').length+1, {name:'',notes:'',qc:false}));
      };
    });
    function collectStages(id){
      return [...document.getElementById(id).querySelectorAll('.srow')].map(r=>({
        name: r.querySelector('.sname').value.trim(),
        notes: r.querySelector('.snotes').value.trim(),
        qc: r.querySelector('.sqc').checked
      })).filter(s=> s.name || s.notes);
    }

    // ---- segmented buttons ----
    function paintSegs(){
      document.querySelectorAll('[data-seg]').forEach(seg=>{
        seg.querySelectorAll('label').forEach(l=>{
          const input = l.querySelector('input');
          l.classList.toggle('on', input.checked);
        });
      });
    }
    document.querySelectorAll('[data-seg] input').forEach(i=> i.addEventListener('change', paintSegs));

    // ---- hydrate from a data object ----
    function hydrateFrom(data){
      const f = (data && data.fields) || {};
      Object.keys(f).forEach(name=>{
        document.querySelectorAll('[name="'+(window.CSS&&CSS.escape?CSS.escape(name):name)+'"]').forEach(el=>{
          if(el.type==='radio'){ el.checked = (el.value===f[name]); }
          else if(el.type==='checkbox'){ el.checked = !!f[name]; }
          else el.value = f[name];
        });
      });
      fillList('busList', (data && data.busStages && data.busStages.length) ? data.busStages : DEFAULTS.bus);
      fillList('truckList', (data && data.truckStages && data.truckStages.length) ? data.truckStages : DEFAULTS.truck);
      paintSegs();
    }

    // ---- collect ----
    function collectFields(){
      const f = {};
      document.querySelectorAll('input[name], textarea[name]').forEach(el=>{
        if(el.type==='radio'){ if(el.checked) f[el.name]=el.value; }
        else if(el.type==='checkbox'){ f[el.name]=el.checked; }
        else f[el.name]=el.value.trim();
      });
      return f;
    }
    function snapshot(){ return { fields: collectFields(), busStages: collectStages('busList'), truckStages: collectStages('truckList') }; }

    function toast(msg, err){
      const t = document.getElementById('toast');
      t.textContent = msg; t.classList.toggle('err', !!err); t.classList.add('show');
      setTimeout(()=> t.classList.remove('show'), 2800);
    }

    // ---- guided progress ----
    const reviewItems = [];
    function setupReview(){
      document.querySelectorAll('.review-item').forEach(item=>{
        const seg = item.querySelector('.seg'); if(!seg) return;
        const tag = document.createElement('div'); tag.className = 'ritag';
        seg.parentNode.insertBefore(tag, seg);
        const reset = document.createElement('button');
        reset.type = 'button'; reset.className = 'resetbtn'; reset.innerHTML = '<i data-lucide="rotate-ccw"></i> Reset';
        reset.onclick = ()=>{
          document.querySelectorAll('input[name="'+item.getAttribute('data-group')+'"]').forEach(r=> r.checked=false);
          paintSegs(); markDirty(); updateProgress();
        };
        seg.insertAdjacentElement('afterend', reset);
        item._tag = tag; item._reset = reset; reviewItems.push(item);
      });
    }
    function answered(item){ return !!document.querySelector('input[name="'+item.getAttribute('data-group')+'"]:checked'); }
    function updateProgress(){
      let done = 0;
      reviewItems.forEach(item=>{
        if(answered(item)){ done++; item._tag.className='ritag done'; item._tag.innerHTML='<i data-lucide="check"></i>Answered'; item._reset.style.display=''; const c=item.closest('.card'); if(c) c.classList.remove('attention'); }
        else { item._tag.className='ritag todo'; item._tag.innerHTML='<i data-lucide="circle-dashed"></i>Needs your input'; item._reset.style.display='none'; }
      });
      const total = reviewItems.length, pct = total ? Math.round(done/total*100) : 0;
      const fill = document.getElementById('pfill'); fill.style.width = pct+'%'; fill.classList.toggle('done', total>0 && done===total);
      document.getElementById('pcount').textContent = done+' of '+total+' reviewed';
      const next = document.getElementById('pnext'), cele = document.getElementById('celebrate');
      if(total>0 && done===total){ next.disabled=true; next.innerHTML='All reviewed <i data-lucide="party-popper"></i>'; cele.classList.add('show'); }
      else { next.disabled=false; next.innerHTML='Jump to next <i data-lucide="arrow-right"></i>'; cele.classList.remove('show'); }
      icons();
    }
    document.getElementById('pnext').onclick = ()=>{
      const item = reviewItems.find(it=> !answered(it)); if(!item) return;
      const card = item.closest('.card') || item;
      card.scrollIntoView({behavior:'smooth', block:'center'}); card.classList.add('attention');
    };

    // ---- dirty tracking + on-device draft ----
    const LSKEY = 'bp_draft_v1'; let dirty = false, saving = false;
    function setStatus(html, color){ const s=document.getElementById('status'); s.innerHTML=html; s.style.color=color||''; }
    function markDirty(){ dirty=true; setStatus('● Unsaved changes — press Save', '#9a5e08'); try{ localStorage.setItem(LSKEY, JSON.stringify(snapshot())); }catch(e){} }
    window.addEventListener('beforeunload', e=>{ if(dirty && !saving){ e.preventDefault(); e.returnValue=''; } });
    document.addEventListener('input', ()=>{ markDirty(); updateProgress(); });
    document.addEventListener('change', ()=>{ paintSegs(); markDirty(); updateProgress(); });

    // ---- save ----
    const btn = document.getElementById('saveBtn');
    btn.onclick = async ()=>{
      saving=true; btn.disabled=true; const old=btn.innerHTML; btn.textContent='Saving…';
      try{
        const res = await fetch('blueprint.php?action=save', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({csrf:CSRF, data: snapshot()})
        });
        const j = await res.json();
        if(j.ok){
          dirty=false; try{ localStorage.removeItem(LSKEY); }catch(e){}
          toast('Saved — thank you! The developer can now see your input.');
          setStatus('✓ All changes saved', '#0c7a5c');
        } else { toast(j.error || 'Could not save.', true); }
      }catch(e){ toast('Network error — please try again.', true); }
      saving=false; btn.disabled=false; btn.innerHTML=old; icons();
    };

    // ---- init ----
    let initial = SAVED, restored = false;
    if(!initial){ try{ const d=localStorage.getItem(LSKEY); if(d){ initial=JSON.parse(d); restored=true; } }catch(e){} }
    hydrateFrom(initial);
    setupReview();
    updateProgress();
    const wel = document.getElementById('welcome');
    if(SAVED){ wel.textContent='Welcome back — your saved answers are loaded. Edit anything and press Save again.'; wel.style.display='block'; }
    else if(restored){ wel.textContent='We restored your unsaved draft from this device. Review it and Save when ready.'; wel.style.display='block'; }
  </script>

<?php endif; ?>
</body>
</html>
