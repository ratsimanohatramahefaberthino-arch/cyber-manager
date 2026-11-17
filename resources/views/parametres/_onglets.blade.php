{{-- Styles partagés par les pages Installation, Versions et Parc.
     La navigation entre onglets est maintenant dans components/parametres/coque.blade.php --}}
<style>
    .cm-t-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px; box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 20px; }
    .cm-t-card h2 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .cm-t-aide { font-size: 12px; color: #64748b; margin-bottom: 14px; }
    .cm-t-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 10px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
    .cm-t-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
    .cm-t-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .cm-t-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
    .cm-t-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .cm-t-scroll { overflow-x: auto; }
    .cm-t-btn { border: 0; border-radius: 8px; padding: 7px 13px; font-size: 12px; font-weight: 700; color: #fff; background: #4f46e5; cursor: pointer; text-decoration: none; display: inline-block; }
    .cm-t-btn:hover { background: #4338ca; }
    .cm-t-btn.vert { background: #059669; } .cm-t-btn.vert:hover { background: #047857; }
    .cm-t-btn.gris { background: #fff; color: #334155; border: 1px solid #cbd5e1; } .cm-t-btn.gris:hover { background: #f8fafc; }
    .cm-t-btn.rouge { background: #fff; color: #dc2626; border: 1px solid #fecaca; } .cm-t-btn.rouge:hover { background: #fef2f2; }
    .cm-t-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .cm-t-badge.gris { background: #f1f5f9; color: #475569; }
    .cm-t-badge.bleu { background: #e0e7ff; color: #3730a3; }
    .cm-t-badge.vert { background: #d1fae5; color: #047857; }
    .cm-t-badge.orange { background: #ffedd5; color: #c2410c; }
    .cm-t-badge.rouge { background: #fee2e2; color: #b91c1c; }
    .cm-t-champ label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 5px; }
    .cm-t-champ input[type=text], .cm-t-champ input[type=file], .cm-t-champ textarea { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px 11px; font-size: 14px; background: #fff; }
    .cm-t-ligne { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
    .cm-t-mono { font-family: monospace; font-size: 11px; color: #64748b; word-break: break-all; }
    .cm-t-inline { display: inline; }
</style>