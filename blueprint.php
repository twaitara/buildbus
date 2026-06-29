<?php
/**
 * Bus & Truck Body Build — Production System Blueprint
 * A login-gated, editable blueprint the client reviews and annotates.
 * Client input is saved to data/blueprint_input.json (and appended to history).
 */
session_start();

// ---- credentials (override via secret.php) ----
$creds = ['user' => 'client', 'pass' => 'CHANGE_ME_NOW'];
if (is_file(__DIR__ . '/secret.php')) {
    $s = require __DIR__ . '/secret.php';
    if (is_array($s)) $creds = array_merge($creds, $s);
}

$DATA_DIR  = __DIR__ . '/data';
$DATA_FILE = $DATA_DIR . '/blueprint_input.json';
$HIST_FILE = $DATA_DIR . '/blueprint_history.jsonl';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf   = $_SESSION['csrf'];
$action = $_GET['action'] ?? '';
$authed = !empty($_SESSION['bp_auth']);

// ---- logout ----
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: blueprint.php');
    exit;
}

// ---- login ----
$loginError = '';
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if (hash_equals((string)$creds['user'], $u) && hash_equals((string)$creds['pass'], $p)) {
        session_regenerate_id(true);
        $_SESSION['bp_auth'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: blueprint.php');
        exit;
    }
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
    $record = ['saved_at' => date('c'), 'data' => $body['data'] ?? []];
    file_put_contents($DATA_FILE, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    file_put_contents($HIST_FILE, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    echo json_encode(['ok' => true, 'saved_at' => $record['saved_at']]);
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
<title>Production System Blueprint — Bus &amp; Truck Body Build</title>
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
  .top .bar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
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
  /* login */
  .login{max-width:380px;margin:9vh auto;padding:0 18px}
  .login .card{padding:28px}
  .login h1{font-size:21px;margin:0 0 4px}
  .login p{color:var(--muted);font-size:14px;margin:0 0 20px}
  .login label{display:block;font-size:13px;font-weight:600;margin:12px 0 5px}
  .login .btn{width:100%;margin-top:18px}
  .err{background:#fbeaea;color:#b3261e;border-radius:10px;padding:9px 12px;font-size:13px;font-weight:500;margin-bottom:6px}
  .flow{font-size:13px;color:var(--muted);margin:2px 0 0}
</style>
</head>
<body>

<?php if (!$authed): ?>
  <div class="login">
    <div class="card">
      <h1>Production system blueprint</h1>
      <p>Bus &amp; truck body build. Please sign in with the username and password you were given.</p>
      <?php if ($loginError): ?><div class="err"><?= h($loginError) ?></div><?php endif; ?>
      <form method="post" action="blueprint.php?action=login">
        <label>Username</label>
        <input type="text" name="username" autocomplete="username" autofocus required>
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" required
               style="width:100%;font:inherit;font-size:14px;border:1px solid var(--line);border-radius:10px;padding:9px 11px">
        <button class="btn" type="submit">Sign in</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <div class="top">
    <div class="wrap">
      <div class="bar">
        <div>
          <div class="eyebrow">Production system · blueprint for review</div>
          <h1>Bus &amp; truck body build</h1>
          <p>Please review each part of the proposed system. Edit anything that is wrong, mark whether you approve or want a change, then press <b>Save</b> at the bottom. Your notes go straight to the developer.</p>
        </div>
        <a class="logout" href="blueprint.php?action=logout">Sign out</a>
      </div>
    </div>
  </div>

  <div class="wrap">

    <div class="card">
      <h2>Your details</h2>
      <p class="lead">So we know who reviewed this.</p>
      <div class="grid2">
        <div><label class="fld">Business / shop name</label><input type="text" name="client_name" placeholder="e.g. Acme Body Builders"></div>
        <div><label class="fld">Your name &amp; role</label><input type="text" name="reviewer" placeholder="e.g. John, Workshop Manager"></div>
      </div>
    </div>

    <div class="sectlabel">The workflow, step by step</div>

    <?php foreach ($phases as $p): ?>
      <div class="card">
        <div class="phase">
          <div class="pnum"><?= $p['n'] ?></div>
          <div class="pbody">
            <div class="ptitle"><?= h($p['title']) ?><span class="ptag"><?= h($p['sys']) ?></span></div>
            <div class="pdesc"><?= $p['body'] ?></div>
            <div class="fb">
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

    <div class="sectlabel">Production stages — please correct these to match your shop</div>

    <div class="card">
      <h2>Bus build — sections in order</h2>
      <p class="lead">These are the sections a bus passes through. Rename them to your real section names, fix the order (top to bottom), tick <b>QC gate</b> where a quality check must be passed before moving on, add or remove rows as needed.</p>
      <div class="stagehead"><span class="pill bus">Bus</span><span class="hint">Edit names, tick QC gate, add or remove rows. If the order is wrong, note it below.</span></div>
      <div id="busList"></div>
      <button type="button" class="addbtn" data-add="busList">+ Add a section</button>
    </div>

    <div class="card">
      <h2>Truck build — sections in order</h2>
      <p class="lead">Same idea for trucks. The truck flow is usually a little shorter than the bus flow.</p>
      <div class="stagehead"><span class="pill truck">Truck</span></div>
      <div id="truckList"></div>
      <button type="button" class="addbtn" data-add="truckList">+ Add a section</button>
    </div>

    <div class="sectlabel">Key decisions — do you agree?</div>

    <div class="card">
      <p class="lead">These are the choices that shape how the system behaves. Tell us if any should be different.</p>
      <?php foreach ($decisions as $d): ?>
        <div class="drow">
          <div class="dtitle"><?= h($d['title']) ?></div>
          <div class="seg" data-seg>
            <label class="approve"><input type="radio" name="<?= $d['k'] ?>_v" value="approve">Agree</label>
            <label class="change"><input type="radio" name="<?= $d['k'] ?>_v" value="change">Change it</label>
          </div>
          <textarea name="<?= $d['k'] ?>_note" placeholder="If you want it different, tell us how (optional)"></textarea>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="sectlabel">Anything we missed</div>

    <div class="card">
      <h2>Your notes</h2>
      <p class="lead">Anything about your process that isn't captured above — special steps, who signs off, paperwork, customer types, anything at all.</p>
      <textarea name="missed" style="min-height:120px" placeholder="Type freely…"></textarea>
      <div class="grid2" style="margin-top:14px">
        <div>
          <label class="fld">Overall, is this blueprint ready to build?</label>
          <div class="seg" data-seg>
            <label class="approve"><input type="radio" name="signoff_v" value="approve">Approved — build it</label>
            <label class="change"><input type="radio" name="signoff_v" value="change">Not yet</label>
          </div>
        </div>
        <div><label class="fld">Date</label><input type="text" name="signoff_date" placeholder="e.g. 30 June 2026"></div>
      </div>
    </div>

  </div>

  <div class="savebar">
    <div class="wrap">
      <div class="savestatus" id="status"><?php if ($savedAt): ?>Last saved <?= h(date('j M Y, g:i a', strtotime($savedAt))) ?><?php else: ?>Not saved yet — your changes are not stored until you press Save.<?php endif; ?></div>
      <button class="btn" id="saveBtn">Save my changes</button>
    </div>
  </div>
  <div class="toast" id="toast"></div>

  <script>
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

    // ---- hydrate from saved ----
    function hydrate(){
      const f = (SAVED && SAVED.fields) || {};
      Object.keys(f).forEach(name=>{
        document.querySelectorAll('[name="'+(window.CSS&&CSS.escape?CSS.escape(name):name)+'"]').forEach(el=>{
          if(el.type==='radio'){ el.checked = (el.value===f[name]); }
          else if(el.type==='checkbox'){ el.checked = !!f[name]; }
          else el.value = f[name];
        });
      });
      fillList('busList', (SAVED && SAVED.busStages && SAVED.busStages.length) ? SAVED.busStages : DEFAULTS.bus);
      fillList('truckList', (SAVED && SAVED.truckStages && SAVED.truckStages.length) ? SAVED.truckStages : DEFAULTS.truck);
      paintSegs();
    }

    // ---- collect + save ----
    function collectFields(){
      const f = {};
      document.querySelectorAll('input[name], textarea[name]').forEach(el=>{
        if(el.type==='radio'){ if(el.checked) f[el.name]=el.value; }
        else if(el.type==='checkbox'){ f[el.name]=el.checked; }
        else f[el.name]=el.value.trim();
      });
      return f;
    }
    function toast(msg, err){
      const t = document.getElementById('toast');
      t.textContent = msg; t.classList.toggle('err', !!err); t.classList.add('show');
      setTimeout(()=> t.classList.remove('show'), 2600);
    }
    const btn = document.getElementById('saveBtn');
    btn.onclick = async ()=>{
      btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';
      const data = { fields: collectFields(), busStages: collectStages('busList'), truckStages: collectStages('truckList') };
      try{
        const res = await fetch('blueprint.php?action=save', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({csrf:CSRF, data})
        });
        const j = await res.json();
        if(j.ok){
          toast('Saved — thank you. The developer can now see your notes.');
          document.getElementById('status').textContent = 'Last saved just now';
        } else { toast(j.error || 'Could not save.', true); }
      }catch(e){ toast('Network error — please try again.', true); }
      btn.disabled = false; btn.textContent = old;
    };

    hydrate();
  </script>

<?php endif; ?>
</body>
</html>
