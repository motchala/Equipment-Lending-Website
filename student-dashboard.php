<?php
// student-dashboard.php
// Stateless PHP — all student identity lives in JS sessionStorage.
// Populated by student-portal.php after a valid faculty code is verified.
// If no session AND no saved receipt → JS redirects back to student-portal.php.
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSync | Student Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    /* ================================================================
       PUPSYNC STUDENT DASHBOARD
       Lighter maroon palette (#a32020) — visually distinct from
       the faculty dashboard (#800000 / #5a0000).
       Shell mirrors faculty-dashboard structure; faculty-only panels
       and actions are fully absent.
    ================================================================ */

    /* ── Design Tokens ───────────────────────────────────────────── */
    :root {
        --color-primary:            #a32020;
        --color-primary-container:  #a32020;
        --color-on-primary:         #ffffff;
        --color-secondary:          #705050;
        --color-surface-lowest:     #ffffff;
        --color-surface:            #fff9f9;
        --color-surface-low:        #fef3f3;
        --color-surface-container:  #fae9e9;
        --color-surface-high:       #f4d8d8;
        --color-surface-highest:    #edcbcb;
        --color-outline:            #b08080;
        --color-outline-variant:    #e8cccc;
        --color-on-surface:         #1f1414;
        --color-on-surface-variant: #5c3d3d;
        --color-background:         #fff9f9;
        --color-error:              #c0392b;
        --color-success:            #166534;
        --color-success-container:  #dcfce7;
        --radius-sm:   8px;
        --radius-md:   12px;
        --radius-lg:   16px;
        --radius-xl:   20px;
        --radius-full: 9999px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
        --shadow-md: 0 4px 16px rgba(163,32,32,.10), 0 2px 6px rgba(0,0,0,.06);
        --shadow-lg: 0 10px 32px rgba(163,32,32,.14), 0 4px 12px rgba(0,0,0,.08);
        --sidebar-w:    272px;
        --topbar-h:     68px;
        --font-sans:    'Inter', 'Hanken Grotesk', system-ui, sans-serif;
        --font-display: 'Hanken Grotesk', 'Inter', system-ui, sans-serif;
        --transition:   0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* ── Reset & Base ────────────────────────────────────────────── */
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { height: 100%; }
    body {
        font-family: var(--font-sans);
        background: var(--color-background);
        color: var(--color-on-surface);
        display: flex; height: 100%;
        overflow: hidden;
        -webkit-font-smoothing: antialiased;
    }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--color-outline-variant); border-radius: 3px; }

    /* ── Side Navigation ─────────────────────────────────────────── */
    .side-nav {
        width: var(--sidebar-w);
        height: 100vh;
        background: var(--color-surface-low);
        border-right: 1px solid var(--color-outline-variant);
        display: flex; flex-direction: column;
        padding: 0 0 20px;
        position: fixed; left: 0; top: 0;
        z-index: 240; flex-shrink: 0;
        transition: transform var(--transition);
    }
    .side-nav-brand {
        display: flex; align-items: center; gap: 14px;
        padding: 20px 20px 22px; margin-bottom: 8px;
        background: linear-gradient(135deg, #a32020 0%, #7c1616 100%);
        position: relative; overflow: hidden;
    }
    .side-nav-brand::after {
        content: ''; position: absolute; inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M0 40L40 0H20L0 20M40 40V20L20 40'/%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
    }
    .side-nav-logo {
        width: 40px; height: 40px;
        background: rgba(255,255,255,.18); border-radius: var(--radius-md);
        display: flex; align-items: center; justify-content: center;
        color: #fff; flex-shrink: 0; position: relative; z-index: 1;
    }
    .side-nav-logo svg { width: 20px; height: 20px; }
    .side-nav-brand-text { position: relative; z-index: 1; }
    .side-nav-title {
        font-family: var(--font-display); font-weight: 700;
        font-size: 1.1rem; color: #fff; letter-spacing: -.3px;
    }
    .side-nav-title strong { font-weight: 800; }
    .side-nav-sub {
        font-size: .68rem; color: rgba(255,255,255,.65);
        text-transform: uppercase; letter-spacing: 1.5px; margin-top: 1px;
    }
    .student-mode-badge {
        display: inline-flex; align-items: center; gap: 4px;
        margin-top: 5px; padding: 2px 8px;
        background: rgba(255,255,255,.18);
        border: 1px solid rgba(255,255,255,.22);
        border-radius: var(--radius-full);
        font-size: .62rem; font-weight: 700;
        color: rgba(255,255,255,.92);
        text-transform: uppercase; letter-spacing: 1px;
    }
    .student-mode-badge .material-symbols-outlined { font-size: 10px; }

    .side-nav-section-label {
        font-size: .65rem; font-weight: 700;
        color: var(--color-secondary); opacity: .7;
        text-transform: uppercase; letter-spacing: 1.2px;
        padding: 16px 20px 6px;
    }
    .side-nav-links {
        flex: 1; display: flex; flex-direction: column;
        gap: 2px; padding: 4px 10px 0; overflow-y: auto;
    }
    .side-nav-footer {
        padding: 10px 10px 0;
        border-top: 1px solid var(--color-outline-variant);
        display: flex; flex-direction: column; gap: 2px;
    }
    .side-nav-item {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 14px; border-radius: var(--radius-full);
        color: var(--color-secondary); font-size: .875rem; font-weight: 500;
        text-decoration: none; cursor: pointer;
        border: none; background: none; width: 100%; text-align: left;
        font-family: var(--font-sans);
        transition: background var(--transition), color var(--transition);
    }
    .side-nav-item:hover { background: var(--color-surface-high); color: var(--color-on-surface); }
    .side-nav-item.active { background: var(--color-primary); color: #fff; font-weight: 600; }
    .side-nav-item .material-symbols-outlined { font-size: 20px; flex-shrink: 0; }
    .nav-badge {
        margin-left: auto; font-size: .6rem; font-weight: 700;
        padding: 2px 7px; border-radius: var(--radius-full);
        background: var(--color-surface-high); color: var(--color-secondary);
        text-transform: uppercase; letter-spacing: .5px;
    }
    .side-nav-item.active .nav-badge { background: rgba(255,255,255,.22); color: rgba(255,255,255,.9); }

    /* Student chip in sidebar footer */
    .student-chip {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; margin-bottom: 2px;
        background: var(--color-surface-container);
        border: 1px solid var(--color-outline-variant);
        border-radius: var(--radius-lg);
    }
    .student-chip-avatar {
        width: 34px; height: 34px; border-radius: var(--radius-full);
        background: linear-gradient(135deg, #a32020, #7c1616);
        color: #fff; font-weight: 700; font-size: .8rem;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .student-chip-name {
        font-size: .8rem; font-weight: 600; color: var(--color-on-surface);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;
    }
    .student-chip-id { font-size: .7rem; color: var(--color-secondary); }

    /* ── Main Wrapper ────────────────────────────────────────────── */
    .main-wrapper {
        margin-left: var(--sidebar-w); flex: 1;
        display: flex; flex-direction: column;
        height: 100vh; overflow-x: hidden; overflow-y: visible;
    }

    /* ── Top Bar ─────────────────────────────────────────────────── */
    .top-bar {
        height: var(--topbar-h);
        background: var(--color-surface);
        border-bottom: 2px solid var(--color-outline-variant);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 28px;
        position: sticky; top: 0; z-index: 230; flex-shrink: 0;
        box-shadow: 0 2px 12px rgba(163,32,32,.07);
    }
    .top-bar-left { display: flex; align-items: center; gap: 12px; }
    .top-bar-page-title {
        font-family: var(--font-display); font-size: 1.05rem;
        font-weight: 700; color: var(--color-on-surface); letter-spacing: -.2px;
    }
    .auth-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 12px;
        background: var(--color-surface-container);
        border: 1px solid var(--color-outline-variant);
        border-radius: var(--radius-full);
        font-size: .75rem; font-weight: 600; color: var(--color-primary);
    }
    .auth-chip .material-symbols-outlined { font-size: 14px; }
    .top-bar-actions { display: flex; align-items: center; gap: 8px; }
    .top-bar-divider { width: 1px; height: 28px; background: var(--color-outline-variant); margin: 0 4px; }
    .top-bar-icon-btn {
        width: 40px; height: 40px; border-radius: var(--radius-full);
        border: 1px solid var(--color-outline-variant); background: transparent;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        color: var(--color-on-surface-variant);
        transition: background var(--transition), color var(--transition);
    }
    .top-bar-icon-btn:hover { background: var(--color-surface-high); color: var(--color-on-surface); }
    .top-bar-icon-btn .material-symbols-outlined { font-size: 20px; }

    /* Avatar + dropdown */
    .student-dd-wrap { position: relative; }
    .top-bar-avatar {
        width: 36px; height: 36px; border-radius: var(--radius-full);
        background: linear-gradient(135deg, #a32020, #7c1616);
        color: #fff; font-weight: 700; font-size: .8rem;
        border: 2px solid transparent; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: border-color var(--transition), box-shadow var(--transition);
    }
    .top-bar-avatar:hover { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-surface-high); }
    .student-dropdown {
        position: absolute; top: calc(100% + 12px); right: 0;
        min-width: 220px; background: var(--color-surface);
        border: 1px solid var(--color-outline-variant); border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg); z-index: 500; overflow: hidden;
        opacity: 0; visibility: hidden; pointer-events: none;
        transform: translateY(-8px);
        transition: opacity var(--transition), transform var(--transition), visibility var(--transition);
    }
    .student-dropdown.open { opacity: 1; visibility: visible; pointer-events: auto; transform: translateY(0); }
    .student-dropdown-header {
        padding: 14px 16px;
        background: var(--color-surface-container);
        border-bottom: 1px solid var(--color-outline-variant);
    }
    .student-dropdown-name { font-weight: 700; font-size: .875rem; color: var(--color-on-surface); margin-bottom: 2px; }
    .student-dropdown-id { font-size: .75rem; color: var(--color-secondary); }
    .student-dropdown-item {
        display: flex; align-items: center; gap: 10px;
        padding: 11px 16px; font-size: .875rem; font-weight: 500;
        color: var(--color-on-surface-variant); cursor: pointer;
        background: none; border: none; width: 100%; text-align: left;
        text-decoration: none; font-family: var(--font-sans);
        transition: background var(--transition);
    }
    .student-dropdown-item:hover { background: var(--color-surface-high); }
    .student-dropdown-item .material-symbols-outlined { font-size: 18px; }
    .student-dropdown-item.danger { color: var(--color-error); }

    /* ── App Main ────────────────────────────────────────────────── */
    .app-main {
        flex: 1; overflow-y: auto;
        padding: 28px 32px 48px;
        background: var(--color-background);
    }

    /* Panel visibility */
    .sd-panel { display: none; }
    .sd-panel.active { display: block; animation: sdFadeIn .2s ease both; }
    @keyframes sdFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

    /* ── Page header ─────────────────────────────────────────────── */
    .sd-page-header { margin-bottom: 24px; }
    .sd-page-title {
        font-family: var(--font-display); font-size: 1.75rem;
        font-weight: 700; color: var(--color-on-surface);
        letter-spacing: -.4px; margin-bottom: 4px; line-height: 1.15;
    }
    .sd-page-subtitle { font-size: .875rem; color: var(--color-secondary); }

    /* ── Auth banner ─────────────────────────────────────────────── */
    .auth-banner {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 18px; margin-bottom: 24px;
        background: linear-gradient(135deg, rgba(163,32,32,.06) 0%, rgba(163,32,32,.02) 100%);
        border: 1px solid rgba(163,32,32,.15);
        border-left: 3px solid var(--color-primary);
        border-radius: var(--radius-lg);
    }
    .auth-banner-icon {
        width: 36px; height: 36px; border-radius: var(--radius-md);
        background: rgba(163,32,32,.1);
        display: flex; align-items: center; justify-content: center;
        color: var(--color-primary); flex-shrink: 0;
    }
    .auth-banner-icon .material-symbols-outlined { font-size: 20px; }
    .auth-banner-label {
        font-size: .72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .8px; color: var(--color-primary); margin-bottom: 2px;
    }
    .auth-banner-faculty { font-size: .875rem; font-weight: 600; color: var(--color-on-surface); }
    .auth-banner-note { font-size: .75rem; color: var(--color-secondary); margin-top: 1px; }

    /* Code-used warning banner */
    .code-used-banner {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 16px; margin-bottom: 24px;
        background: #fff8e1; border: 1px solid #ffe082; border-radius: var(--radius-lg);
        font-size: .85rem; color: #795548;
    }
    .code-used-banner .material-symbols-outlined { color: #f59e0b; font-size: 20px; flex-shrink: 0; }

    /* ── Catalog filters ─────────────────────────────────────────── */
    .catalog-filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .catalog-search-wrap {
        flex: 1; min-width: 200px;
        display: flex; align-items: center; gap: 10px;
        background: var(--color-surface-lowest);
        border: 1.5px solid var(--color-outline-variant);
        border-radius: var(--radius-md); padding: 10px 16px;
        transition: border-color var(--transition), box-shadow var(--transition);
    }
    .catalog-search-wrap:focus-within {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(163,32,32,.08);
    }
    .catalog-search-wrap .material-symbols-outlined { font-size: 18px; color: var(--color-secondary); flex-shrink: 0; }
    .catalog-search-wrap input {
        border: none; background: transparent; outline: none;
        font-family: var(--font-sans); font-size: .875rem;
        color: var(--color-on-surface); width: 100%;
    }
    .catalog-search-wrap input::placeholder { color: var(--color-secondary); }
    .catalog-filter-select {
        padding: 10px 14px;
        border: 1.5px solid var(--color-outline-variant);
        border-radius: var(--radius-md);
        background: var(--color-surface-lowest);
        color: var(--color-on-surface);
        font-family: var(--font-sans); font-size: .875rem;
        font-weight: 500; outline: none; cursor: pointer; min-width: 175px;
        transition: border-color var(--transition);
    }
    .catalog-filter-select:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(163,32,32,.08);
    }

    /* ── Equipment grid ──────────────────────────────────────────── */
    .eq-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 18px;
    }

    /* ── Equipment card ──────────────────────────────────────────── */
    .eq-card {
        background: #fff;
        border: 1px solid rgba(163,32,32,.08);
        border-radius: var(--radius-lg); overflow: hidden;
        box-shadow: 0 2px 12px -2px rgba(163,32,32,.07);
        transition: transform .28s cubic-bezier(.4,0,.2,1), box-shadow .28s, border-color .28s;
        display: flex; flex-direction: column;
    }
    .eq-card:hover {
        transform: translateY(-4px);
        border-color: rgba(163,32,32,.25);
        box-shadow: 0 12px 25px -5px rgba(163,32,32,.12);
    }
    .eq-card-img {
        width: 100%; height: 148px;
        background: linear-gradient(145deg, #fef3f3, #fae9e9);
        display: flex; align-items: center; justify-content: center;
    }
    .eq-card-img .material-symbols-outlined { font-size: 52px; color: var(--color-primary); opacity: .15; }
    .eq-card-body {
        padding: 14px 16px 16px; display: flex; flex-direction: column; flex: 1;
        background: rgba(255,255,255,.92);
        border-top: 1px solid rgba(163,32,32,.06);
    }
    .eq-card-name {
        font-family: var(--font-display); font-weight: 700; font-size: .95rem;
        color: #111827; margin-bottom: 4px; letter-spacing: -.1px;
        line-height: 1.3; transition: color .18s;
    }
    .eq-card:hover .eq-card-name { color: var(--color-primary); }
    .eq-card-cat {
        font-size: .7rem; font-weight: 600; color: #9ca3af;
        text-transform: uppercase; letter-spacing: .06em;
        display: flex; align-items: center; gap: 4px; margin-bottom: 10px;
    }
    .eq-card-cat .material-symbols-outlined { font-size: 11px; color: rgba(163,32,32,.4); }
    .stock-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 20px;
        font-size: .72rem; font-weight: 700; margin-bottom: 12px; width: fit-content;
    }
    .stock-avail { background: #dcfce7; color: #166534; border: 1px solid rgba(22,101,52,.12); }
    .stock-avail .material-symbols-outlined { font-size: 12px; color: #16a34a; }
    .stock-low   { background: #fff3cd; color: #92400e; border: 1px solid rgba(146,64,14,.12); }
    .stock-low   .material-symbols-outlined { font-size: 12px; color: #d97706; }
    .btn-borrow-card {
        width: 100%; padding: 10px;
        background: var(--color-primary); color: #fff;
        border: none; border-radius: var(--radius-sm);
        cursor: pointer; font-weight: 600; font-size: .875rem;
        font-family: var(--font-sans); margin-top: auto;
        box-shadow: 0 1px 3px rgba(163,32,32,.2);
        transition: background .18s, transform .18s, box-shadow .18s;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .btn-borrow-card .material-symbols-outlined { font-size: 16px; }
    .btn-borrow-card:hover:not(:disabled) {
        background: #8c1a1a; transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(163,32,32,.28);
    }
    .btn-borrow-card:disabled {
        background: #f3f4f6; color: #9ca3af;
        cursor: not-allowed; box-shadow: none; border: 1px solid #e5e7eb;
    }

    /* ── Empty state ─────────────────────────────────────────────── */
    .eq-empty { grid-column: 1 / -1; text-align: center; padding: 5rem 1rem; color: #9ca3af; }
    .eq-empty .material-symbols-outlined { font-size: 52px; display: block; margin-bottom: 14px; opacity: .22; color: var(--color-primary); }
    .eq-empty p { font-size: .9rem; font-weight: 500; }

    /* ── Coming Soon (Room panel) ────────────────────────────────── */
    .coming-soon-wrap { max-width: 460px; margin: 60px auto; text-align: center; padding: 48px 32px; background: var(--color-surface-lowest); border: 1px dashed var(--color-outline-variant); border-radius: var(--radius-xl); }
    .coming-soon-icon { width: 80px; height: 80px; border-radius: var(--radius-xl); background: var(--color-surface-container); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--color-primary); }
    .coming-soon-icon .material-symbols-outlined { font-size: 40px; opacity: .55; }
    .coming-soon-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 700; color: var(--color-on-surface); margin-bottom: 10px; }
    .coming-soon-desc { font-size: .9rem; color: var(--color-secondary); line-height: 1.65; margin-bottom: 20px; }
    .coming-soon-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; background: var(--color-surface-container); border: 1px solid var(--color-outline-variant); border-radius: var(--radius-full); font-size: .8rem; font-weight: 600; color: var(--color-primary); }
    .coming-soon-pill .material-symbols-outlined { font-size: 15px; }

    /* ── My Request panel ────────────────────────────────────────── */
    .no-request-wrap { max-width: 400px; margin: 60px auto; text-align: center; padding: 48px 32px; background: var(--color-surface-lowest); border: 1px dashed var(--color-outline-variant); border-radius: var(--radius-xl); }
    .no-request-wrap .material-symbols-outlined { font-size: 48px; color: var(--color-primary); opacity: .25; display: block; margin-bottom: 14px; }
    .no-request-title { font-family: var(--font-display); font-size: 1.1rem; font-weight: 700; color: var(--color-on-surface); margin-bottom: 8px; }
    .no-request-sub { font-size: .875rem; color: var(--color-secondary); line-height: 1.55; }
    .receipt-card { max-width: 480px; margin: 0 auto; }
    .receipt-head {
        padding: 22px 24px; text-align: center;
        background: linear-gradient(135deg, #a32020, #7c1616);
        border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    }
    .receipt-head .check-icon { font-size: 38px; color: #fff; display: block; margin-bottom: 8px; }
    .receipt-head h3 { font-family: var(--font-display); font-weight: 700; font-size: 1.1rem; color: #fff; margin-bottom: 3px; }
    .receipt-head p { font-size: .8rem; color: rgba(255,255,255,.75); }
    .receipt-body {
        background: var(--color-surface-lowest);
        border: 1px solid var(--color-outline-variant);
        border-top: none; border-radius: 0 0 var(--radius-xl) var(--radius-xl);
        padding: 20px 24px;
    }
    .receipt-row {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 9px 0; border-bottom: 1px solid var(--color-outline-variant); font-size: .875rem;
    }
    .receipt-row:last-of-type { border-bottom: none; }
    .receipt-label { color: var(--color-secondary); font-weight: 500; }
    .receipt-value { font-weight: 700; color: var(--color-on-surface); text-align: right; max-width: 60%; }
    .receipt-qr-section { text-align: center; padding: 16px 0 6px; border-top: 1px solid var(--color-outline-variant); margin-top: 12px; }
    .receipt-qr-section p { font-size: .75rem; color: var(--color-secondary); line-height: 1.55; margin-bottom: 14px; }

    /* ── Toast ───────────────────────────────────────────────────── */
    #sd-toast {
        position: fixed; bottom: 24px; left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: #1f1414; color: #fff;
        padding: 11px 22px; border-radius: var(--radius-full);
        font-size: .85rem; font-weight: 600;
        box-shadow: var(--shadow-lg); z-index: 9999;
        opacity: 0; pointer-events: none;
        transition: opacity .3s, transform .3s; white-space: nowrap;
    }
    #sd-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    #sd-toast.success { background: #166534; }
    #sd-toast.error   { background: var(--color-error); }

    /* ── Nav backdrop (mobile) ───────────────────────────────────── */
    .nav-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 239; }
    .nav-backdrop.open { display: block; }

    /* ── Responsive ──────────────────────────────────────────────── */
    @media (max-width: 1024px) {
        .side-nav { transform: translateX(-100%); transition: transform .3s cubic-bezier(.16,1,.3,1); }
        .side-nav.open { transform: translateX(0); }
        .main-wrapper { margin-left: 0; }
        .top-bar { padding: 0 16px; }
        .app-main { padding: 20px 16px 48px; }
    }
    @media (max-width: 640px) {
        .auth-chip { display: none; }
        .auth-banner { flex-direction: column; align-items: flex-start; gap: 10px; }
        .eq-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    }
    @media (max-width: 400px) {
        .eq-grid { grid-template-columns: 1fr; }
        .sd-page-title { font-size: 1.5rem; }
    }
    </style>
</head>
<body>

<!-- ── Side Navigation ──────────────────────────────────────────── -->
<nav class="side-nav" id="sideNav">
    <div class="side-nav-brand">
        <div class="side-nav-logo">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                <polyline points="2 17 12 22 22 17"/>
                <polyline points="2 12 12 17 22 12"/>
            </svg>
        </div>
        <div class="side-nav-brand-text">
            <div class="side-nav-title"><strong>PUP</strong>SYNC</div>
            <div class="side-nav-sub">Student Portal</div>
            <div class="student-mode-badge">
                <span class="material-symbols-outlined">school</span>
                Student Mode
            </div>
        </div>
    </div>

    <div class="side-nav-links">
        <div class="side-nav-section-label">Services</div>
        <button class="side-nav-item active" data-panel="panel-borrow" id="nav-borrow">
            <span class="material-symbols-outlined">inventory_2</span>
            Borrow Equipment
        </button>
        <button class="side-nav-item" data-panel="panel-room" id="nav-room">
            <span class="material-symbols-outlined">meeting_room</span>
            Reserve a Room
            <span class="nav-badge">Soon</span>
        </button>
        <button class="side-nav-item" data-panel="panel-request" id="nav-request">
            <span class="material-symbols-outlined">receipt_long</span>
            My Request
        </button>
    </div>

    <div class="side-nav-footer">
        <div class="student-chip">
            <div class="student-chip-avatar" id="sidebarInitials">ST</div>
            <div style="min-width:0;">
                <div class="student-chip-name" id="sidebarName">—</div>
                <div class="student-chip-id" id="sidebarId">—</div>
            </div>
        </div>
        <a href="student-portal.php" class="side-nav-item">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Portal
        </a>
    </div>
</nav>

<!-- ── Main Wrapper ─────────────────────────────────────────────── -->
<div class="main-wrapper">

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="top-bar-left">
            <button class="top-bar-icon-btn" id="mobileMenuBtn"
                style="display:none;" aria-label="Open navigation">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <span class="top-bar-page-title" id="topBarTitle">Borrow Equipment</span>
            <div class="auth-chip" id="authChip">
                <span class="material-symbols-outlined">verified_user</span>
                <span id="authChipText">Authorized session</span>
            </div>
        </div>
        <div class="top-bar-actions">
            <div class="top-bar-divider"></div>
            <div class="student-dd-wrap" id="studentDdWrap">
                <button class="top-bar-avatar" id="avatarBtn"
                    aria-label="Student menu" aria-expanded="false">
                    <span id="topBarInitials">ST</span>
                </button>
                <div class="student-dropdown" id="studentDropdown">
                    <div class="student-dropdown-header">
                        <div class="student-dropdown-name" id="ddName">—</div>
                        <div class="student-dropdown-id"   id="ddId">—</div>
                    </div>
                    <a href="student-portal.php" class="student-dropdown-item">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to Portal
                    </a>
                    <button class="student-dropdown-item danger" id="endSessionBtn">
                        <span class="material-symbols-outlined">logout</span>
                        End Session
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- App Main -->
    <main class="app-main" id="appMain">

        <!-- ── Panel: Borrow Equipment ──────────────────────────── -->
        <div class="sd-panel active" id="panel-borrow">
            <div class="sd-page-header">
                <div class="sd-page-title">Borrow Equipment</div>
                <div class="sd-page-subtitle">Browse available items and submit a borrow request</div>
            </div>

            <!-- Auth / code-used banner — swapped by JS -->
            <div id="panelBannerSlot"></div>

            <!-- Filters -->
            <div class="catalog-filters" id="catalogFilters">
                <div class="catalog-search-wrap">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="equipSearch" placeholder="Search equipment…">
                </div>
                <select class="catalog-filter-select" id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>

            <!-- Grid -->
            <div class="eq-grid" id="equipGrid">
                <div class="eq-empty">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <p>Loading equipment…</p>
                </div>
            </div>
        </div>

        <!-- ── Panel: Reserve a Room ────────────────────────────── -->
        <div class="sd-panel" id="panel-room">
            <div class="sd-page-header">
                <div class="sd-page-title">Reserve a Room</div>
                <div class="sd-page-subtitle">Book lecture halls, labs, or study rooms</div>
            </div>
            <div class="coming-soon-wrap">
                <div class="coming-soon-icon">
                    <span class="material-symbols-outlined">meeting_room</span>
                </div>
                <div class="coming-soon-title">Coming Soon</div>
                <div class="coming-soon-desc">
                    Room reservation is currently being set up. Once live, you'll be able to book
                    lecture halls, computer labs, and study rooms right here with the same
                    faculty code process.
                </div>
                <div class="coming-soon-pill">
                    <span class="material-symbols-outlined">hourglass_top</span>
                    In Development
                </div>
            </div>
        </div>

        <!-- ── Panel: My Request ────────────────────────────────── -->
        <div class="sd-panel" id="panel-request">
            <div class="sd-page-header">
                <div class="sd-page-title">My Request</div>
                <div class="sd-page-subtitle">Your borrow receipt and QR code</div>
            </div>
            <div id="requestPanelContent">
                <!-- Populated by JS -->
            </div>
        </div>

    </main>
</div><!-- /.main-wrapper -->

<!-- ── Borrow Form Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="borrowModal" tabindex="-1" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"
            style="border-radius:20px;border:none;box-shadow:0 10px 40px rgba(163,32,32,.22);">

            <div class="modal-header"
                style="background:linear-gradient(135deg,#a32020 0%,#7c1616 100%);
                       border-radius:20px 20px 0 0;border-bottom:none;padding:22px 28px;">
                <div>
                    <h5 class="modal-title"
                        style="color:#fff;font-family:var(--font-display);font-weight:700;
                               font-size:1.1rem;margin-bottom:3px;">
                        Borrow Equipment
                    </h5>
                    <p id="borrowModalSubtitle"
                        style="color:rgba(255,255,255,.8);margin:0;font-size:.83rem;">
                        Complete the details below
                    </p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close" style="opacity:.8;"></button>
            </div>

            <div class="modal-body"
                style="padding:26px 28px 20px;background:var(--color-surface);">

                <div class="mb-3">
                    <label class="form-label"
                        style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                        Equipment
                    </label>
                    <input type="text" class="form-control" id="borrowEquipDisplay" readonly
                        style="background:var(--color-surface-container);
                               border-color:var(--color-outline-variant);
                               color:var(--color-on-surface);padding:10px 14px;font-weight:600;">
                </div>

                <div class="mb-3">
                    <label class="form-label"
                        style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                        Room / Location <span style="color:#c62828;">*</span>
                    </label>
                    <input type="text" class="form-control" id="borrowRoom"
                        placeholder="e.g. B-205 or AVR 1"
                        style="background:var(--color-surface-container);
                               border-color:var(--color-outline-variant);
                               color:var(--color-on-surface);padding:10px 14px;">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label"
                            style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                            Borrow Date <span style="color:#c62828;">*</span>
                        </label>
                        <input type="date" class="form-control" id="borrowDateInput"
                            style="background:var(--color-surface-container);
                                   border-color:var(--color-outline-variant);
                                   color:var(--color-on-surface);padding:10px 14px;">
                    </div>
                    <div class="col">
                        <label class="form-label"
                            style="font-weight:600;font-size:.875rem;color:var(--color-on-surface);">
                            Return Date <span style="color:#c62828;">*</span>
                        </label>
                        <input type="date" class="form-control" id="returnDateInput"
                            style="background:var(--color-surface-container);
                                   border-color:var(--color-outline-variant);
                                   color:var(--color-on-surface);padding:10px 14px;">
                    </div>
                </div>

                <div style="background:#fff8e1;border-radius:10px;padding:10px 14px;
                            font-size:.8rem;color:#795548;">
                    <span class="material-symbols-outlined"
                        style="font-size:15px;vertical-align:middle;margin-right:4px;">info</span>
                    Your request is <strong>auto-approved</strong> via faculty authorization.
                    The code will be consumed after submission.
                </div>

                <div id="borrowModalError"
                    style="display:none;color:#c62828;background:#fce4ec;
                           border-radius:10px;padding:10px 14px;font-size:.85rem;margin-top:12px;">
                </div>
            </div>

            <div class="modal-footer"
                style="border-top:1px solid var(--color-outline-variant);
                       padding:16px 28px 24px;background:var(--color-surface);
                       border-radius:0 0 20px 20px;">
                <button type="button" class="btn" data-bs-dismiss="modal"
                    style="padding:10px 24px;border-radius:12px;
                           border:1px solid var(--color-outline-variant);
                           color:var(--color-secondary);font-weight:600;background:transparent;">
                    Cancel
                </button>
                <button type="button" id="borrowSubmitBtn"
                    style="padding:10px 28px;border-radius:12px;
                           background:linear-gradient(135deg,#a32020 0%,#7c1616 100%);
                           color:#fff;font-weight:700;border:none;cursor:pointer;">
                    <span class="material-symbols-outlined"
                        style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>
                    Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mobile nav backdrop -->
<div class="nav-backdrop" id="navBackdrop"></div>

<!-- Toast -->
<div id="sd-toast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
'use strict';

/* ================================================================
   SESSION & RECEIPT
================================================================ */
let _session = null;
let _receipt  = null;
let _codeUsed = false;

try { const r = sessionStorage.getItem('pup_student_session'); if (r) _session = JSON.parse(r); } catch(e) {}
try { const r = sessionStorage.getItem('pup_last_receipt');    if (r) _receipt  = JSON.parse(r); } catch(e) {}

const _hasSession = !!(  _session && _session.valid);
const _hasReceipt = !!_receipt;

/* Guard: nothing to show → go back */
if (!_hasSession && !_hasReceipt) {
    window.location.href = 'student-portal.php';
}

/* Code is already consumed if no active session */
if (!_hasSession && _hasReceipt) _codeUsed = true;

/* ================================================================
   POPULATE IDENTITY ELEMENTS
================================================================ */
const _name = _hasSession
    ? (_session.student_name || '')
    : (_receipt && _receipt.student_name ? _receipt.student_name : '');
const _id   = _hasSession
    ? (_session.student_id || '')
    : (_receipt && _receipt.student_id ? _receipt.student_id : '');
const _faculty = _hasSession
    ? (_session.faculty_name || '—')
    : (_receipt && _receipt.faculty_name ? _receipt.faculty_name : '—');

const _initials = _name.trim().split(/\s+/).map(p => p[0] || '').slice(0,2).join('').toUpperCase() || 'ST';

document.getElementById('topBarInitials').textContent = _initials;
document.getElementById('sidebarInitials').textContent = _initials;
document.getElementById('sidebarName').textContent = _name || '—';
document.getElementById('sidebarId').textContent   = _id   || '—';
document.getElementById('ddName').textContent      = _name || '—';
document.getElementById('ddId').textContent        = _id   || '—';
document.getElementById('authChipText').textContent = 'Authorized by ' + _faculty;

/* ================================================================
   TOAST
================================================================ */
let _toastTimer;
function showToast(msg, type) {
    const t = document.getElementById('sd-toast');
    t.textContent = msg;
    t.className   = 'show' + (type ? ' ' + type : '');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => { t.className = ''; }, 3200);
}

/* ================================================================
   PANEL SWITCHING
================================================================ */
const _panelTitles = {
    'panel-borrow':  'Borrow Equipment',
    'panel-room':    'Reserve a Room',
    'panel-request': 'My Request',
};

function switchPanel(id) {
    document.querySelectorAll('.sd-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.side-nav-item[data-panel]').forEach(b => b.classList.remove('active'));
    const panel  = document.getElementById(id);
    const navBtn = document.querySelector('.side-nav-item[data-panel="' + id + '"]');
    if (panel)  panel.classList.add('active');
    if (navBtn) navBtn.classList.add('active');
    const titleEl = document.getElementById('topBarTitle');
    if (titleEl) titleEl.textContent = _panelTitles[id] || '';
    if (id === 'panel-request') renderReceiptPanel();
    closeMobileNav();
}

document.querySelectorAll('.side-nav-item[data-panel]').forEach(btn => {
    btn.addEventListener('click', () => switchPanel(btn.getAttribute('data-panel')));
});

/* ================================================================
   MOBILE NAV
================================================================ */
const _sideNav     = document.getElementById('sideNav');
const _navBackdrop = document.getElementById('navBackdrop');
const _mobileBtn   = document.getElementById('mobileMenuBtn');

function syncMobileBtn() {
    if (_mobileBtn) _mobileBtn.style.display = window.innerWidth <= 1024 ? 'flex' : 'none';
}
syncMobileBtn();
window.addEventListener('resize', syncMobileBtn);

if (_mobileBtn) {
    _mobileBtn.addEventListener('click', () => {
        _sideNav.classList.toggle('open');
        _navBackdrop.classList.toggle('open');
    });
}
if (_navBackdrop) _navBackdrop.addEventListener('click', closeMobileNav);

function closeMobileNav() {
    _sideNav.classList.remove('open');
    _navBackdrop.classList.remove('open');
}

/* ================================================================
   STUDENT DROPDOWN
================================================================ */
const _avatarBtn  = document.getElementById('avatarBtn');
const _dropdown   = document.getElementById('studentDropdown');

_avatarBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = _dropdown.classList.contains('open');
    _dropdown.classList.toggle('open', !open);
    _avatarBtn.setAttribute('aria-expanded', String(!open));
});
document.addEventListener('click', e => {
    if (!e.target.closest('#studentDdWrap')) {
        _dropdown.classList.remove('open');
        _avatarBtn.setAttribute('aria-expanded', 'false');
    }
});

document.getElementById('endSessionBtn').addEventListener('click', () => {
    try { sessionStorage.removeItem('pup_student_session'); } catch(e) {}
    try { sessionStorage.removeItem('pup_last_receipt');    } catch(e) {}
    window.location.href = 'student-portal.php';
});

/* ================================================================
   RENDER EQUIPMENT GRID
================================================================ */
const _inventory = _hasSession ? (_session.inventory || []) : [];
const _grid      = document.getElementById('equipGrid');
const _searchEl  = document.getElementById('equipSearch');
const _catFilter = document.getElementById('categoryFilter');
const _bannerSlot = document.getElementById('panelBannerSlot');

/* Auth / code-used banner */
if (_codeUsed) {
    _bannerSlot.innerHTML = `
        <div class="code-used-banner">
            <span class="material-symbols-outlined">info</span>
            Your faculty code has already been used. You can still view your receipt in
            <strong>My Request</strong>.
        </div>`;
    /* Hide filters and show a disabled grid instead of real data */
    if (document.getElementById('catalogFilters')) {
        document.getElementById('catalogFilters').style.display = 'none';
    }
    _grid.innerHTML = `
        <div class="eq-empty">
            <span class="material-symbols-outlined">lock</span>
            <p>Equipment browsing is only available during an active authorized session.</p>
        </div>`;
} else {
    /* Normal auth banner */
    _bannerSlot.innerHTML = `
        <div class="auth-banner">
            <div class="auth-banner-icon">
                <span class="material-symbols-outlined">verified_user</span>
            </div>
            <div>
                <div class="auth-banner-label">Authorized Session</div>
                <div class="auth-banner-faculty">${escHTML(_faculty)}</div>
                <div class="auth-banner-note">
                    One request is allowed per authorization code — the code is consumed on submission.
                </div>
            </div>
        </div>`;

    /* Build category filter options */
    const _cats = [...new Set(_inventory.map(i => i.category).filter(Boolean))].sort();
    _cats.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c; opt.textContent = c;
        _catFilter.appendChild(opt);
    });

    renderGrid();
    _searchEl.addEventListener('input',  renderGrid);
    _catFilter.addEventListener('change', renderGrid);
}

