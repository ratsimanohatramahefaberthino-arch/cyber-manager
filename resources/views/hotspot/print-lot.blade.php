<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Impression des identifiants</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; margin: 16px; color: #0f172a; }
        .barre { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .barre button { padding: 8px 16px; border: 0; border-radius: 8px; background: #0f172a; color: #fff; cursor: pointer; }
        .grille { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .ticket { border: 1px dashed #64748b; border-radius: 8px; padding: 12px; break-inside: avoid; }
        .ticket .wifi { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .ticket .champ { margin-top: 8px; font-size: 11px; color: #64748b; }
        .ticket .valeur { font-family: ui-monospace, Consolas, monospace; font-size: 18px; font-weight: 700; word-break: break-all; }
        @media print { .barre { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="barre">
        <strong>{{ $vouchers->count() }} identifiant(s)</strong>
        <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    </div>
    <div class="grille">
        @foreach($vouchers as $voucher)
            <div class="ticket">
                <div class="wifi">Wi-Fi{{ config('mikrotik.ssid_default') ? ' · ' . config('mikrotik.ssid_default') : '' }}</div>
                <div class="champ">Identifiant</div>
                <div class="valeur">{{ $voucher->username }}</div>
                <div class="champ">Mot de passe</div>
                <div class="valeur">{{ $voucher->password }}</div>
            </div>
        @endforeach
    </div>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>