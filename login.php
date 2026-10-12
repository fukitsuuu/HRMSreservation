


<?php
session_start();

// Check if user is already logged in and redirect to reservation portal
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['department'])) {
    // User is already logged in, redirect to reservation portal
    header("Location: reservation.php");
    exit();
}

// Get error message from URL parameter
$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Reservation Request Portal</title>
<link rel="icon" href="images/logo2.png?v=<?php echo time(); ?>">
<link href="../css/tailwind.css" rel="stylesheet" />

<style>
  :root{
    --blue-600:#1b2f73; --blue-700:#15265e; --blue-800:#0f1c49; --blue-a:#2342a6;
    --gold:#d4af37; --ink:#0f172a; --muted:#64748b;
    --ring:0 0 0 3px rgba(35,66,166,.28);
    --card-bg: rgba(255,255,255,.95); --card-border: rgba(226,232,240,.9);
    --wm-opa-light:.35; --wm-opa-dark:.55;
  }
  @media (prefers-color-scheme: dark){ :root{ --ink:#e5e7eb; --muted:#9ca3af; } }

  /* ===== Background ===== */
  body{
    min-height:100svh; margin:0; color:var(--ink);
    background:
      radial-gradient(70% 60% at 8% 10%, rgba(255,255,255,.18) 0, transparent 60%),
      radial-gradient(40% 40% at 100% 0%, rgba(212,175,55,.08) 0, transparent 40%),
      linear-gradient(140deg, rgba(15,28,73,1) 50%, rgba(255,255,255,1) 50%);
  }
  html.dark body{
    background:
      radial-gradient(70% 60% at 8% 10%, rgba(212,175,55,.08) 0, transparent 60%),
      radial-gradient(40% 40% at 100% 0%, rgba(212,175,55,.12) 0, transparent 40%),
      linear-gradient(140deg, rgba(7,12,38,1) 50%, rgba(11,21,56,1) 50%);
    color:#e5e7eb;
  }

  /* ===== Watermark ===== */
  .bg-watermark{ position:fixed; inset:0; z-index:-1; display:grid; place-items:center; pointer-events:none; }
  .bg-watermark img{
    width:min(820px,70vw); max-height:68vh; object-fit:contain; opacity:var(--wm-opa-light);
    filter: drop-shadow(0 0 26px rgba(255,255,255,.40)) drop-shadow(0 14px 34px rgba(0,0,0,.25));
    transition:opacity .25s ease, filter .25s ease, transform .6s ease;
  }
  html.dark .bg-watermark img{
    opacity:var(--wm-opa-dark);
    filter: drop-shadow(0 0 34px rgba(255,255,255,.55)) drop-shadow(0 16px 40px rgba(0,0,0,.30));
  }

  .reveal { opacity:0; transform:translateY(8px); animation:reveal .45s .05s both; }
  @keyframes reveal { to { opacity:1; transform:none; } }

  /* ===== Card ===== */
  .card{
    background:var(--card-bg); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
    border:1px solid var(--card-border); border-radius:18px; box-shadow:0 16px 48px rgba(2,6,23,.18);
  }
  html.dark .card{ background:rgba(17,24,39,.92); border-color:rgba(71,85,105,.55); box-shadow:0 16px 48px rgba(0,0,0,.5); }

  /* ===== Inputs ===== */
  .field{ position:relative; }
  .input{
    width:100%; border:1px solid #e5e7eb; border-radius:12px; background:#fff;
    padding:1rem 2.6rem 1rem .95rem; outline:none; color:#0f172a; transition:border-color .15s, box-shadow .15s, background .15s;
  }
  .input:focus{ border-color:var(--blue-a); box-shadow:var(--ring) }
  html.dark .input{ background:#0b1220; border-color:#243041; color:#e5e7eb; }
  .float-label{
    position:absolute; left:.9rem; top:50%; transform:translateY(-50%); padding:0 .25rem; color:#94a3b8;
    pointer-events:none; background:transparent; transition:all .15s ease;
  }
  .input:focus + .float-label,
  .input:not(:placeholder-shown) + .float-label,
  .input.has-value + .float-label{
    top:0; transform:translateY(-50%) scale(.92); color:var(--blue-a); background:#fff;
  }
  html.dark .input:focus + .float-label,
  html.dark .input:not(:placeholder-shown) + .float-label,
  html.dark .input.has-value + .float-label{ background:#0b1220; }
  .icon-right{ position:absolute; right:.6rem; top:50%; transform:translateY(-50%); color:#64748b; }
  html.dark .icon-right{ color:#94a3b8; }

  /* ===== Buttons ===== */
  .btn{
    width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:linear-gradient(180deg, var(--blue-600), var(--blue-800));
    color:#fff; font-weight:800; border-radius:14px; padding:.95rem 1rem; border:1px solid rgba(255,255,255,.06);
    transition:transform .08s ease, filter .15s ease, box-shadow .2s ease; box-shadow:0 8px 18px rgba(2,6,23,.18);
  }
  .btn:hover{ filter:saturate(1.08); box-shadow:0 12px 26px rgba(2,6,23,.26); }
  .btn:active{ transform:translateY(1px) scale(.99); }
  .btn[disabled]{ opacity:.85; cursor:not-allowed; }

  /* ===== Alerts (inline attempts/info) ===== */
  .alert{ border-radius:12px; padding:.65rem .8rem; font-size:.9rem }
  .alert-error{ border:1px solid #fecaca; background:#fef2f2; color:#b91c1c }
  .alert-info{ border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3 }
  html.dark .alert-error{ background:#3f1b1b; border-color:#7f1d1d; color:#fecaca }
  html.dark .alert-info{ background:#1e1b4b; border-color:#3730a3; color:#c7d2fe }


</style>
</head>

<body class="grid md:grid-cols-2 gap-0 place-items-center p-6 md:p-10">

  <!-- Watermark -->
  <div class="bg-watermark" aria-hidden="true">
    <img src="images/logo.png" alt="ATIERA watermark" id="wm">
  </div>

  <!-- Left panel -->
  <section class="hidden md:flex w-full h-full items-center justify-center mr-64">
    <div class="max-w-lg text-white px-6 reveal">
      <img src="images/logo.png" alt="ATIERA" class="w-56 mb-6 drop-shadow-xl select-none" draggable="false">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
        RESERVATION<br>REQUEST<br><span style="color:var(--gold)">PORTAL</span>
      </h1>
    </div>
  </section>

  <!-- Right: Login -->
  <main class="w-full max-w-md md:ml-auto">
    <div id="card" class="card p-6 sm:p-8 reveal">
      <div class="flex items-center justify-between mb-4">
        <div class="md:hidden flex items-center gap-3">
          <img src="images/logo.png" alt="ATIERA" class="h-10 w-auto">
        </div>
        <button id="modeBtn" class="px-3 py-2 rounded-lg border border-slate-200 text-sm hover:bg-white/60 dark:hover:bg-slate-800" aria-pressed="false" title="Toggle dark mode">🌓</button>
      </div>

      <h3 class="text-lg sm:text-xl font-semibold mb-1">Sign in</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Use your portal credentials to continue.</p>

      <!-- Inline attempt + info banners -->
      <div id="alert" class="alert alert-error hidden mb-4" role="alert"></div>
      <div id="info"  class="alert alert-info  hidden mb-4" role="status"></div>
      
      <?php if (!empty($error_message)): ?>
      <div class="alert alert-error mb-4" role="alert"><?php echo $error_message; ?></div>
      <?php endif; ?>
      
      <?php if (!empty($_GET['message'])): ?>
      <div class="alert alert-info mb-4" role="alert"><?php echo htmlspecialchars($_GET['message']); ?></div>
      <?php endif; ?>

      <form id="loginForm" class="space-y-4" method="POST" action="includes/login-backend.php">
        <!-- Username -->
        <div class="field">
          <input id="username" name="username" type="text" autocomplete="username" class="input peer" placeholder=" " required aria-describedby="userHelp">
          <label for="username" class="float-label">Username or Email</label>
          <span class="icon-right mx-2" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-5.33 0-8 2.67-8 5v1h16v-1c0-2.33-2.67-5-8-5Z" fill="currentColor"/></svg>
          </span>
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <span id="capsNote" class="hidden text-xs px-2 py-0.5 rounded bg-amber-50 border border-amber-300 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200">Caps Lock is ON</span>
          </div>
          <div class="field">
            <input id="password" name="password" type="password" autocomplete="current-password" class="input pr-12 peer" placeholder=" " required>
            <label for="password" class="float-label">Password</label>
            <div class="icon-right flex items-center gap-1">
              <button type="button" id="togglePw" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center" aria-label="Show password" aria-pressed="false" title="Show/Hide password">
                <svg id="eyeOn" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Z" fill="currentColor"/></svg>
                <svg id="eyeOff" class="hidden" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 4.27 4.28 3 21 19.72 19.73 21l-2.2-2.2A11.73 11.73 0 0 1 12 19c-5 0-9.27-3.11-11-7a12.71 12.71 0 0 1 4.1-4.73L3 4.27ZM12 7a5 5 0 0 1 5 5 5 5 0 0 1-.46 2.11L14.6 12.17A2.5 2.5 0 0 0 11.83 9.4L9.9 7.46A4.84 4.84 0 0 1 12 7Z" fill="currentColor"/></svg>
              </button>
            </div>
          </div>

        <!-- Submit -->
        <button id="submitBtn" type="submit" class="btn my-4" aria-live="polite">
          <span id="btnText">Sign In</span>
        </button>

        <p class="text-xs text-center text-slate-500 dark:text-slate-400">© 2025 Logistic 2 - Group 8</p>
      </form>
    </div>
  </main>



<script>
  const $ = (s, r=document)=>r.querySelector(s);

  // Elements
  const form      = $('#loginForm');
  const userEl    = $('#username');
  const pwEl      = $('#password');
  const toggle    = $('#togglePw');
  const eyeOn     = $('#eyeOn');
  const eyeOff    = $('#eyeOff');
  const alertBox  = $('#alert');
  const infoBox   = $('#info');
  const submitBtn = $('#submitBtn');
  const btnText   = $('#btnText');
  const capsNote  = $('#capsNote');
  const pwBar     = $('#pwBar');
  const pwLabel   = $('#pwLabel');
  const modeBtn   = $('#modeBtn');
  const wmImg     = $('#wm');

  /* ---------- Dark mode toggle ---------- */
  modeBtn.addEventListener('click', ()=>{
    const root = document.documentElement;
    const dark = root.classList.toggle('dark');
    modeBtn.setAttribute('aria-pressed', String(dark));
    wmImg.style.transform = 'scale(1.01)'; setTimeout(()=> wmImg.style.transform = '', 220);
  });

  /* ---------- Alerts helpers ---------- */
  const showError = (msg)=>{ alertBox.textContent = msg; alertBox.classList.remove('hidden'); };
  const hideError = ()=> alertBox.classList.add('hidden');
  const showInfo  = (msg)=>{ infoBox.textContent = msg; infoBox.classList.remove('hidden'); };
  const hideInfo  = ()=> infoBox.classList.add('hidden');

  /* ---------- Caps Lock chip ---------- */
  function caps(e){
    const on = e.getModifierState && e.getModifierState('CapsLock');
    if (capsNote) capsNote.classList.toggle('hidden', !on);
  }
  pwEl.addEventListener('keyup', caps);
  pwEl.addEventListener('keydown', caps);

  /* ---------- Password meter ---------- */
  pwEl.addEventListener('input', () => {
    const v = pwEl.value;
    let score = 0;
    if (v.length >= 6) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const widths = ['12%','38%','64%','88%','100%'];
    const labels = ['weak','fair','okay','good','strong'];
    pwBar.style.width = widths[score];
    pwLabel.textContent = labels[score];
  });

  /* ---------- Show/Hide password ---------- */
  toggle.addEventListener('click', () => {
    const show = pwEl.type === 'password';
    pwEl.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    eyeOn.classList.toggle('hidden', show);
    eyeOff.classList.toggle('hidden', !show);
    pwEl.focus();
  });





  /* ---------- Auth + lockout ---------- */
  const MAX_TRIES = 5, LOCK_MS = 60_000;
  const triesKey = 'atiera_login_tries';
  const lockKey  = 'atiera_login_lock';
  let   lockTimer = null;

  const num = key => Number(localStorage.getItem(key) || '0');
  const setNum = (key,val) => localStorage.setItem(key, String(val));

  function mmss(ms){
    const s = Math.max(0, Math.ceil(ms/1000));
    const m = Math.floor(s/60);
    const r = s % 60;
    return (m? `${m}:${String(r).padStart(2,'0')}` : `${r}s`);
  }

  function startLockCountdown(until){
    clearInterval(lockTimer);
    submitBtn.disabled = true;
    const tick = () => {
      const left = until - Date.now();
      if (left <= 0){
        clearInterval(lockTimer);
        localStorage.removeItem(lockKey);
        setNum(triesKey, 0);
        submitBtn.disabled = false;
        btnText.textContent = 'Sign In';
        hideError(); hideInfo();
        return;
      }
      btnText.textContent = `Locked ${mmss(left)}`;
      showError(`Too many attempts. Try again in ${mmss(left)}.`);
    };
    tick();
    lockTimer = setInterval(tick, 250);
  }

  function checkLock(){
    const until = Number(localStorage.getItem(lockKey) || '0');
    if (until > Date.now()) { startLockCountdown(until); return true; }
    return false;
  }

  function startLoading(){ submitBtn.disabled = true; btnText.textContent = 'Checking…'; }
  function stopLoading(ok=false){
    if (ok){ btnText.textContent = 'Success'; }
    else { btnText.textContent = 'Sign In'; submitBtn.disabled = false; }
  }

  function shakeCard(){
    const card = document.getElementById('card');
    card.style.animation = 'shakeX .35s ease-in-out';
    setTimeout(()=> card.style.animation = '', 360);
  }

  /* ---------- LOGIN SUBMIT ---------- */
  form.addEventListener('submit', async (e) => {
    hideError(); hideInfo();
    if (checkLock()) {
      e.preventDefault();
      return;
    }

    const u = userEl.value.trim();
    const p = pwEl.value;
    if (!u || !p) { 
      e.preventDefault();
      showError('Please enter username/email and password.'); 
      return; 
    }

    startLoading();
    // Form will submit to PHP backend for authentication
  });

  // Resume countdown if locked
  checkLock();

  /* ---------- Auto-float labels for pre-filled values ---------- */
  function checkPreFilledInputs() {
    const inputs = document.querySelectorAll('.input');
    inputs.forEach(input => {
      if (input.value) {
        input.classList.add('has-value');
      }
    });
  }

  // Check on page load
  checkPreFilledInputs();

  /* ---------- Dynamic label floating ---------- */
  function handleInputChange(input) {
    if (input.value) {
      input.classList.add('has-value');
    } else {
      input.classList.remove('has-value');
    }
  }

  // Add event listeners for all inputs
  document.querySelectorAll('.input').forEach(input => {
    input.addEventListener('input', () => handleInputChange(input));
    input.addEventListener('blur', () => handleInputChange(input));
  });
</script>

</body>
</html>
