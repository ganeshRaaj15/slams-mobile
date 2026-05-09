<?php
/**
 * @var array<string, mixed> $asset
 * @var int $labId
 * @var int $serviceId
 * @var string $appUrl
 * @var string $webUrl
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening SLAMS Mobile</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --primary: #0f766e;
            --primary-soft: #ccfbf1;
            --border: rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(circle at top, rgba(15, 118, 110, 0.14), transparent 32%),
                linear-gradient(180deg, #f8fafc 0%, #ecfeff 100%);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            width: min(100%, 460px);
            padding: 28px;
            border-radius: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 10px;
            font-size: 28px;
            line-height: 1.15;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .asset {
            margin-top: 20px;
            padding: 16px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .asset-label {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
        }

        .asset-name {
            margin-top: 8px;
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .actions {
            display: grid;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 14px 32px rgba(15, 118, 110, 0.22);
        }

        .btn-secondary {
            background: #ffffff;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .note {
            margin-top: 14px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <noscript>
        <meta http-equiv="refresh" content="0;url=<?= esc($webUrl, 'attr') ?>">
    </noscript>
    <main class="card">
        <div class="eyebrow">SLAMS Mobile</div>
        <h1>Opening the mobile booking flow</h1>
        <p>If SLAMS Mobile is installed, this QR scan will switch into the app and open the booking composer for the selected equipment. If not, you will continue on the website automatically.</p>

        <section class="asset" aria-label="Selected equipment">
            <div class="asset-label">Selected Equipment</div>
            <div class="asset-name"><?= esc((string) ($asset['name'] ?? 'Equipment')) ?></div>
        </section>

        <div class="actions">
            <a class="btn btn-primary" href="<?= esc($appUrl, 'attr') ?>">Open in SLAMS Mobile</a>
            <a class="btn btn-secondary" href="<?= esc($webUrl, 'attr') ?>">Continue on Website</a>
        </div>
    </main>

    <script>
    (function () {
        const appUrl = <?= json_encode($appUrl, JSON_UNESCAPED_SLASHES) ?>;
        const webUrl = <?= json_encode($webUrl, JSON_UNESCAPED_SLASHES) ?>;
        let appOpened = false;

        function cancelFallback() {
            appOpened = true;
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                cancelFallback();
            }
        });

        window.addEventListener('pagehide', cancelFallback);

        window.setTimeout(function () {
            if (!appOpened) {
                window.location.replace(webUrl);
            }
        }, 1600);

        window.setTimeout(function () {
            window.location.href = appUrl;
        }, 120);
    })();
    </script>
</body>
</html>
