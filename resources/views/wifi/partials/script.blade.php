@verbatim
<script>
(function () {
    'use strict';

    // ───────────────────────── Utilitaires ─────────────────────────
    const entier = (v) => parseInt(v, 10) || 0;
    const borne  = (v, min, max) => Math.max(min, Math.min(max, v));
    const pad    = (n) => String(n).padStart(2, '0');
    const toast  = (m) => window.dispatchEvent(new CustomEvent('toast', { detail: m }));

    const COLONNES_DEFAUT = { c0: 40, c1: 75, c2: 150, c3: 90, c4: 75, c5: 100, c6: 100, c7: 110, c8: 120, c9: 110 };

    function chargerColonnes() {
        try {
            const s = JSON.parse(localStorage.getItem('cm_wifi_cols'));
            return s && typeof s === 'object' ? { ...COLONNES_DEFAUT, ...s } : { ...COLONNES_DEFAUT };
        } catch (e) {
            return { ...COLONNES_DEFAUT };
        }
    }

    // Met à jour `cible` en place avec `src` : seules les valeurs qui changent déclenchent
    // un rendu Alpine (pas de reconstruction du tableau à chaque poll).
    function fusionProfonde(cible, src) {
        for (const k of Object.keys(src)) {
            const v = src[k];
            const c = cible[k];
            if (v && typeof v === 'object' && !Array.isArray(v) && c && typeof c === 'object' && !Array.isArray(c)) {
                fusionProfonde(c, v);
            } else if (c !== v) {
                cible[k] = v;
            }
        }
    }

    window.wifiPage = function (cfg) {

        // ── État NON réactif (hors proxy Alpine : aucun rendu déclenché par ces écritures) ──
        const echantillons   = new Map();   // id ligne → derniers relevés de volume
        const debutSonnerie  = new Map();   // "id|fin" → timestamp de début de sonnerie
        const notifiees      = new Set();   // "id|fin" déjà notifiées
        let ctxAudio = null, oscillateurs = [];
        let timerTick = null, timerPoll = null, timerTitre = null, timerTest = null;
        let pollEnCours = false, pollCtl = null, dernierBip = 0;

        return {
            cfg,
            lignes: cfg.lignesInitiales ?? [],
            stats: cfg.statsInitiales ?? { connectes: 0, deconnectes: 0, termines: 0, volume_total: 0 },
            vouchersDisponibles: (cfg.vouchersDisponibles ?? []).map(v => ({ ...v, masque: true, transition: false })),
            vouchersWarning: cfg.vouchersWarning ?? null,
            alarme: { ...cfg.alarme },
            sync: { ok: true, at: null, erreur: null },
            erreurReseau: false,
            derniereSync: '',
            notifSupportee: typeof Notification !== 'undefined',
            notifAutorisee: (typeof Notification !== 'undefined') && Notification.permission === 'granted',

            // Horloge alignée sur celle du serveur (évite les écarts si le PC de caisse est mal réglé)
            decalage: cfg.serveurMs ? cfg.serveurMs - Date.now() : 0,
            now: Date.now() + (cfg.serveurMs ? cfg.serveurMs - Date.now() : 0),

            filtreEtat: '', recherche: '', cleTri: 'date_debut', sensTri: 'desc', selected: [],
            debitTotal: 0,
            seq: 0,                       // incrémenté à chaque action : invalide les réponses de poll en vol
            actionEnCours: false,

            // Alarme
            ongletAlarmeActif: null,
            sonAlarme: false,
            testEnCours: false,
            testId: null,
            prolongationsRapides: [
                { label: '+15 min',  type: 'duree',   valeur: 15 },
                { label: '+30 min',  type: 'duree',   valeur: 30 },
                { label: '+500 Ar',  type: 'montant', valeur: 500 },
                { label: '+1000 Ar', type: 'montant', valeur: 1000 },
            ],

            confirmation: { ouvert: false, titre: '', message: '', bouton: '', danger: false, enCours: false, action: null },

            alarmeModal: {
                ouvert: false, ligne: null, modifier: false,
                type: 'duree', h: 0, m: 30, montant: 1000, depart: 'debut',
                raccourcis: [
                    { label: '15 min',  type: 'duree',   h: 0, m: 15 },
                    { label: '30 min',  type: 'duree',   h: 0, m: 30 },
                    { label: '1 h',     type: 'duree',   h: 1, m: 0 },
                    { label: '500 Ar',  type: 'montant', montant: 500 },
                    { label: '1000 Ar', type: 'montant', montant: 1000 },
                ],
                apercu: '', erreur: '', enCours: false,
            },

            prolongerModal: {
                ouvert: false, ligne: null, type: 'duree', depart: 'apres', h: 0, m: 15, montant: 500,
                raccourcis: [
                    { label: '+15 min',  type: 'duree',   h: 0, m: 15 },
                    { label: '+30 min',  type: 'duree',   h: 0, m: 30 },
                    { label: '+1 h',     type: 'duree',   h: 1, m: 0 },
                    { label: '+500 Ar',  type: 'montant', montant: 500 },
                    { label: '+1000 Ar', type: 'montant', montant: 1000 },
                ],
                erreur: '', enCours: false,
            },

            reglages: {
                ouvert: false, tab: 'general', enCours: false,
                form: {
                    enabled: true, volume: 70, modeDuree: 'auto', dureeMin: 0, dureeSec: 30, tailleMax: 10,
                    sonId: 'bip-classique', sonFichier: null, sonNom: 'Bip classique', sonUrl: null,
                },
                sonsUploades: [],
                uploadEnCours: false, uploadErreur: '', uploadOk: '',
            },
            supprSonModal: { ouvert: false, nom: '', son: null },
            resetModal: { ouvert: false },
            dragActif: false,

            colWidths: chargerColonnes(),
            resizing: null,

            // ───────────────────── Cycle de vie ─────────────────────
            init() {
                this.calculerDebits(this.lignes);
                this.chargerReglages();

                timerTick = setInterval(() => this.tick(), 1000);
                this.planifierPoll();

                this._surClic = () => this.debloquerAudio();
                document.addEventListener('click', this._surClic, { once: true });

                this._surVisibilite = () => { if (!document.hidden) this.rafraichir(); };
                document.addEventListener('visibilitychange', this._surVisibilite);
            },

            // Appelé automatiquement par Alpine quand le composant est retiré du DOM.
            destroy() {
                clearInterval(timerTick);
                clearTimeout(timerPoll);
                clearTimeout(timerTest);
                if (pollCtl) pollCtl.abort();
                this.arreterSon();
                this.clignoterTitre(false);
                document.removeEventListener('visibilitychange', this._surVisibilite);
                document.removeEventListener('click', this._surClic);
            },

            tick() {
                this.now = Date.now() + this.decalage;
                this.verifierAlarmes();
            },

            // ───────────────────── Polling (jamais de requêtes empilées) ─────────────────────
            planifierPoll() {
                clearTimeout(timerPoll);
                timerPoll = setTimeout(async () => {
                    await this.rafraichir();
                    this.planifierPoll();
                }, document.hidden ? 5000 : 2000);
            },

            async rafraichir(manuel = false) {
                if (pollEnCours) return;
                pollEnCours = true;

                const seq = this.seq;
                pollCtl = new AbortController();
                const garde = setTimeout(() => pollCtl.abort(), 8000);

                try {
                    const r = await fetch(cfg.urlEtat, { headers: { Accept: 'application/json' }, signal: pollCtl.signal });
                    if (!r.ok) { this.erreurReseau = true; return; }
                    const d = await r.json();

                    this.erreurReseau = false;
                    if (seq !== this.seq) return;     // une action a eu lieu entre-temps : réponse périmée, on l'ignore
                    this.appliquer(d);
                    if (manuel) toast('Tableau actualisé.');
                } catch (e) {
                    this.erreurReseau = true;
                } finally {
                    clearTimeout(garde);
                    pollEnCours = false;
                }
            },

            // Applique une réponse serveur (poll OU action) à l'écran.
            appliquer(d) {
                if (d.serveur_ms) this.decalage = d.serveur_ms - Date.now();
                this.now = Date.now() + this.decalage;
                if (d.lignes) this.fusionnerLignes(d.lignes);
                if (d.stats) Object.assign(this.stats, d.stats);
                if (d.vouchers_disponibles) this.mettreAJourVouchers(d.vouchers_disponibles);
                if ('vouchers_warning' in d) this.vouchersWarning = d.vouchers_warning;
                if (d.sync) this.sync = d.sync;
                this.derniereSync = new Date().toLocaleTimeString('fr-FR');
            },

            fusionnerLignes(nouvelles) {
                const parId = new Map(this.lignes.map(l => [l.id, l]));

                const resultat = nouvelles.map(n => {
                    const old = parId.get(n.id);
                    if (!old) return n;
                    // On ne piétine jamais une remarque en cours de frappe.
                    const src = old._focusRemarque ? { ...n, remarque: old.remarque } : n;
                    fusionProfonde(old, src);
                    return old;
                });

                const identique = resultat.length === this.lignes.length && resultat.every((l, i) => l === this.lignes[i]);
                if (!identique) this.lignes = resultat;

                this.calculerDebits(this.lignes);

                const ids = new Set(this.lignes.map(l => String(l.id)));
                if (this.selected.some(id => !ids.has(id))) this.selected = this.selected.filter(id => ids.has(id));
            },

            // Débit calculé UNE fois par poll (et non dans le rendu) sur une fenêtre glissante.
            calculerDebits(lignes) {
                const t = Date.now();
                const presents = new Set();
                let total = 0;

                for (const l of lignes) {
                    presents.add(l.id);
                    let e = echantillons.get(l.id);
                    if (!e) { e = []; echantillons.set(l.id, e); }

                    const dernier = e[e.length - 1];
                    if (dernier && (l.volume_entree < dernier.ein || l.volume_sortie < dernier.eout)) e.length = 0;
                    e.push({ ts: t, ein: l.volume_entree, eout: l.volume_sortie });
                    if (e.length > 6) e.shift();

                    let din = 0, dout = 0;
                    if (l.etat_appareil === 'connecte' && e.length >= 2) {
                        const a = e[0], b = e[e.length - 1];
                        const dt = (b.ts - a.ts) / 1000;
                        if (dt >= 1) {
                            din  = Math.max(0, (b.ein - a.ein) / dt);
                            dout = Math.max(0, (b.eout - a.eout) / dt);
                        }
                    }
                    l.debit_entree = din;
                    l.debit_sortie = dout;
                    total += din + dout;
                }

                for (const id of [...echantillons.keys()]) if (!presents.has(id)) echantillons.delete(id);
                this.debitTotal = total;
            },

            mettreAJourVouchers(nouveaux) {
                const ids = new Set(nouveaux.map(v => v.id));

                this.vouchersDisponibles.forEach(v => {
                    if (!ids.has(v.id) && !v.transition) {
                        v.transition = true;
                        setTimeout(() => {
                            this.vouchersDisponibles = this.vouchersDisponibles.filter(x => x.id !== v.id);
                        }, 1500);
                    }
                });

                nouveaux.forEach(nv => {
                    if (!this.vouchersDisponibles.some(x => x.id === nv.id)) {
                        this.vouchersDisponibles.push({ ...nv, masque: true, transition: false });
                    }
                });
            },

            // Appel API commun : JSON, CSRF, erreurs normalisées.
            async api(url, { method = 'POST', body = null } = {}) {
                const r = await window.cmFetch(url, {
                    method,
                    headers: body ? { 'Content-Type': 'application/json' } : {},
                    body: body ? JSON.stringify(body) : undefined,
                });
                let d = {};
                try { d = await r.json(); } catch (e) {}
                return { ok: r.ok && d.ok !== false, status: r.status, d };
            },

            // ═════════════════════════════════════════════════════════════
            //  MACHINE À ÉTATS DE L'ALARME
            //
            //   aucune ─(poser)→ decompte ─(temps écoulé)→ SONNE
            //   SONNE ─(« Arrêter l'alarme »)→ silencieuse   (son coupé, fenêtre fermée,
            //                                                 la barre reste, on peut prolonger)
            //   SONNE / silencieuse ─(prolonger)→ decompte
            //   decompte ─(appareil hors ligne)→ pause  (le chrono est gelé)
            //
            //  L'état est CALCULÉ à partir des données de la ligne : la fenêtre n'est qu'un
            //  affichage de cet état, elle ne peut donc plus se désynchroniser.
            // ═════════════════════════════════════════════════════════════

            // Secondes avant la sonnerie (négatif = dépassement), null si pas d'alarme.
            restantAlarme(l) {
                const a = l.alarme;
                if (!a.active) return null;
                if (a.fin) return Math.floor((new Date(a.fin).getTime() - this.now) / 1000);
                if (a.secondes_restantes != null) return a.secondes_restantes;
                return 0;
            },

            etatAlarme(l) {
                const a = l.alarme;
                if (!a.active || l.etat_appareil === 'termine') return 'aucune';
                if (a.acquittee) return 'silencieuse';
                if (l.etat_appareil !== 'connecte') return 'pause';
                return this.restantAlarme(l) <= 0 ? 'sonne' : 'decompte';
            },

            get fenetresAlarme() {
                return this.lignes.filter(l => this.etatAlarme(l) === 'sonne');
            },

            get ongletAlarme() {
                const f = this.fenetresAlarme;
                return f.find(l => l.id === this.ongletAlarmeActif) || f[0] || null;
            },

            chronoAlarme(l) {
                return this.formatDureeSec(Math.max(0, this.restantAlarme(l) ?? 0));
            },

            depassementSecondes(l) {
                return Math.max(0, -(this.restantAlarme(l) ?? 0));
            },

            depassementMontant(l) {
                if (!l.tarif) return 0;
                return Math.floor((this.depassementSecondes(l) / 60) * l.tarif.montant_par_minute);
            },

            // Progression = 1 - restant / durée totale du cycle en cours.
            progressionAlarme(l) {
                const total = l.alarme.total_secondes || ((l.alarme.minutes || 0) * 60);
                if (!total) return 0;
                const restant = this.restantAlarme(l) ?? total;
                return borne((1 - restant / total) * 100, 0, 100);
            },

            couleurBarre(l) {
                const r = this.restantAlarme(l) ?? 0;
                if (r <= 0) return 'bg-rose-500';
                if (r <= 60) return 'bg-orange-500';
                return 'bg-amber-500';
            },

            // Appelé chaque seconde : notifications, titre, son.
            verifierAlarmes() {
                const sonnantes = this.fenetresAlarme;
                const cles = new Set();

                for (const l of sonnantes) {
                    const cle = l.id + '|' + l.alarme.fin;
                    cles.add(cle);
                    if (!debutSonnerie.has(cle)) debutSonnerie.set(cle, Date.now());
                    if (!notifiees.has(cle)) { notifiees.add(cle); this.notifier(l); }
                }
                for (const c of [...debutSonnerie.keys()]) if (!cles.has(c)) debutSonnerie.delete(c);

                this.clignoterTitre(sonnantes.length > 0);
                this.gererSon(sonnantes);
            },

            notifier(l) {
                if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
                try {
                    new Notification('Cyber Manager — Alarme', {
                        body: l.appareil + ' a atteint son forfait.',
                        icon: '/favicon.ico',
                        tag: 'wifi-alarm-' + l.id,
                        requireInteraction: true,
                    });
                } catch (e) {}
            },

            clignoterTitre(on) {
                if (on) {
                    if (timerTitre) return;
                    let alt = false;
                    timerTitre = setInterval(() => {
                        document.title = alt ? 'Wi-Fi — Cyber Manager' : 'ALARME — Temps écoulé';
                        alt = !alt;
                    }, 800);
                } else if (timerTitre) {
                    clearInterval(timerTitre);
                    timerTitre = null;
                    document.title = 'Wi-Fi — Cyber Manager';
                }
            },

            // « Arrêter l'alarme » : mise à jour immédiate (le son et la fenêtre s'arrêtent à l'instant),
            // puis confirmation serveur ; en cas d'échec on annule et on prévient.
            async acquitter(l) {
                if (!l) return;
                this.seq++;
                const avant = l.alarme.acquittee;
                l.alarme.acquittee = true;
                this.verifierAlarmes();

                try {
                    const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/alarme/acquitter`);
                    if (!ok) throw new Error(d.message || 'Erreur.');
                    this.appliquer(d);
                } catch (e) {
                    l.alarme.acquittee = avant;
                    toast(e.message && e.message !== 'Failed to fetch' ? e.message : 'Erreur réseau : alarme non arrêtée.');
                }
            },

            // Prolongation en un clic depuis la fenêtre d'alerte.
            async prolongerRapide(l, p) {
                if (!l || this.actionEnCours) return;
                this.actionEnCours = true;
                this.seq++;
                try {
                    const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/alarme/prolonger`, {
                        body: { type: p.type, valeur: p.valeur, depart: 'maintenant' },
                    });
                    if (!ok) { toast(d.message || 'Erreur.'); return; }
                    toast(d.message);
                    this.appliquer(d);          // l'alarme repasse en « décompte » : la fenêtre disparaît d'elle-même
                } catch (e) {
                    toast('Erreur réseau.');
                } finally {
                    this.actionEnCours = false;
                }
            },

            // ───────────────────── Son ─────────────────────
            gererSon(sonnantes) {
                const dureeMax = entier(this.alarme.duree_secondes);
                const maintenant = Date.now();
                const actives = sonnantes.filter(l => {
                    if (dureeMax <= 0) return true;
                    const debut = debutSonnerie.get(l.id + '|' + l.alarme.fin) ?? maintenant;
                    return (maintenant - debut) < dureeMax * 1000;
                });

                if (!this.alarme.enabled || actives.length === 0) {
                    if (this.sonAlarme) this.arreterSon();
                    return;
                }

                if (this.testEnCours) this.arreterSon();     // l'alarme réelle est prioritaire sur un test
                const vol = this.alarme.volume ?? 70;

                if (this.alarme.son_url) {
                    const a = this.$refs.audio;
                    if (a && (!this.sonAlarme || a.paused)) {
                        this.sonAlarme = true;
                        this.lireFichier(this.alarme.son_url, vol, true);
                    }
                } else if (!this.sonAlarme || (maintenant - dernierBip) > 2500) {
                    this.sonAlarme = true;
                    dernierBip = maintenant;
                    this.bip(this.alarme.son_builtin || 'bip-classique', vol);
                }
            },

            lireFichier(url, volPct, boucle) {
                const a = this.$refs.audio;
                if (!a) return;
                try {
                    if (a.getAttribute('src') !== url) a.src = url;
                    a.loop = boucle;
                    a.volume = borne(volPct / 100, 0, 1);
                    a.currentTime = 0;
                    a.play().catch(() => {});
                } catch (e) {}
            },

            arreterSon() {
                try {
                    const a = this.$refs.audio;
                    if (a) { a.pause(); a.loop = false; a.currentTime = 0; }
                    oscillateurs.forEach(o => { try { o.stop(); } catch (e) {} });
                } catch (e) {}
                oscillateurs = [];
                clearTimeout(timerTest);
                this.testEnCours = false;
                this.testId = null;
                this.sonAlarme = false;
                dernierBip = 0;
            },

            contexte() {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return null;
                if (!ctxAudio) ctxAudio = new Ctx();
                if (ctxAudio.state === 'suspended') ctxAudio.resume().catch(() => {});
                return ctxAudio;
            },

            bip(id, volPct) {
                const ctx = this.contexte();
                if (!ctx) return;
                const gain = ctx.createGain();
                gain.gain.value = borne(volPct / 100, 0, 1) * 0.35;
                gain.connect(ctx.destination);

                const sequences = {
                    'bip-classique': [880, 880, 880],
                    'carillon': [523, 659, 784, 1047],
                    'urgent': [1200, 1200, 1200, 1200, 1200],
                };
                (sequences[id] || sequences['bip-classique']).forEach((f, i) => {
                    const osc = ctx.createOscillator();
                    osc.type = id === 'urgent' ? 'sawtooth' : 'sine';
                    osc.frequency.value = f;
                    osc.connect(gain);
                    const debut = ctx.currentTime + i * 0.28;
                    osc.start(debut);
                    osc.stop(debut + 0.2);
                    oscillateurs.push(osc);
                    osc.onended = () => { oscillateurs = oscillateurs.filter(o => o !== osc); };
                });
            },

            testerSon(idOuUrl) {
                if (this.testEnCours && this.testId === idOuUrl) { this.arreterSon(); return; }
                this.arreterSon();
                this.testEnCours = true;
                this.testId = idOuUrl;

                const vol = this.reglages.form.volume ?? 70;
                let source = idOuUrl;
                if (idOuUrl === 'actif') {
                    source = this.reglages.form.sonUrl || this.reglages.form.sonId || 'bip-classique';
                }

                if (typeof source === 'string' && source.startsWith('/')) this.lireFichier(source, vol, false);
                else this.bip(source, vol);

                timerTest = setTimeout(() => this.arreterSon(), 6000);
            },

            // Les navigateurs bloquent le son tant que l'utilisateur n'a pas interagi avec la page.
            debloquerAudio() {
                this.contexte();
                const a = this.$refs.audio;
                if (a && a.getAttribute('src')) {
                    a.muted = true;
                    a.play().then(() => { a.pause(); a.currentTime = 0; a.muted = false; }).catch(() => { a.muted = false; });
                }
            },

            demanderNotif() {
                if (typeof Notification === 'undefined') return;
                Notification.requestPermission().then(p => { this.notifAutorisee = p === 'granted'; });
            },

            // ───────────────────── Poser / modifier ─────────────────────
            ouvrirAlarme(l, modifier = false) {
                const min = l.alarme.minutes || 30;
                Object.assign(this.alarmeModal, {
                    ouvert: true, ligne: l, modifier,
                    type: modifier ? (l.alarme.type || 'duree') : 'duree',
                    h: Math.floor(min / 60), m: min % 60,
                    montant: l.alarme.montant || 1000,
                    depart: 'debut',
                    apercu: '', erreur: '', enCours: false,
                });
                this.majApercu();
            },

            appliquerRaccourci(r) {
                this.alarmeModal.type = r.type;
                if (r.type === 'duree') { this.alarmeModal.h = r.h; this.alarmeModal.m = r.m; }
                else this.alarmeModal.montant = r.montant;
                this.majApercu();
            },

            // Même règle que le serveur : minimum, puis arrondi.
            montantNet(brut, t) {
                if (t.montant_minimum > 0 && brut < t.montant_minimum) brut = t.montant_minimum;
                if (t.arrondi_actif && t.unite_arrondi > 0) {
                    const u = Math.max(1, t.unite_arrondi);
                    const s = Math.max(0, Math.min(u - 1, t.seuil_arrondi || 0));
                    const r = brut % u;
                    if (r !== 0) brut = r < s ? brut - r : brut + (u - r);
                }
                return brut;
            },

            convertirEnMinutes(l, type, h, m, montant) {
                if (type === 'duree') return entier(h) * 60 + entier(m);
                const t = l.tarif;
                if (!t || t.montant_par_minute <= 0) return 0;
                return Math.floor(this.montantNet(entier(montant), t) / t.montant_par_minute);
            },

            heureDans(secondes) {
                const d = new Date(this.now + secondes * 1000);
                return pad(d.getHours()) + ':' + pad(d.getMinutes());
            },

            majApercu() {
                const M = this.alarmeModal, l = M.ligne;
                if (!l) return;

                const min = this.convertirEnMinutes(l, M.type, M.h, M.m, M.montant);
                if (min < 1) { M.apercu = 'Indiquez une durée ou un montant valide.'; return; }

                const total = min * 60;
                const deja = this.secondesConsommees(l);
                const restant = M.depart === 'debut' ? total - deja : total;

                if (restant <= 0) {
                    M.apercu = 'Cette limite est déjà dépassée. Choisissez une valeur plus grande ou « À partir de maintenant ».';
                    return;
                }

                let txt = `Déjà consommé : ${this.formatDureeSec(deja)} (${this.formatAr(this.montantTempsReel(l))}). `;
                txt += l.etat_appareil === 'connecte'
                    ? `L'alarme sonnera à ${this.heureDans(restant)}, dans ${this.formatDureeSec(restant)}.`
                    : `L'appareil est hors ligne : le décompte (${this.formatDureeSec(restant)}) démarrera à sa reconnexion.`;
                M.apercu = txt;
            },

            async confirmerAlarme() {
                const M = this.alarmeModal, l = M.ligne;
                if (!l) return;

                const valeur = M.type === 'duree' ? entier(M.h) * 60 + entier(M.m) : entier(M.montant);
                if (valeur < 1) { M.erreur = 'Indiquez une valeur supérieure à 0.'; return; }

                M.enCours = true; M.erreur = '';
                this.seq++;
                try {
                    const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/alarme`, {
                        body: { type: M.type, valeur, depart: M.depart },
                    });
                    if (!ok) { M.erreur = d.message || 'Erreur.'; return; }
                    toast(d.message);
                    M.ouvert = false;
                    this.appliquer(d);
                } catch (e) {
                    M.erreur = 'Erreur réseau.';
                } finally {
                    M.enCours = false;
                }
            },

            async annulerAlarme() {
                const l = this.alarmeModal.ligne;
                if (!l) return;
                this.seq++;
                try {
                    const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/alarme/annuler`);
                    if (!ok) { this.alarmeModal.erreur = d.message || 'Erreur.'; return; }
                    toast('Alarme supprimée.');
                    this.alarmeModal.ouvert = false;
                    this.appliquer(d);
                } catch (e) {
                    this.alarmeModal.erreur = 'Erreur réseau.';
                }
            },

            // ───────────────────── Prolonger ─────────────────────
            ouvrirProlonger(l) {
                Object.assign(this.prolongerModal, {
                    ouvert: true, ligne: l,
                    type: 'duree', depart: 'apres', h: 0, m: 15, montant: 500,
                    erreur: '', enCours: false,
                });
            },

            appliquerRaccourciProlongation(r) {
                this.prolongerModal.type = r.type;
                if (r.type === 'duree') { this.prolongerModal.h = r.h; this.prolongerModal.m = r.m; }
                else this.prolongerModal.montant = r.montant;
            },

            apercuProlongation() {
                const M = this.prolongerModal, l = M.ligne;
                if (!l) return '';

                const min = this.convertirEnMinutes(l, M.type, M.h, M.m, M.montant);
                if (min < 1) return 'Indiquez une durée ou un montant valide.';

                const restant = this.restantAlarme(l) ?? 0;
                const cumul = M.depart === 'apres' && restant > 0;
                const nouveau = (cumul ? restant : 0) + min * 60;

                return l.etat_appareil === 'connecte'
                    ? `Nouvelle sonnerie à ${this.heureDans(nouveau)}, dans ${this.formatDureeSec(nouveau)}.`
                    : `L'appareil est hors ligne : le décompte de ${this.formatDureeSec(nouveau)} reprendra à sa reconnexion.`;
            },

            async confirmerProlongation() {
                const M = this.prolongerModal, l = M.ligne;
                if (!l) return;

                const valeur = M.type === 'duree' ? entier(M.h) * 60 + entier(M.m) : entier(M.montant);
                if (valeur < 1) { M.erreur = 'Indiquez une valeur supérieure à 0.'; return; }

                const depart = (this.restantAlarme(l) ?? 0) > 0 ? M.depart : 'maintenant';

                M.enCours = true; M.erreur = '';
                this.seq++;
                try {
                    const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/alarme/prolonger`, {
                        body: { type: M.type, valeur, depart },
                    });
                    if (!ok) { M.erreur = d.message || 'Erreur.'; return; }
                    toast(d.message);
                    M.ouvert = false;
                    this.appliquer(d);          // → état « décompte » : la barre repart, plus aucune fenêtre
                } catch (e) {
                    M.erreur = 'Erreur réseau.';
                } finally {
                    M.enCours = false;
                }
            },

            // ───────────────────── Confirmations (arrêt, reprise, suppression) ─────────────────────
            ouvrirConfirmation(opts) {
                this.confirmation = {
                    ouvert: true, enCours: false,
                    titre: '', message: '', bouton: '', danger: false, action: null,
                    ...opts,
                };
            },

            async executerConfirmation() {
                const courante = this.confirmation;
                const action = courante.action;
                if (!action) { courante.ouvert = false; return; }

                courante.enCours = true;
                try { await action(); }
                catch (e) { toast('Erreur réseau.'); }
                finally {
                    courante.enCours = false;
                    // Si l'action a ouvert une NOUVELLE confirmation (ex. suppression forcée), on la laisse ouverte.
                    if (this.confirmation === courante) courante.ouvert = false;
                }
            },

            confirmerArret(l) {
                this.ouvrirConfirmation({
                    titre: 'Arrêter la session ?',
                    message: `La durée et le montant de <strong>${this.escapeHtml(l.appareil)}</strong> seront figés immédiatement.<br><br>Le client reste connecté au Wi-Fi ; la déconnexion se fait avec l'action Supprimer.`,
                    bouton: 'Arrêter la session',
                    danger: false,
                    action: async () => {
                        this.seq++;
                        const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/arreter`);
                        toast(d.message || (ok ? 'OK' : 'Erreur.'));
                        if (ok) this.appliquer(d);
                    },
                });
            },

            confirmerContinuer(l) {
                this.ouvrirConfirmation({
                    titre: 'Continuer cette session ?',
                    message: `La durée et le montant de <strong>${this.escapeHtml(l.appareil)}</strong> vont reprendre.<br><br>Le temps passé hors ligne n'est pas facturé.`,
                    bouton: 'Continuer',
                    danger: false,
                    action: async () => {
                        this.seq++;
                        const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/continuer`);
                        toast(d.message || (ok ? 'OK' : 'Erreur.'));
                        if (ok) this.appliquer(d);
                    },
                });
            },

            confirmerSuppression(l) {
                const montantTxt = Number(this.montantFinalCalcule(l) || 0).toLocaleString('fr-FR');
                const voucherTxt = l.voucher?.username
                    ? `Son identifiant <strong>${this.escapeHtml(l.voucher.username)}</strong> sera effacé du MikroTik.`
                    : '';
                const alarmeTxt = l.alarme?.active
                    ? `<br><br><span class="font-semibold text-amber-700">Une alarme est active sur cet appareil.</span>`
                    : '';

                this.ouvrirConfirmation({
                    titre: 'Supprimer cet appareil ?',
                    message: `L'appareil <strong>${this.escapeHtml(l.appareil)}</strong> sera retiré du tableau.<br><br>${voucherTxt}${alarmeTxt}<br><br>Montant à encaisser : <strong>${montantTxt} Ar</strong>.<br><br><span class="font-semibold text-rose-600">Action définitive et irréversible.</span>`,
                    bouton: 'Supprimer définitivement',
                    danger: true,
                    action: () => this.executerSuppression(l, false),
                });
            },

            async executerSuppression(l, force) {
                this.seq++;
                const { ok, status, d } = await this.api(`${cfg.urlSession}/${l.id}` + (force ? '?force=1' : ''), { method: 'DELETE' });

                if (status === 409 && d.besoin_force) {
                    const liste = (d.autres || []).map(x => `<li class="font-mono">${this.escapeHtml(x.mac || '—')}</li>`).join('');
                    this.ouvrirConfirmation({
                        titre: 'Cet identifiant est encore utilisé',
                        message:
                            `Le voucher <strong>${this.escapeHtml(l.voucher?.username || '')}</strong> est associé à <strong>${(d.autres || []).length}</strong> autre(s) connexion(s) active(s) :` +
                            `<ul class="mt-2 list-disc pl-6 text-sm">${liste}</ul><br>` +
                            `Le supprimer coupera leur accès Internet.<br><br><strong>Voulez-vous vraiment continuer ?</strong>`,
                        bouton: 'Supprimer et déconnecter',
                        danger: true,
                        action: () => this.executerSuppression(l, true),
                    });
                    return;
                }

                toast(d.message || (ok ? 'Supprimé.' : 'Erreur.'));
                if (!ok) { if (d.redirect) window.location.href = d.redirect; return; }
                this.appliquer(d);
            },

            demanderSuppressionMasse() {
                if (this.selected.length === 0) return;
                const nb = this.selected.length;
                this.ouvrirConfirmation({
                    titre: `Supprimer ${nb} appareil(s) ?`,
                    message: `Les ${nb} appareils sélectionnés seront retirés du tableau et leurs identifiants effacés du MikroTik.<br><br><span class="font-semibold text-rose-600">Action définitive.</span>`,
                    bouton: `Supprimer ${nb}`,
                    danger: true,
                    action: async () => {
                        this.seq++;
                        const { ok, d } = await this.api(cfg.urlSupprimerMasse, {
                            body: { ids: this.selected.map(i => parseInt(i, 10)) },
                        });
                        toast(d.message || (ok ? 'Terminé.' : 'Erreur.'));
                        this.selected = [];
                        if (ok) this.appliquer(d);
                    },
                });
            },

            async sauverRemarque(l) {
                l._focusRemarque = false;
                const val = (l.remarque || '').trim();
                if ((l._remarqueAvant ?? '').trim() === val) return;     // rien n'a changé : pas de requête

                try {
                    const { ok, d } = await this.api(`${cfg.urlSession}/${l.id}/remarque`, { body: { remarque: val || null } });
                    if (!ok) { toast(d.message || 'Erreur.'); return; }
                    l.remarque = d.remarque;
                    l._remarqueAvant = d.remarque || '';
                    toast('Remarque enregistrée.');
                } catch (e) {
                    toast('Erreur réseau.');
                }
            },

            // ───────────────────── Réglages de l'alarme ─────────────────────
            nomSonIntegre(id) {
                return (cfg.sonsParDefaut.find(s => s.id === id) || {}).nom || 'Bip classique';
            },

            async chargerReglages() {
                try {
                    const r = await fetch(cfg.urlReglagesGet, { headers: { Accept: 'application/json' } });
                    if (!r.ok) return;
                    const d = await r.json();

                    const dureeSec = d.duree_secondes ?? 0;
                    const volume = (typeof d.volume === 'number' && !isNaN(d.volume)) ? d.volume : 70;
                    const integre = d.son_builtin || 'bip-classique';

                    this.reglages.form = {
                        enabled: !!d.enabled,
                        volume,
                        modeDuree: dureeSec > 0 ? 'manuel' : 'auto',
                        dureeMin: Math.floor(dureeSec / 60),
                        dureeSec: dureeSec % 60,
                        tailleMax: d.taille_max_mo ?? 10,
                        sonId: d.son_nom ? null : integre,
                        sonFichier: d.son_nom || null,
                        sonNom: d.son_nom || this.nomSonIntegre(integre),
                        sonUrl: d.son_url || null,
                    };
                    this.reglages.sonsUploades = d.sons_uploades ?? [];

                    // Réglages réellement utilisés pour la sonnerie
                    this.alarme.enabled = !!d.enabled;
                    this.alarme.volume = volume;
                    this.alarme.duree_secondes = dureeSec;
                    this.alarme.son_url = d.son_url || null;
                    this.alarme.son_nom = d.son_nom || null;
                    this.alarme.son_builtin = integre;
                } catch (e) {}
            },

            ouvrirReglages() {
                this.reglages.ouvert = true;
                this.reglages.tab = 'general';
                this.reglages.uploadErreur = '';
                this.reglages.uploadOk = '';
                this.chargerReglages();
            },

            fermerReglages() {
                this.reglages.ouvert = false;
                this.arreterSon();
                this.chargerReglages();      // abandonne les modifications non enregistrées
            },

            basculerActivation() { this.reglages.form.enabled = !this.reglages.form.enabled; },

            choisirSonDefaut(s) {
                Object.assign(this.reglages.form, { sonId: s.id, sonFichier: null, sonNom: s.nom, sonUrl: null });
            },

            choisirSonUpload(s) {
                Object.assign(this.reglages.form, { sonId: null, sonFichier: s.nom, sonNom: s.nom, sonUrl: s.url });
            },

            async enregistrerReglages() {
                const F = this.reglages.form;
                this.reglages.enCours = true;
                try {
                    let dureeSec = 0;
                    if (F.modeDuree === 'manuel') {
                        dureeSec = borne(entier(F.dureeMin) * 60 + entier(F.dureeSec), 10, 600);
                    }
                    const tailleMo = borne(entier(F.tailleMax) || 10, 1, 10);
                    const volume = borne(entier(F.volume ?? 70), 0, 100);

                    const r1 = await this.api(cfg.urlReglagesSave, {
                        body: { enabled: F.enabled ? 1 : 0, volume, duree_secondes: dureeSec, taille_max_mo: tailleMo },
                    });
                    if (!r1.ok) { toast(r1.d.message || 'Erreur serveur (réglages).'); return; }

                    // Son : fichier uploadé, ou son intégré (bip classique, carillon, urgent)
                    const nomSon = F.sonFichier || F.sonId || 'bip-classique';
                    const r2 = await this.api(cfg.urlChoisirSon, { body: { nom: nomSon } });
                    if (!r2.ok) { toast(r2.d.message || 'Son non enregistré.'); return; }

                    toast('Réglages enregistrés.');
                    this.reglages.ouvert = false;
                    this.arreterSon();
                    await this.chargerReglages();
                } catch (e) {
                    toast('Erreur réseau.');
                } finally {
                    this.reglages.enCours = false;
                }
            },

            demanderReinitialisation() { this.resetModal.ouvert = true; },

            async confirmerReinitialisation() {
                try {
                    const { ok } = await this.api(cfg.urlReinitialiser);
                    if (!ok) { toast('Réinitialisation impossible.'); return; }
                    this.resetModal.ouvert = false;
                    await this.chargerReglages();
                    toast('Réglages réinitialisés.');
                } catch (e) { toast('Erreur réseau.'); }
            },

            dropSon(event) {
                this.dragActif = false;
                const f = event.dataTransfer?.files?.[0];
                if (f) this.envoyerSon(f);
            },

            uploaderSon(event) {
                const f = event.target.files?.[0];
                if (f) this.envoyerSon(f);
                event.target.value = '';
            },

            async envoyerSon(file) {
                const R = this.reglages;
                R.uploadErreur = ''; R.uploadOk = ''; R.uploadEnCours = true;

                const tailleMaxMo = R.form.tailleMax ?? 10;
                const ext = file.name.split('.').pop().toLowerCase();

                if (file.size > tailleMaxMo * 1024 * 1024) {
                    R.uploadErreur = `Fichier trop volumineux (max ${tailleMaxMo} Mo).`;
                } else if (!['mp3', 'wav', 'ogg', 'm4a', 'aac'].includes(ext)) {
                    R.uploadErreur = 'Format non accepté (MP3, WAV, OGG, M4A, AAC).';
                }
                if (R.uploadErreur) { R.uploadEnCours = false; return; }

                const fd = new FormData();
                fd.append('son', file);

                try {
                    const r = await window.cmFetch(cfg.urlUploaderSon, { method: 'POST', body: fd });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok) {
                        R.uploadErreur = d.message || d.errors?.son?.[0] || 'Erreur d\'envoi.';
                    } else {
                        R.sonsUploades.push({ nom: d.nom, url: d.url });
                        R.uploadOk = 'Fichier ajouté. Cliquez « Choisir » pour l\'utiliser, puis « Enregistrer ».';
                    }
                } catch (e) {
                    R.uploadErreur = 'Erreur réseau.';
                }
                R.uploadEnCours = false;
            },

            demanderSupprSon(s) { this.supprSonModal = { ouvert: true, nom: s.nom, son: s }; },

            async confirmerSupprSon() {
                const s = this.supprSonModal.son;
                if (!s) return;
                try {
                    const { ok, d } = await this.api(cfg.urlSupprimerSon, { method: 'DELETE', body: { nom: s.nom } });
                    if (!ok) { toast(d.message || 'Suppression impossible.'); return; }

                    this.reglages.sonsUploades = this.reglages.sonsUploades.filter(x => x.nom !== s.nom);
                    if (this.reglages.form.sonFichier === s.nom) {
                        Object.assign(this.reglages.form, { sonId: 'bip-classique', sonFichier: null, sonUrl: null, sonNom: this.nomSonIntegre('bip-classique') });
                    }
                    this.supprSonModal.ouvert = false;
                    await this.chargerReglages();
                } catch (e) { toast('Erreur réseau.'); }
            },

            // ───────────────────── Formats ─────────────────────
            formatAr(n) { return Number(n || 0).toLocaleString('fr-FR') + ' Ar'; },

            formatDuree(m) {
                m = parseInt(m, 10) || 0;
                if (m < 60) return m + ' min';
                const h = Math.floor(m / 60), r = m % 60;
                return r === 0 ? h + ' h' : h + ' h ' + pad(r) + ' min';
            },

            formatDureeSec(sec) {
                sec = Math.max(0, Math.floor(sec));
                const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
                return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
            },

            formatOctets(o) {
                o = parseInt(o, 10) || 0;
                if (o < 1024) return o + ' o';
                if (o < 1048576) return (o / 1024).toFixed(1) + ' Ko';
                if (o < 1073741824) return (o / 1048576).toFixed(1) + ' Mo';
                return (o / 1073741824).toFixed(2) + ' Go';
            },

            // Entrée en octets par seconde (l'ancienne version mélangeait Mbit/s et Mo/s).
            formatDebit(octetsParSec) {
                const v = Number(octetsParSec) || 0;
                if (v < 1) return '0 Ko/s';
                if (v < 1024) return Math.round(v) + ' o/s';
                if (v < 1048576) return (v / 1024).toFixed(1) + ' Ko/s';
                return (v / 1048576).toFixed(2) + ' Mo/s';
            },

            heureExacte(iso) {
                if (!iso) return '—';
                const d = new Date(iso);
                return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            },

            dateLongueFr(iso) {
                if (!iso) return '—';
                const d = new Date(iso);
                const mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
                return pad(d.getDate()) + ' ' + mois[d.getMonth()] + ' ' + d.getFullYear();
            },

            // ───────────────────── Durée et montant ─────────────────────
            secondesConsommees(l) {
                if (!l.date_debut) return 0;
                const debut = new Date(l.date_debut).getTime();
                let fin;
                if (l.etat_appareil === 'en_pause' && l.pause_at) fin = new Date(l.pause_at).getTime();
                else if (l.etat_appareil === 'deconnecte' && l.deconnecte_at) fin = new Date(l.deconnecte_at).getTime();
                else if (l.etat_appareil === 'termine') {
                    fin = l.date_fin_reelle
                        ? new Date(l.date_fin_reelle).getTime()
                        : (l.deconnecte_at ? new Date(l.deconnecte_at).getTime() : debut);
                } else fin = this.now;

                let total = Math.floor((fin - debut) / 1000);
                total -= (l.secondes_hors_ligne || 0);
                total -= (l.secondes_pause || 0);
                return Math.max(0, total);
            },

            montantTempsReel(l) {
                if (!l.tarif) return 0;
                const brut = Math.floor((this.secondesConsommees(l) / 60) * l.tarif.montant_par_minute);
                return Math.max(brut, l.tarif.montant_minimum || 0);
            },

            montantFinalCalcule(l) {
                if (l.etat_appareil === 'termine' && l.montant_final != null) return l.montant_final;
                if (!l.tarif) return 0;
                const minutes = Math.ceil(this.secondesConsommees(l) / 60);
                return this.montantNet(minutes * l.tarif.montant_par_minute, l.tarif);
            },

            // ───────────────────── Présentation ─────────────────────
            libelleEtat(l) {
                return { connecte: 'Connecté', deconnecte: 'Déconnecté', en_pause: 'En pause', termine: 'Terminé' }[l.etat_appareil] || l.etat_appareil;
            },

            badgeEtat(l) {
                return {
                    'bg-emerald-50 text-emerald-700 ring-emerald-600/30': l.etat_appareil === 'connecte',
                    'bg-amber-100 text-amber-800 ring-amber-600/30': l.etat_appareil === 'deconnecte',
                    'bg-sky-50 text-sky-700 ring-sky-600/30': l.etat_appareil === 'en_pause',
                    'bg-slate-100 text-slate-700 ring-slate-500/30': l.etat_appareil === 'termine',
                };
            },

            pointEtat(l) {
                return {
                    'bg-emerald-500': l.etat_appareil === 'connecte',
                    'bg-amber-500': l.etat_appareil === 'deconnecte',
                    'bg-sky-500': l.etat_appareil === 'en_pause',
                    'bg-slate-400': l.etat_appareil === 'termine',
                };
            },

            classeLigne(l) {
                if (this.etatAlarme(l) === 'sonne') return 'bg-rose-50 border-l-4 border-rose-500';
                return {
                    'bg-emerald-50/60 border-l-4 border-emerald-500': l.etat_appareil === 'connecte',
                    'bg-amber-100/70 border-l-4 border-amber-500': l.etat_appareil === 'deconnecte',
                    'bg-sky-50 border-l-4 border-sky-400': l.etat_appareil === 'en_pause',
                    'bg-slate-100 border-l-4 border-slate-400': l.etat_appareil === 'termine',
                };
            },

            // ───────────────────── Tableau : filtre, tri, sélection ─────────────────────
            get lignesAffichees() {
                let arr = [...this.lignes];
                if (this.filtreEtat) arr = arr.filter(l => l.etat_appareil === this.filtreEtat);

                const terme = this.recherche.trim().toLowerCase();
                if (terme) {
                    arr = arr.filter(l =>
                        [l.appareil, l.ip, l.mac, l.ssid, l.voucher?.username].filter(Boolean).join(' ').toLowerCase().includes(terme));
                }

                const cle = this.cleTri, sens = this.sensTri === 'asc' ? 1 : -1;
                arr.sort((a, b) => {
                    const va = a[cle] ?? '', vb = b[cle] ?? '';
                    if (va === vb) return 0;
                    return va > vb ? sens : -sens;
                });
                return arr;
            },

            trier(cle) {
                if (this.cleTri === cle) this.sensTri = this.sensTri === 'asc' ? 'desc' : 'asc';
                else { this.cleTri = cle; this.sensTri = 'asc'; }
            },

            get tousSelectionnes() {
                const dispo = this.lignesAffichees.map(l => String(l.id));
                return dispo.length > 0 && dispo.every(id => this.selected.includes(id));
            },

            basculerTous() {
                const dispo = this.lignesAffichees.map(l => String(l.id));
                this.selected = this.tousSelectionnes ? [] : dispo;
            },

            escapeHtml(s) {
                if (s == null) return '';
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            },

            surbriller(texte) {
                const safe = this.escapeHtml(texte);
                const terme = this.recherche.trim();
                if (!terme) return safe;
                const t = this.escapeHtml(terme).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return safe.replace(new RegExp(`(${t})`, 'gi'), '<mark class="rounded-sm bg-yellow-200 px-0.5 text-slate-900">$1</mark>');
            },

            // ───────────────────── Redimensionnement des colonnes (conservé) ─────────────────────
            startResize(e, colKey) {
                this.resizing = { key: colKey, startX: e.pageX, startW: this.colWidths[colKey] };

                const onMove = (ev) => {
                    if (!this.resizing) return;
                    this.colWidths[this.resizing.key] = Math.max(50, this.resizing.startW + (ev.pageX - this.resizing.startX));
                };
                const onUp = () => {
                    try { localStorage.setItem('cm_wifi_cols', JSON.stringify(this.colWidths)); } catch (err) {}
                    this.resizing = null;
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                };

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            },
        };
    };
})();
</script>
@endverbatim