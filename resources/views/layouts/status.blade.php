<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>

    <style>
        :root {
            --np-blue: #0560aa;
            --np-blue-dark: #044e8c;
            --np-green: #16803c;
            --np-green-dark: #146c34;
            --ink: #16324f;
            --muted: #5c6b7a;
            --line: #e6edf3;
            --page: #eef4f8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: "Be Vietnam Pro", system-ui, sans-serif;
        }

        .outer-wrapper {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 16px 12px 28px;
        }

        .inner-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .brand-mark {
            display: flex;
            justify-content: center;
            margin-bottom: 12px;
        }

        .logo-starita {
            width: 84px;
            height: 84px;
            object-fit: contain;
        }

        .powered {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px 10px;
            margin-top: 4px;
            padding: 6px 0 4px;
        }

        .powered-by {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .powered img {
            display: block;
            object-fit: contain;
        }

        .logo-novulutions {
            height: 44px;
            width: auto;
        }

        .logo-novupay {
            height: 20px;
            width: auto;
            max-width: 34vw;
        }

        .logo-novustream {
            height: 34px;
            width: auto;
        }

        .wrapper {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(16, 42, 67, 0.06);
            margin-bottom: 12px;
            padding: 18px 16px;
        }

        .top {
            text-align: center;
        }

        .icon {
            background-color: var(--np-blue);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 0 0 auto;
        }

        .icon.status-mark {
            width: 64px;
            height: 64px;
            margin: 0 auto 12px;
        }

        .icon.success { background-color: var(--np-green); }
        .icon.warning { background-color: #d97706; }
        .icon.pending { background-color: #d97706; }
        .icon.error { background-color: #dc3545; }

        .header h5 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
        }

        .header p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.45;
        }

        .header h3 {
            margin: 14px 0 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .mid h6 {
            margin: 0 0 12px;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .details .items {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin: 0;
            padding: 8px 0;
            font-size: 0.92rem;
        }

        .details .items > div:first-child {
            color: var(--muted);
            flex: 0 1 auto;
        }

        .details .items > div:last-child {
            margin-left: auto;
            max-width: 62%;
            text-align: right;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e8f6ee;
            color: var(--np-green);
            font-size: 0.85rem;
        }

        .status-pill.pending {
            background: #fff4e5;
            color: #b45309;
        }

        .status-pill.error {
            background: #fdecec;
            color: #b42318;
        }

        hr {
            border: 0;
            border-top: 1px solid var(--line);
            margin: 14px 0;
            opacity: 1;
        }

        .payment-id {
            text-align: center;
            margin-top: 4px;
        }

        .payment-id .label {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .payment-id .value {
            margin-top: 2px;
            font-weight: 700;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .help-card {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            padding: 14px 14px;
        }

        .help-card:hover,
        .help-card:focus-visible {
            color: inherit;
            outline: 2px solid var(--np-blue);
            outline-offset: 2px;
        }

        .help-copy {
            flex: 1 1 auto;
            min-width: 0;
        }

        .help-copy strong,
        .help-copy span {
            display: block;
            color: var(--muted);
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .help-copy span a {
            color: var(--np-blue);
        }

        .bottom {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .bottom button,
        .bottom a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 48px;
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            line-height: 1.2;
        }

        .btn-receipt {
            background: var(--np-green);
            color: #fff;
        }

        .btn-receipt:hover { background: var(--np-green-dark); color: #fff; }

        .btn-back {
            background: var(--np-blue);
            color: #fff;
        }

        .btn-back:hover { background: var(--np-blue-dark); color: #fff; }

        @media (min-width: 420px) {
            .outer-wrapper { padding: 28px 16px 40px; }
            .logo-starita { width: 96px; height: 96px; }
            .logo-novulutions { height: 54px; }
            .logo-novupay { height: 26px; }
            .logo-novustream { height: 42px; }
            .wrapper { padding: 22px 20px; }
        }

        @media print {
            body { background: #fff; }
            .outer-wrapper { padding: 0; min-height: auto; }
            .help-card,
            .actions,
            .no-print { display: none !important; }
            .wrapper { box-shadow: none; border: 1px solid #d7e0e8; }
        }
    </style>
</head>
<body>
    @yield('base')
</body>
</html>