function renderGrid() {
    const q   = (_searchEl.value || '').toLowerCase().trim();
    const cat = _catFilter.value;

    const filtered = _inventory.filter(item => {
        const matchCat = !cat || item.category === cat;
        const matchQ   = !q   || item.item_name.toLowerCase().includes(q);
        return matchCat && matchQ;
    });

    _grid.innerHTML = '';

    if (!filtered.length) {
        _grid.innerHTML = `
            <div class="eq-empty">
                <span class="material-symbols-outlined">search_off</span>
                <p>No equipment matched your search.</p>
            </div>`;
        return;
    }

    filtered.forEach(item => {
        const qty   = parseInt(item.quantity) || 0;
        const avail = qty > 0;
        const low   = avail && qty <= 2;

        const stockCls  = low ? 'stock-low' : 'stock-avail';
        const stockIcon = low ? 'warning'   : 'check_circle';
        const stockText = low ? qty + ' left — limited' : qty + ' available';

        const card = document.createElement('div');
        card.className = 'eq-card';
        card.innerHTML = `
            <div class="eq-card-img">
                <span class="material-symbols-outlined">inventory_2</span>
            </div>
            <div class="eq-card-body">
                <div class="eq-card-name">${escHTML(item.item_name)}</div>
                <div class="eq-card-cat">
                    <span class="material-symbols-outlined">folder</span>
                    ${escHTML(item.category || 'General')}
                </div>
                <div class="stock-badge ${avail ? stockCls : ''}">
                    <span class="material-symbols-outlined">${avail ? stockIcon : 'cancel'}</span>
                    ${avail ? escHTML(stockText) : 'Out of stock'}
                </div>
                <button class="btn-borrow-card" data-name="${escAttr(item.item_name)}"
                    ${!avail ? 'disabled' : ''}>
                    <span class="material-symbols-outlined">${avail ? 'add_shopping_cart' : 'block'}</span>
                    ${avail ? 'Borrow' : 'Unavailable'}
                </button>
            </div>`;

        if (avail) {
            card.querySelector('.btn-borrow-card')
                .addEventListener('click', () => openBorrowModal(item.item_name));
        }
        _grid.appendChild(card);
    });
}

