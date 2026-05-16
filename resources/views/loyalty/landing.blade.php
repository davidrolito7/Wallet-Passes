<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $card->loyaltyProgram->business->name }} — Tarjeta de Lealtad</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: {{ $card->loyaltyProgram->business->primary_color }};
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 40px 32px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }

        .business-name {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #888;
            margin-bottom: 8px;
        }

        .program-name {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .reward {
            font-size: 15px;
            color: #555;
            margin-bottom: 32px;
        }

        .stamps-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 32px;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 16px;
        }

        .stamp {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto;
        }

        .stamp.filled {
            background: {{ $card->loyaltyProgram->business->primary_color }};
        }

        .stamp.empty {
            background: #e0e0e0;
            opacity: .5;
        }

        .progress-text {
            font-size: 14px;
            color: #888;
            margin-bottom: 8px;
        }

        .holder-name {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 24px;
        }

        .wallet-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: block;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: opacity .2s;
        }

        .btn:hover { opacity: .85; }

        .btn-google {
            background: #1a73e8;
            color: white;
        }

        .btn-apple {
            background: #000;
            color: white;
        }

        .btn-apple.disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .apple-note {
            font-size: 12px;
            color: #aaa;
            margin-top: 6px;
        }

        .completed-badge {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <p class="business-name">{{ $card->loyaltyProgram->business->name }}</p>
        <h1 class="program-name">{{ $card->loyaltyProgram->name }}</h1>
        <p class="reward">🎁 {{ $card->loyaltyProgram->reward_title }}</p>

        @if($card->is_completed)
            <div class="completed-badge">✅ ¡Tarjeta completada! Lista para canjear.</div>
        @endif

        <div class="stamps-grid">
            @php
                $total = $card->loyaltyProgram->total_stamps;
                $icon  = $card->loyaltyProgram->stampIconLabel();
            @endphp
            @for($i = 1; $i <= $total; $i++)
                <div class="stamp {{ $i <= $card->stamps_collected ? 'filled' : 'empty' }}">
                    @if($i <= $card->stamps_collected)
                        {{ $icon }}
                    @else
                        &nbsp;
                    @endif
                </div>
            @endfor
        </div>

        <p class="progress-text">{{ $card->progressText() }} sellos</p>
        <p class="holder-name">{{ $card->holder_name }}</p>

        <div class="wallet-buttons">
            @if($card->google_pass_id)
                <a href="{{ route('loyalty.google', $card) }}" class="btn btn-google">
                    Agregar a Google Wallet
                </a>
            @endif

            @if($card->apple_pass_id)
                <a href="{{ route('loyalty.apple', $card) }}" class="btn btn-apple">
                    Agregar a Apple Wallet
                </a>
            @else
                <span class="btn btn-apple disabled">Apple Wallet — Próximamente</span>
                <p class="apple-note">La integración con Apple Wallet estará disponible pronto.</p>
            @endif
        </div>
    </div>
</body>
</html>
