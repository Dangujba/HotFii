<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connected · HotFii</title>

    @vite(['resources/css/app.css','resources/js/app.js'])

    <style>
        body {
            min-height: 100vh;
            overflow: hidden;
        }

        .connected-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
        }

        .connected-card {
            max-width: 520px;
            width: 100%;
            position: relative;
            z-index: 10;
        }

        .success-icon {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            background: #198754;
            color: #fff;
            font-size: 46px;
            box-shadow: 0 0 0 12px rgba(25,135,84,.12);
            animation: successPop .6s ease both;
        }

        @keyframes successPop {
            0% { transform: scale(.3); opacity: 0; }
            70% { transform: scale(1.12); }
            100% { transform: scale(1); opacity: 1; }
        }

        .confetti {
            position: fixed;
            width: 9px;
            height: 15px;
            top: -30px;
            z-index: 2;
            animation: fall linear forwards;
        }

        @keyframes fall {
            to {
                transform: translate3d(var(--drift), 110vh, 0) rotate(900deg);
                opacity: .15;
            }
        }

        .waiting-spinner {
            width: 58px;
            height: 58px;
            border: 5px solid rgba(0,0,0,.12);
            border-top-color: currentColor;
            border-radius: 50%;
            margin: auto;
            animation: spin .85s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="portal-shell">

<div class="connected-shell">

    <div class="connected-card card portal-card">
        <div class="card-body p-4 p-md-5 text-center">

            <div id="verifying" @if($connected) style="display:none" @endif>
                <div class="waiting-spinner mb-4"></div>

                <h1 class="h3">Connecting you…</h1>

                <p class="text-secondary mb-0">
                    HotFii is confirming your internet session.
                </p>
            </div>

            <div id="success" @unless($connected) style="display:none" @endunless>

                <div class="success-icon">
                    <i class="bi bi-check-lg"></i>
                </div>

                <h1 class="display-6 fw-bold mb-3">
                    You're connected!
                </h1>

                <p class="lead mb-2">
                    Enjoy your browsing.
                </p>

                <p class="text-secondary mb-4">
                    Your HotFii internet session is now active.
                </p>

                <a href="{{ $originalUrl }}"
                   class="btn btn-hotfii btn-lg w-100">
                    Continue Browsing
                </a>
            </div>

            <div id="failed" style="display:none">
                <div class="text-warning fs-1 mb-3">
                    <i class="bi bi-wifi"></i>
                </div>

                <h1 class="h4">Still connecting…</h1>

                <p class="text-secondary">
                    We haven't received confirmation from the router yet.
                </p>

                <button class="btn btn-outline-secondary"
                        onclick="location.reload()">
                    Check Again
                </button>
            </div>

        </div>
    </div>

</div>

<script>
(() => {
    const initiallyConnected = @json($connected);

    const verifying = document.getElementById('verifying');
    const success = document.getElementById('success');
    const failed = document.getElementById('failed');

    const celebrate = () => {
        const colors = [
            '#198754',
            '#ffc107',
            '#0d6efd',
            '#dc3545',
            '#6f42c1',
            '#20c997'
        ];

        for (let i = 0; i < 120; i++) {
            const piece = document.createElement('div');

            piece.className = 'confetti';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[
                Math.floor(Math.random() * colors.length)
            ];

            piece.style.setProperty(
                '--drift',
                ((Math.random() - .5) * 240) + 'px'
            );

            piece.style.animationDuration =
                (2.5 + Math.random() * 3) + 's';

            piece.style.animationDelay =
                (Math.random() * .8) + 's';

            document.body.appendChild(piece);

            setTimeout(() => piece.remove(), 6500);
        }
    };

    const showSuccess = () => {
        verifying.style.display = 'none';
        failed.style.display = 'none';
        success.style.display = 'block';

        celebrate();
    };

    if (initiallyConnected) {
        showSuccess();
        return;
    }

    const checkUrl = new URL(window.location.href);
    checkUrl.searchParams.set('check', '1');

    let attempts = 0;
    const maxAttempts = 20;

    const check = async () => {
        attempts++;

        try {
            const response = await fetch(checkUrl.toString(), {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (data.connected) {
                showSuccess();
                return;
            }
        } catch (error) {
            console.error('HotFii connection verification failed', error);
        }

        if (attempts < maxAttempts) {
            setTimeout(check, 1000);
            return;
        }

        verifying.style.display = 'none';
        failed.style.display = 'block';
    };

    setTimeout(check, 700);
})();
</script>

</body>
</html>