/* ================================================================
   BORROW MODAL
================================================================ */
let _selectedEquip  = '';
const _borrowModalEl = document.getElementById('borrowModal');
const _borrowModal   = new bootstrap.Modal(_borrowModalEl);
const _today         = new Date().toISOString().split('T')[0];
const _tomorrow      = new Date(Date.now() + 86400000).toISOString().split('T')[0];

function openBorrowModal(equipName) {
    _selectedEquip = equipName;
    document.getElementById('borrowEquipDisplay').value = equipName;
    document.getElementById('borrowRoom').value         = '';
    document.getElementById('borrowDateInput').value    = _today;
    document.getElementById('borrowDateInput').min      = _today;
    document.getElementById('returnDateInput').value    = _tomorrow;
    document.getElementById('returnDateInput').min      = _tomorrow;
    document.getElementById('borrowModalSubtitle').textContent = equipName;
    document.getElementById('borrowModalError').style.display  = 'none';
    const btn = document.getElementById('borrowSubmitBtn');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
    _borrowModal.show();
}

/* Keep return date min in sync with borrow date */
document.getElementById('borrowDateInput').addEventListener('change', function () {
    const retEl = document.getElementById('returnDateInput');
    const nextDay = new Date(new Date(this.value + 'T00:00:00').getTime() + 86400000)
                        .toISOString().split('T')[0];
    retEl.min = nextDay;
    if (retEl.value < nextDay) retEl.value = nextDay;
});

