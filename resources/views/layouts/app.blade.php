<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Laravel AI PR Review')</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            :root {
                color: #111827;
                background: #F8FAF9;
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
                letter-spacing: 0;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background: #F8FAF9;
                color: #111827;
                font-size: 16px;
                line-height: 1.5;
            }

            a {
                color: #0F766E;
                text-decoration: underline;
                text-underline-offset: 3px;
            }

            a:focus-visible,
            button:focus-visible,
            input:focus-visible {
                outline: 3px solid rgba(15, 118, 110, .3);
                outline-offset: 2px;
            }

            .app-header {
                border-bottom: 1px solid #D7DEE2;
                background: #FFFFFF;
            }

            .shell {
                width: min(1120px, 100%);
                margin: 0 auto;
                padding: 0 16px;
            }

            .app-header .shell {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                min-height: 64px;
            }

            .product-label,
            .section-label,
            label,
            .meta-label {
                font-size: 14px;
                font-weight: 600;
                line-height: 1.4;
            }

            .section-label,
            .helper,
            .muted {
                color: #4B5563;
            }

            main.shell {
                padding-top: 32px;
                padding-bottom: 64px;
            }

            h1 {
                margin: 0 0 32px;
                font-size: 28px;
                font-weight: 600;
                line-height: 1.2;
            }

            h2 {
                margin: 0 0 16px;
                font-size: 20px;
                font-weight: 600;
                line-height: 1.3;
            }

            .section {
                border: 1px solid #D7DEE2;
                border-radius: 8px;
                background: #FFFFFF;
                padding: 24px;
            }

            .section + .section {
                margin-top: 32px;
            }

            .form-row {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
                align-items: end;
            }

            .field {
                display: grid;
                gap: 8px;
            }

            input[type="text"] {
                width: 100%;
                min-height: 48px;
                border: 1px solid #D7DEE2;
                border-radius: 8px;
                padding: 0 16px;
                color: #111827;
                background: #FFFFFF;
            }

            button {
                min-height: 48px;
                border: 0;
                border-radius: 8px;
                padding: 0 24px;
                background: #0F766E;
                color: #FFFFFF;
                font-weight: 600;
                cursor: pointer;
            }

            .helper {
                margin: 0;
                font-size: 14px;
            }

            .error-block,
            .success-block {
                margin-top: 16px;
                border-radius: 8px;
                padding: 16px;
            }

            .error-block {
                border: 1px solid rgba(180, 35, 24, .35);
                color: #B42318;
                background: #fffafa;
            }

            .success-block {
                border: 1px solid rgba(21, 128, 61, .35);
                color: #15803D;
                background: #fbfffc;
            }

            .error-block strong,
            .success-block strong {
                display: block;
                margin-bottom: 4px;
                font-size: 14px;
                font-weight: 600;
            }

            .empty-state {
                padding: 48px 0;
                color: #4B5563;
            }

            .empty-state strong {
                display: block;
                margin-bottom: 8px;
                color: #111827;
                font-size: 20px;
                font-weight: 600;
                line-height: 1.3;
            }

            .detail-header {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 24px;
            }

            .detail-header h1 {
                margin: 0;
            }

            .metadata {
                display: grid;
                gap: 16px;
            }

            .metadata-row {
                display: grid;
                gap: 4px;
            }

            @media (min-width: 768px) {
                .shell {
                    padding-left: 24px;
                    padding-right: 24px;
                }

                .form-row {
                    grid-template-columns: minmax(0, 1fr) auto;
                }
            }

            @media (min-width: 1024px) {
                .shell {
                    padding-left: 32px;
                    padding-right: 32px;
                }
            }
        </style>
    </head>
    <body>
        <header class="app-header">
            <div class="shell">
                <div class="product-label">Laravel AI PR Review</div>
                <div class="section-label">Review Runs</div>
            </div>
        </header>

        <main class="shell">
            @yield('content')
        </main>
    </body>
</html>
