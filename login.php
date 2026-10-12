<?php
session_start();

// Check if user is already logged in and redirect to reservation portal
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['department'])) {
    header("Location: reservation.php");
    exit();
}

$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

$info_message = '';
if (isset($_GET['message'])) {
    $info_message = htmlspecialchars($_GET['message']);
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>ATIERA — Reservation Login</title>
<link rel="icon" href="images/logo2.png">
<link href="css/tailwind.css" rel="stylesheet" />

<style>
  :root{
    --blue-600:#1b2f73; --blue-700:#15265e; --blue-800:#0f1c49; --blue-a:#2342a6;
    --gold:#d4af37; --ink:#0f172a; --muted:#64748b;
    --ring:0 0 0 3px rgba(35,66,166,.28);
    --card-bg: rgba(255,255,255,.95); --card-border: rgba(226,232,240,.9);
  }
  @media (prefers-color-scheme: dark){ :root{ --ink:#e5e7eb; --muted:#9ca3af; } }

  body{
    min-height:100svh; margin:0; color:var(--ink);
    display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    background:#fff;
  }
  @media (max-width: 767px){
    body{ grid-template-columns:1fr; }
  }

  .reveal { opacity:0; transform:translateY(8px); animation:reveal .45s .05s both; }
  @keyframes reveal { to { opacity:1; transform:none; } }

  .card{
    background:#fff;
    border:1px solid var(--card-border); border-radius:18px; box-shadow:0 16px 48px rgba(2,6,23,.18);
  }
  html.dark .card{ background:rgba(17,24,39,.92); border-color:rgba(71,85,105,.55); box-shadow:0 16px 48px rgba(0,0,0,.5); }

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
  .input.has-value + .float-label,
  .input:-webkit-autofill + .float-label,
  .input:autofill + .float-label{
    top:0; transform:translateY(-50%) scale(.92); color:var(--blue-a); background:#fff;
  }
  html.dark .input:focus + .float-label,
  html.dark .input:not(:placeholder-shown) + .float-label,
  html.dark .input.has-value + .float-label,
  html.dark .input:-webkit-autofill + .float-label,
  html.dark .input:autofill + .float-label{ background:#0b1220; }

  @keyframes autofillStart { from { opacity:1; } to { opacity:1; } }
  .input:-webkit-autofill{
    animation-name:autofillStart; animation-duration:.01s;
    -webkit-text-fill-color:#0f172a;
    box-shadow:0 0 0 1000px #fff inset;
    caret-color:#0f172a;
  }
  html.dark .input:-webkit-autofill{
    -webkit-text-fill-color:#e5e7eb;
    box-shadow:0 0 0 1000px #0b1220 inset;
    caret-color:#e5e7eb;
  }
  .icon-right{
    position:absolute; top:0; bottom:0; right:.45rem; width:2.25rem;
    margin:0; padding:0; border:0; background:transparent; color:#64748b;
    display:flex; align-items:center; justify-content:center; line-height:0;
  }
  .icon-right svg{ display:block; width:20px; height:20px; }
  .icon-right svg.hidden{ display:none; }
  span.icon-right svg{ transform:translateY(1px); }
  html.dark .icon-right{ color:#94a3b8; }

  .caps-note{
    position:absolute; right:0; bottom:calc(100% + .35rem);
    font-size:.75rem; line-height:1; padding:.25rem .45rem; border-radius:.4rem;
    background:#fffbeb; border:1px solid #fcd34d; color:#92400e;
  }
  html.dark .caps-note{ background:rgba(120,53,15,.35); border-color:#b45309; color:#fde68a; }

  .brand-pane{
    min-height:100svh; display:flex; align-items:center; justify-content:center;
    padding:3.5rem; background:#0f1c49;
  }
  html.dark .brand-pane{ background:#070c26; }
  .brand-copy{ width:min(100%, 26rem); color:#fff; }
  .brand-copy img{ width:11rem; margin:0 0 1.75rem; }
  .brand-rule{ width:3rem; height:3px; margin:0 0 1.25rem; background:var(--gold); border:0; }
  .brand-copy h1{ font-size:2.4rem; line-height:1.12; font-weight:800; letter-spacing:-.02em; margin:0; }
  .brand-copy p{ margin:1.1rem 0 0; color:rgba(255,255,255,.88); font-size:1.05rem; }
  @media (max-width: 767px){
    .brand-pane{ min-height:auto; padding:1.75rem 1.25rem 1.5rem; }
    .brand-copy img{ width:8rem; margin-bottom:1rem; }
    .brand-copy h1{ font-size:1.55rem; }
    .brand-copy p{ margin-top:.6rem; font-size:.95rem; }
  }

  .auth-pane{
    min-height:100svh; display:flex; align-items:center; justify-content:center;
    padding:2.5rem 1.5rem; background:#fff;
  }
  html.dark .auth-pane{ background:#0b1538; }
  @media (max-width: 767px){
    .auth-pane{ min-height:auto; padding:1.25rem 1rem 2rem; align-items:stretch; }
  }
  .auth-pane .card{ width:min(100%, 26.5rem); }
  .card-title{ margin:0; color:#0f172a; font-size:1.25rem; font-weight:650; line-height:1.3; }
  .card-lede{ margin:.35rem 0 0; color:#64748b; font-size:.9rem; line-height:1.45; }
  html.dark .card-title{ color:#e5e7eb; }
  html.dark .card-lede{ color:#9ca3af; }
  .mode-btn{ flex-shrink:0; color:#0f172a; }
  html.dark .mode-btn{ color:#e5e7eb; border-color:#334155; }
  .mobile-logo{ display:none; }

  .btn{
    width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:linear-gradient(180deg, var(--blue-600), var(--blue-800));
    color:#fff; font-weight:800; border-radius:14px; padding:.95rem 1rem; border:1px solid rgba(255,255,255,.06);
    transition:transform .08s ease, filter .15s ease, box-shadow .2s ease; box-shadow:0 8px 18px rgba(2,6,23,.18);
  }
  .btn:hover{ filter:saturate(1.08); box-shadow:0 12px 26px rgba(2,6,23,.26); }
  .btn:active{ transform:translateY(1px) scale(.99); }
  .btn[disabled]{ opacity:.85; cursor:not-allowed; }

  .alert{ border-radius:12px; padding:.65rem .8rem; font-size:.9rem }
  .alert-error{ border:1px solid #fecaca; background:#fef2f2; color:#b91c1c }
  .alert-info{ border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3 }
  .alert-success{ border:1px solid #bbf7d0; background:#f0fdf4; color:#166534 }
  html.dark .alert-error{ background:#3f1b1b; border-color:#7f1d1d; color:#fecaca }
  html.dark .alert-info{ background:#1e1b4b; border-color:#3730a3; color:#c7d2fe }
  html.dark .alert-success{ background:#1b3a1b; border-color:#166534; color:#bbf7d0 }
</style>
</head>

<body class="select-none">

  <section class="brand-pane">
    <div class="brand-copy reveal">
      <img src="images/logo.png" alt="ATIERA" class="select-none" draggable="false">
      <hr class="brand-rule">
      <h1>ATIERA <span style="color:var(--gold)">HOTEL &amp; RESTAURANT</span><br>Reservation Portal</h1>
      <p>Secure • Fast • Intuitive</p>
    </div>
  </section>

  <main class="auth-pane">
    <div id="card" class="card p-6 sm:p-8 reveal">
      <div class="flex items-start justify-between gap-4 mb-5">
        <div class="min-w-0">
          <img src="images/logo.png" alt="ATIERA" class="mobile-logo select-none" draggable="false">
          <h2 class="card-title">Sign in</h2>
          <p class="card-lede">Use your portal credentials to continue.</p>
        </div>
        <button id="modeBtn" type="button" class="mode-btn px-3 py-2 rounded-lg border border-slate-200 text-sm hover:bg-white/60" aria-pressed="false" title="Toggle dark mode" aria-label="Toggle dark mode">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        </button>
      </div>

      <div id="alert" class="alert alert-error hidden mb-4" role="alert"></div>
      <div id="info" class="alert alert-info hidden mb-4" role="status"></div>

      <?php if ($error_message !== ''): ?>
      <div class="alert alert-error mb-4" role="alert"><?php echo $error_message; ?></div>
      <?php endif; ?>

      <?php if ($info_message !== ''): ?>
      <div class="alert alert-info mb-4" role="status"><?php echo $info_message; ?></div>
      <?php endif; ?>

      <form id="loginForm" class="flex flex-col gap-4" method="POST" action="includes/login-backend.php">
        <div class="field">
          <input id="username" name="username" type="text" autocomplete="username" class="input peer" placeholder=" " required>
          <label for="username" class="float-label">Username or Email</label>
          <span class="icon-right" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-5.33 0-8 2.67-8 5v1h16v-1c0-2.33-2.67-5-8-5Z" fill="currentColor"/></svg>
          </span>
        </div>

        <div class="field">
          <span id="capsNote" class="caps-note hidden">Caps Lock is ON</span>
          <input id="password" name="password" type="password" autocomplete="current-password" class="input pr-12 peer" placeholder=" " required>
          <label for="password" class="float-label">Password</label>
          <button type="button" id="togglePw" class="icon-right rounded-lg hover:bg-slate-100" aria-label="Show password" aria-pressed="false" title="Show or hide password">
            <svg id="eyeOn" viewBox="0 0 24 24" fill="none"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Z" fill="currentColor"/></svg>
            <svg id="eyeOff" class="hidden" viewBox="0 0 24 24" fill="none"><path d="M3 4.27 4.28 3 21 19.72 19.73 21l-2.2-2.2A11.73 11.73 0 0 1 12 19c-5 0-9.27-3.11-11-7a12.71 12.71 0 0 1 4.1-4.73L3 4.27ZM12 7a5 5 0 0 1 5 5 5 5 0 0 1-.46 2.11L14.6 12.17A2.5 2.5 0 0 0 11.83 9.4L9.9 7.46A4.84 4.84 0 0 1 12 7Z" fill="currentColor"/></svg>
          </button>
        </div>

        <button id="submitBtn" type="submit" class="btn" aria-live="polite">
          <span id="btnText">Sign In</span>
        </button>
      </form>

      <p class="text-xs text-center text-slate-500 mt-5">© 2025 ATIERA BSIT 4101 - GROUP 8</p>
    </div>
  </main>

<script>
  const $ = (s, r=document)=>r.querySelector(s);

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
  const modeBtn   = $('#modeBtn');

  modeBtn.addEventListener('click', ()=>{
    const root = document.documentElement;
    const dark = root.classList.toggle('dark');
    modeBtn.setAttribute('aria-pressed', String(dark));
  });

  const showError = (msg)=>{ alertBox.textContent = msg; alertBox.classList.remove('hidden'); };
  const hideError = ()=> alertBox.classList.add('hidden');
  const hideInfo  = ()=> infoBox.classList.add('hidden');

  function caps(e){
    const on = e.getModifierState && e.getModifierState('CapsLock');
    if (capsNote) capsNote.classList.toggle('hidden', !on);
  }
  pwEl.addEventListener('keyup', caps);
  pwEl.addEventListener('keydown', caps);

  toggle.addEventListener('click', () => {
    const show = pwEl.type === 'password';
    pwEl.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    eyeOn.classList.toggle('hidden', show);
    eyeOff.classList.toggle('hidden', !show);
    pwEl.focus();
  });

  const LOCK_MS = 60_000;
  const triesKey = 'atiera_reservation_login_tries';
  const lockKey  = 'atiera_reservation_login_lock';
  let   lockTimer = null;

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

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    hideError(); hideInfo();
    if (checkLock()) {
      return;
    }

    const u = userEl.value.trim();
    const p = pwEl.value;
    if (!u || !p) {
      showError('Please enter username and password.');
      return;
    }

    startLoading();
    form.submit();
  });

  checkLock();

  function checkPreFilledInputs() {
    document.querySelectorAll('.input').forEach(input => {
      input.classList.toggle('has-value', input.value !== '');
    });
  }

  function handleInputChange(input) {
    input.classList.toggle('has-value', input.value !== '');
  }

  document.querySelectorAll('.input').forEach(input => {
    input.addEventListener('input', () => handleInputChange(input));
    input.addEventListener('change', () => handleInputChange(input));
    input.addEventListener('blur', () => handleInputChange(input));
    input.addEventListener('animationstart', (e) => {
      if (e.animationName === 'autofillStart') input.classList.add('has-value');
    });
  });
  checkPreFilledInputs();
  [100, 400, 1000].forEach((ms) => setTimeout(checkPreFilledInputs, ms));
</script>

</body>
</html>