document.getElementById('borrowSubmitBtn').addEventListener('click', handleSubmit);

function showBorrowError(msg) {
    const el = document.getElementById('borrowModalError');
    el.textContent    = msg;
    el.style.display  = 'block';
}

/* ================================================================
   SUBMIT
================================================================ */
function handleSubmit() {
    document.getElementById('borrowModalError').style.display = 'none';

    if (!_session) {
        showBorrowError('Session expired. Please go back and re-enter a faculty code.');
        return;
    }

    const room   = (document.getElementById('borrowRoom').value || '').trim();
    const borrow = document.getElementById('borrowDateInput').value || '';
    const ret    = document.getElementById('returnDateInput').value || '';

    if (!room)         { showBorrowError('Please enter the room or location.'); return; }
    if (!borrow)       { showBorrowError('Please select a borrow date.'); return; }
    if (!ret)          { showBorrowError('Please select a return date.'); return; }
    if (ret < borrow)  { showBorrowError('Return date cannot be before the borrow date.'); return; }

    const btn = document.getElementById('borrowSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;"></span>Submitting…';

    fetch('includes/submit-student-borrow.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            code_db_id:    _session.code_db_id,
            faculty_id:    _session.faculty_id,
            faculty_name:  _session.faculty_name,
            student_name:  _session.student_name,
            student_id:    _session.student_id,
            equipment_name: _selectedEquip,
            room,
            borrow_date:   borrow,
            return_date:   ret,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
            showBorrowError(data.error);
            return;
        }

        /* Build and persist receipt */
        _receipt = {
            student_name: _session.student_name,
            student_id:   _session.student_id,
            faculty_name: _session.faculty_name,
            equipment:    _selectedEquip,
            room,
            borrow_date:  borrow,
            return_date:  ret,
            request_id:   data.request_id,
            return_token: data.return_token,
        };
        try { sessionStorage.setItem('pup_last_receipt', JSON.stringify(_receipt)); } catch(e) {}

        /* Consume session — code is now used */
        try { sessionStorage.removeItem('pup_student_session'); } catch(e) {}
        _session  = null;
        _codeUsed = true;

        _borrowModal.hide();
        showToast('Request submitted — approved!', 'success');

        /* Lock all borrow buttons */
        document.querySelectorAll('.btn-borrow-card:not(:disabled)').forEach(b => {
            b.disabled = true;
            b.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">lock</span> Code used';
        });

        /* Update auth banner to code-used state */
        if (_bannerSlot) {
            _bannerSlot.innerHTML = `
                <div class="code-used-banner">
                    <span class="material-symbols-outlined">info</span>
                    Your faculty code has been used. View your receipt in <strong>My Request</strong>.
                </div>`;
        }

        /* Switch to receipt */
        switchPanel('panel-request');
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">send</span>Submit Request';
        showBorrowError('Network error. Please check your connection and try again.');
    });
}

