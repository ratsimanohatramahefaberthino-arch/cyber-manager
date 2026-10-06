<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $titre ?? 'Voucher' }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; color: #000; font-size: 10px; }

        .entete { display: flex; justify-content: space-between; align-items: baseline;
                  font-size: 10px; padding-bottom: 4px; border-bottom: 1px solid #000; margin-bottom: 6px; }
        .entete .nom { font-weight: 700; }
        .entete .date { font-size: 9px; }

        .grille { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 8px; }
        .ticket { border: 1px solid #000; padding: 4px 5px 3px; break-inside: avoid; page-break-inside: avoid; }
        .ticket .haut { display: flex; justify-content: space-between; font-weight: 700;
                        font-size: 10px; margin-bottom: 3px; }
        .ticket .haut .idx { color: #000; }
        .ticket .tab { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #000; }
        .ticket .tab .th { background: #f1f1f1; font-weight: 700; text-align: center; font-size: 8px;
                           padding: 1px 2px; border-bottom: 1px solid #000; }
        .ticket .tab .th + .th { border-left: 1px solid #000; }
        .ticket .tab .td { font-family: Consolas, "Courier New", monospace; font-weight: 700;
                           font-size: 11px; text-align: center; padding: 2px 3px; letter-spacing: .3px; }
        .ticket .tab .td + .td { border-left: 1px solid #000; }
        .ticket .prix { text-align: left; font-size: 9px; margin-top: 2px; }

        .barre { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .barre button { padding: 6px 14px; border: 0; border-radius: 6px; background: #0f172a; color: #fff;
                        cursor: pointer; font-size: 12px; }
        .vide { text-align: center; padding: 40px; color: #666; font-size: 13px; }
        @media print { .barre { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="barre">
        <strong>{{ $vouchers->count() }} identifiant(s) — {{ $titre ?? 'Impression' }}</strong>
        <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    </div>

    @if($vouchers->isEmpty())
        <div class="vide">Aucun identifiant à imprimer.</div>
    @else
        <div class="entete">
            <span class="nom">{{ $nomFichier ?? 'Voucher' }}</span>
            <span class="date">{{ now()->format('d/m/Y H:i') }}</span>
        </div>

        <div class="grille">
            @foreach($vouchers as $i => $voucher)
                @php
                    $etiquette = $voucher->serveur && $voucher->serveur !== 'all'
                        ? $voucher->serveur
                        : (config('mikrotik.ssid_default') ?: 'HotSpot');
                @endphp
                <div class="ticket">
                    <div class="haut">
                        <span>{{ $etiquette }}</span>
                        <span class="idx">[{{ $i + 1 }}]</span>
                    </div>
                    <div class="tab">
                        <div class="th">Username</div>
                        <div class="th">Password</div>
                        <div class="td">{{ $voucher->username }}</div>
                        <div class="td">{{ $voucher->password }}</div>
                    </div>
                    <div class="prix">Ar {{ number_format((int) $voucher->montant, 0, ',', ' ') }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>