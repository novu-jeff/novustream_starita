<style>
    .portal-welcome { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 28px; overflow: hidden; margin-top: 3rem 0 3rem 0; padding: 28px 30px; border-radius: 12px; background: linear-gradient(110deg,#32667e 0%,#32667e 58%,#087eaa 100%); color: #fff; box-shadow: 0 8px 20px rgba(10,41,64,.12); }
    .portal-welcome-pattern { position: absolute; top: -85px; right: 70px; width: 290px; height: 290px; border: 22px solid rgba(255,255,255,.08); border-radius: 50%; pointer-events: none; }
    .portal-welcome-pattern::after { position: absolute; right: -175px; bottom: -30px; width: 190px; height: 190px; border: 15px solid rgba(255,255,255,.08); border-radius: 50%; content: ''; }
    .portal-welcome-main,.portal-welcome-actions { position: relative; z-index: 1; }
    .portal-welcome-main { display: flex; align-items: center; gap: 17px; min-width: 0; }
    .portal-welcome-avatar-wrap { position: relative; flex: 0 0 auto; }
    .portal-welcome-avatar { display: grid; place-items: center; width: 68px; height: 68px; flex: 0 0 auto; border: 2px solid #fff; border-radius: 50%; background-color: #0000; color: #fff; font: 800 22px 'Plus Jakarta Sans',sans-serif; }
    .portal-active-badge { position: absolute; right: -7px; bottom: -7px; padding: 3px 8px; border: 2px solid #198754; border-radius: 999px; background: #198754; color: #fff; font-size: 9px; font-weight: 700; line-height: 1.2; }
    .portal-welcome-copy { min-width: 0; }
    .portal-eyebrow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 7px; color: #d3eff9; font-size: 10px; font-weight: 600; }
    .portal-eyebrow i { color: #72dfb7; font-size: 15px; }
    .portal-eyebrow span { padding-left: 9px; border-left: 1px solid rgba(255,255,255,.35); color: rgba(255,255,255,.78); }
    .portal-welcome h1 { margin: 0; color: #fff; font: 800 25px/1.2 'Plus Jakarta Sans',sans-serif; }
    .portal-account-meta { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 12px; color: #d8edf5; font-size: 10px; }
    .portal-account-meta span { display: inline-flex; align-items: center; gap: 5px; }
    .portal-account-meta i { color: #72cde9; font-size: 14px; }
    .portal-account-meta b { color: #fff; font-weight: 700; }
    .portal-welcome-actions { display: flex; align-items: center; gap: 9px; flex: 0 0 auto; }
    .portal-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 15px; border: 1px solid transparent; border-radius: 7px; font-size: 11px; font-weight: 700; text-decoration: none; white-space: nowrap; transition: background .16s ease,border-color .16s ease,transform .16s ease; }
    .portal-button:hover { transform: translateY(-1px); }
    .portal-button i { font-size: 16px; }
    .portal-button-glass { border-color: rgba(255,255,255,.35); background: rgba(255,255,255,.12); color: #fff; }
    .portal-button-glass:hover { background: rgba(255,255,255,.21); color: #fff; }
    .portal-button-light { background: #fff; color: #0a2940; }
    .portal-button-light:hover { background: #eaf6fa; color: #0a2940; }
    .portal-kpis { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 13px; margin: 1.5rem 0 1.5rem 0; }
    .portal-kpi { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; min-height: 112px; padding: 17px; border: 1px solid #e0e7eb; border-radius: 9px; background: #fff; box-shadow: 0 2px 6px rgba(15,61,94,.035); }
    .portal-kpi p { margin: 0; color: #73828c; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .portal-kpi strong { display: block; margin-top: 7px; color: #172b3a; font: 800 21px 'Plus Jakarta Sans',sans-serif; }
    .portal-kpi-due strong { color: #c83d4b; }
    .portal-kpi strong small { display: inline; color: #60727e; font: 700 12px 'Inter',sans-serif; }
    .portal-kpi .portal-kpi-date { font-size: 17px; }
    .portal-kpi > div > small { display: flex; align-items: center; gap: 6px; margin-top: 6px; color: #71818c; font-size: 9px; }
    .portal-status-dot { width: 7px; height: 7px; border-radius: 50%; background: #d94d5a; }
    .portal-kpi-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 auto; border-radius: 8px; background: #fff0f1; color: #c83d4b; font-size: 19px; }
    .portal-kpi-icon-amber { background: #fff7e6; color: #bb7b12; }
    .portal-kpi-icon-blue { background: #eaf6fb; color: #167da5; }
    .portal-kpi-icon-green { background: #eaf7f0; color: #25885d; }
    @media (max-width: 820px) {
        .portal-welcome { align-items: flex-start; flex-direction: column; gap: 18px; padding: 23px; }
        .portal-welcome-actions { width: 100%; }
        .portal-welcome-actions .portal-button { flex: 1; }
        .portal-kpis { grid-template-columns: repeat(2,minmax(0,1fr)); margin: 1.5rem 0 1.5rem 0; }
    }
    @media (max-width: 520px) {
        .portal-welcome { margin-top: 15px; padding: 18px; }
        .portal-welcome-main { align-items: center; flex-direction: column; gap: 14px; width: 100%; }
        .portal-welcome-avatar-wrap { margin-top: 4px; }
        .portal-welcome-avatar { width: 78px; height: 78px; font-size: 25px; }
        .portal-welcome-copy { width: 100%; text-align: center; }
        .portal-welcome h1 { font-size: 19px; }
        .portal-eyebrow { justify-content: center; gap: 5px; font-size: 9px; }
        .portal-eyebrow span { border: 0; padding-left: 0; }
        .portal-account-meta { align-items: center; flex-direction: column; gap: 6px; margin-top: 9px; }
        .portal-welcome-actions { flex-direction: column; align-items: stretch; }
        .portal-kpis { gap: 8px; margin: 1.5rem 0 1.5rem 0; }
        .portal-kpi { min-height: 100px; padding: 12px; }
        .portal-kpi strong { font-size: 17px; overflow-wrap: anywhere; }
        .portal-kpi .portal-kpi-date { font-size: 14px; }
        .portal-kpi-icon { width: 30px; height: 30px; font-size: 16px; }
    }
</style>