/* ================================================================
   RECEIPT PANEL
================================================================ */
function renderReceiptPanel() {
    const el = document.getElementById('requestPanelContent');

    if (!_receipt) {
        el.innerHTML = `
            <div class="no-request-wrap">
                <span class="material-symbols-outlined">receipt_long</span>
                <div class="no-request-title">No active request</div>
                <div class="no-request-sub">
                    Borrow an item from the equipment list and your receipt will appear here.
                </div>
            </div>`;
        return;
    }

    const base      = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
    const returnUrl = base + 'return_confirm.php?token=' + encodeURIComponent(_receipt.return_token || '');

    el.innerHTML = `
        <div class="receipt-card">
            <div class="receipt-head">
                <span class="material-symbols-outlined check-icon">check_circle</span>
                <h3>Request Approved</h3>
                <p>Request #${escHTML(String(_receipt.request_id))} &nbsp;·&nbsp; Show QR when claiming &amp; returning</p>
            </div>
            <div class="receipt-body">
                <div class="receipt-row">
                    <span class="receipt-label">Student</span>
                    <span class="receipt-value">${escHTML(_receipt.student_name)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Student ID</span>
                    <span class="receipt-value">${escHTML(_receipt.student_id)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Authorized by</span>
                    <span class="receipt-value">${escHTML(_receipt.faculty_name)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Equipment</span>
                    <span class="receipt-value">${escHTML(_receipt.equipment)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Room</span>
                    <span class="receipt-value">${escHTML(_receipt.room)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Borrow Date</span>
                    <span class="receipt-value">${escHTML(_receipt.borrow_date)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Return by</span>
                    <span class="receipt-value">${escHTML(_receipt.return_date)}</span>
                </div>
                <div class="receipt-qr-section">
                    <p>
                        <span class="material-symbols-outlined"
                            style="font-size:14px;vertical-align:middle;">qr_code_2</span>
                        Show this QR to the admin to <strong>claim</strong> your item,
                        and again when <strong>returning</strong> it.
                    </p>
                    <div id="receiptQrTarget"
                        style="display:inline-block;padding:12px;background:#fff;
                               border-radius:14px;border:2px solid var(--color-primary);">
                    </div>
                </div>
            </div>
        </div>`;

    _renderQr(returnUrl);
}

function _renderQr(url) {
    function doRender() {
        const target = document.getElementById('receiptQrTarget');
        if (!target) return;
        new QRCode(target, {
            text: url, width: 160, height: 160,
            colorDark: '#a32020', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H,
        });
    }
    if (window._sdQrLoaded) { doRender(); }
    else {
        const s   = document.createElement('script');
        s.src     = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        s.onload  = () => { window._sdQrLoaded = true; doRender(); };
        document.head.appendChild(s);
    }
}

/* ================================================================
   HELPERS
================================================================ */
function escHTML(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escAttr(str) {
    return String(str ?? '').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* ================================================================
   INIT: route if code-used-but-has-receipt
================================================================ */
if (_codeUsed) {
    switchPanel('panel-request');
}

})();
</script>

</body>
</html>