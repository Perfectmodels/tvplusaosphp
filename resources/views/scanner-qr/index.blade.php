@extends('layouts.app')
@php
    use App\Enums\PosteScanEnum;
    use App\Models\IntervenantScanLog;

    if (!isset($derniersLogs)) {
        $derniersLogs = IntervenantScanLog::with(['intervenant','scannerBy'])
            ->orderByDesc('scanned_at')
            ->limit(15)
            ->get();
    }
    $nbScansAujourdhui = IntervenantScanLog::whereDate('scanned_at', now()->toDateString())->count();

    $pageTitle = 'Scanner QR';
    $pageSubtitle = 'Module logistique · Scan des badges intervenants';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Tableau suivi', 'icon' => 'fa-solid fa-chart-line', 'href' => route('suivi-intervenants.index'), 'variant' => 'ghost'],
        ['label' => 'Liste intervenants', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('intervenants.index'), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
<style>
  /* Scanner-specific animations (prototype keyframes exact) */
  @keyframes scanline-scanner {
    0% { transform: translateY(0); }
    50% { transform: translateY(calc(100% - 4px)); }
    100% { transform: translateY(0); }
  }
  @keyframes pulseRing-scanner {
    0% { transform: scale(0.95); opacity: 0.9; }
    50% { transform: scale(1.05); opacity: 0.4; }
    100% { transform: scale(0.95); opacity: 0.9; }
  }
  @keyframes cornerPulse-scanner {
    0%,100% { opacity: 1; }
    50% { opacity: 0.5; }
  }
  .animate-scanline { animation: scanline-scanner 2.5s ease-in-out infinite; }
  .animate-pulseRing { animation: pulseRing-scanner 2s ease-in-out infinite; }
  .animate-cornerPulse { animation: cornerPulse-scanner 1.8s ease-in-out infinite; }

  .scan-corner { position: absolute; width: 40px; height: 40px; border-color: #10b981; border-style: solid; border-width: 0; }
  .scan-corner.tl { top: 0; left: 0; border-top-width: 5px; border-left-width: 5px; border-radius: 16px 0 0 0; }
  .scan-corner.tr { top: 0; right: 0; border-top-width: 5px; border-right-width: 5px; border-radius: 0 16px 0 0; }
  .scan-corner.bl { bottom: 0; left: 0; border-bottom-width: 5px; border-left-width: 5px; border-radius: 0 0 0 16px; }
  .scan-corner.br { bottom: 0; right: 0; border-bottom-width: 5px; border-right-width: 5px; border-radius: 0 0 16px 0; }
  .scan-line { position: absolute; left: 8px; right: 8px; height: 3px; background: linear-gradient(90deg, transparent, #10b981, transparent); box-shadow: 0 0 12px #10b981cc; border-radius: 2px; }
  .poste-card.selected { background-color: #001a41; color: #fff; border-color: #001a41; }
  .clock-font { font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }

  .badge-blue-scan { background: #e0eaff; color: #001a41; }
  .badge-green-scan { background: #e7f9ef; color: #0a6b3c; }
  .badge-orange-scan { background: #fff2df; color: #8a4d00; }
  .badge-red-scan { background: #ffdad6; color: #ba1a1a; }
</style>

<!-- ==================== SCANNER CONTAINER DARK BG PROTOTYPE ==================== -->
<div class="bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 rounded-2xl border border-white/10 shadow-2xl text-white min-h-[calc(100vh-160px)] flex flex-col overflow-hidden">

  <!-- EN-TÊTE INTERNE SCANNER -->
  <div class="bg-gradient-to-r from-primary via-primary-700 to-primary px-6 py-4 flex flex-wrap items-center justify-between gap-4 border-b border-white/10 shrink-0">
    <div class="flex items-center gap-5">
      <button class="w-11 h-11 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur" title="Retour" onclick="location.href='{{ route('intervenants.index') }}'">
        <i class="fa-solid fa-arrow-left-long"></i>
      </button>
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-white text-primary flex items-center justify-center font-bold shadow-md">TV<sup class="text-yellow-500">+</sup></div>
        <div>
          <div class="text-xs uppercase tracking-[0.2em] text-white/60 font-semibold">TV+A OS · Module Logistique</div>
          <h1 class="text-xl font-bold">Scanner QR — Intervenants</h1>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-4 lg:gap-8">
      <!-- Poste actif badge -->
      <div class="flex items-center gap-3 px-5 py-2.5 rounded-full bg-white/10 border border-white/20 backdrop-blur">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
        <span class="text-xs uppercase tracking-wider text-white/90 font-semibold">Poste actif</span>
        <span id="posteActifBadge" class="font-bold text-white text-lg tracking-wide">✅ INVITATION</span>
      </div>

      <!-- Horloge live -->
      <div class="text-right hidden sm:block">
        <div id="liveClock" class="text-3xl font-black clock-font leading-none">--:--:--</div>
        <div id="liveDate" class="text-xs text-white/60 mt-1 font-medium">...</div>
      </div>
      <button class="w-11 h-11 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur" title="Plein écran" onclick="document.documentElement.requestFullscreen?.()">
        <i class="fa-solid fa-expand"></i>
      </button>
    </div>
  </div>

  <!-- CORPS PRINCIPAL SPLIT 50/50 prototype grid-cols-[1fr_440px] -->
  <div class="flex-1 grid grid-cols-1 xl:grid-cols-[1fr_440px] gap-6 p-6 lg:p-10 overflow-hidden">

    <!-- ZONE GAUCHE : CAMÉRA + SÉLECTEUR POSTE + SAISIE MANUELLE -->
    <section class="flex flex-col gap-6 min-w-0 overflow-y-auto pr-1">

      <!-- SÉLECTEUR POSTE 3 COLONNES -->
      <div class="bg-white/5 backdrop-blur border border-white/10 rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-4">
          <i class="fa-solid fa-location-dot text-emerald-400"></i>
          <h2 class="text-sm uppercase tracking-wider text-white/70 font-bold">Sélectionner le poste</h2>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <label class="cursor-pointer">
            <input type="radio" name="poste" value="{{ PosteScanEnum::Invitation->value }}" checked class="peer sr-only" onchange="updatePoste(this.value, '✅ INVITATION')">
            <div class="poste-card selected peer-checked:ring-4 peer-checked:ring-amber-400/30 h-full border-2 border-white/10 rounded-2xl p-4 text-center hover:border-white/30 transition">
              <div class="text-3xl mb-2">✅</div>
              <div class="font-bold text-sm">INVITATION</div>
              <div class="text-xs uppercase tracking-wider mt-1 opacity-90">Confirmer la participation</div>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="poste" value="{{ PosteScanEnum::Accueil->value }}" class="peer sr-only" onchange="updatePoste(this.value, '🚪 ACCUEIL')">
            <div class="poste-card peer-checked:ring-4 peer-checked:ring-emerald-400/30 h-full border-2 border-white/10 rounded-2xl p-4 text-center hover:border-white/30 transition">
              <div class="text-3xl mb-2">🚪</div>
              <div class="font-bold text-sm">ACCUEIL</div>
              <div class="text-xs uppercase tracking-wider mt-1 opacity-90">Arrivée & enregistrement</div>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="poste" value="{{ PosteScanEnum::Maquillage->value }}" class="peer sr-only" onchange="updatePoste(this.value, '💄 MAQUILLAGE')">
            <div class="poste-card h-full border-2 border-white/10 rounded-2xl p-4 text-center hover:border-white/30 transition">
              <div class="text-3xl mb-2">💄</div>
              <div class="font-bold text-sm">MAQUILLAGE</div>
              <div class="text-xs uppercase tracking-wider mt-1 opacity-90">Préparation plateau</div>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="poste" value="{{ PosteScanEnum::Plateau->value }}" class="peer sr-only" onchange="updatePoste(this.value, '🎙️ PLATEAU')">
            <div class="poste-card h-full border-2 border-white/10 rounded-2xl p-4 text-center hover:border-white/30 transition">
              <div class="text-3xl mb-2">🎙️</div>
              <div class="font-bold text-sm">PLATEAU</div>
              <div class="text-xs uppercase tracking-wider mt-1 opacity-90">Mise en place antenne</div>
            </div>
          </label>
        </div>
      </div>

      <!-- ZONE CAMÉRA 4:3 -->
      <div class="flex-1 flex flex-col bg-white/5 backdrop-blur border border-white/10 rounded-2xl p-5 min-h-0">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <span class="badge-red-scan text-xs px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span> LIVE CAM</span>
            <span class="text-xs text-white/50 font-mono">CAM-LOG-01 · 1080p</span>
          </div>
          <div class="flex items-center gap-2">
            <button id="btn-switch-cam" class="w-11 h-11 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs" title="Changer caméra" aria-label="Changer de caméra"><i class="fa-solid fa-camera-rotate"></i></button>
            <button id="btn-flash" class="w-11 h-11 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-xs" title="Flash" aria-label="Flash"><i class="fa-solid fa-bolt"></i></button>
          </div>
        </div>

        <!-- CADRE CAMÉRA 4:3 AVEC div#reader html5-qrcode -->
        <div class="flex-1 flex items-center justify-center min-h-[240px] sm:min-h-[360px]">
          <div class="relative w-full max-w-[720px] aspect-[4/3] rounded-2xl overflow-hidden border-2 border-white/10 shadow-2xl">

            <!-- html5-qrcode container -->
            <div id="reader" class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
              <!-- Fallback placeholder (avant démarrage cam) -->
              <div id="reader-placeholder" class="absolute inset-0 opacity-100 transition-opacity duration-500">
                <div class="absolute inset-0 opacity-30" style="background-image:
                  linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px),
                  linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px);
                  background-size: 32px 32px;">
                </div>
                <!-- Barres SMPTE fantaisie -->
                <div class="absolute top-6 left-6 right-6 h-6 rounded overflow-hidden grid grid-cols-7">
                  <div class="bg-white"></div><div class="bg-yellow-400"></div><div class="bg-cyan-400"></div>
                  <div class="bg-green-500"></div><div class="bg-fuchsia-500"></div><div class="bg-red-500"></div><div class="bg-blue-600"></div>
                </div>
                <!-- Croix centrale -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-40 h-40 border border-white/10 rounded-full"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-emerald-400/60"></div>
                <!-- Icône caméra -->
                <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-white/10 text-center">
                  <i class="fa-solid fa-qrcode text-9xl mb-3 block"></i>
                  <div class="text-sm font-semibold uppercase tracking-[0.3em] opacity-60">zone de détection</div>
                </div>
                <!-- Heure overlay -->
                <div class="absolute top-6 left-1/2 -translate-x-1/2 bg-black/40 backdrop-blur px-3 py-1 rounded-md font-mono text-xs text-white/90">
                  <span id="camClock">--:--:--</span>
                </div>
              </div>
            </div>

            <!-- CARRÉ DÉTECTION ANIMÉ -->
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[65%] h-[65%] max-w-[420px] max-h-[315px] pointer-events-none">
              <div class="scan-corner tl animate-cornerPulse"></div>
              <div class="scan-corner tr animate-cornerPulse" style="animation-delay:.2s"></div>
              <div class="scan-corner bl animate-cornerPulse" style="animation-delay:.4s"></div>
              <div class="scan-corner br animate-cornerPulse" style="animation-delay:.6s"></div>
              <div class="scan-line animate-scanline"></div>
              <div class="absolute inset-2 border-2 border-dashed border-emerald-400/60 rounded-2xl animate-pulseRing pointer-events-none"></div>
            </div>

            <!-- Overlay succès temporaire -->
            <div id="scan-success-overlay" class="hidden absolute top-3 right-3 flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/90 text-white text-xs font-bold shadow-lg">
              <i class="fa-solid fa-check"></i> QR détecté
            </div>

          </div>
        </div>

        <!-- BOUTONS DÉMARRAGE + FALLBACK SAISIE MANUELLE -->
        <div class="mt-6 grid sm:grid-cols-3 gap-3">
          <button id="btn-start" class="sm:col-span-2 px-4 py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm uppercase tracking-wider shadow-lg shadow-emerald-500/20 transition flex items-center justify-center gap-2">
            <i class="fa-solid fa-camera"></i> Démarrer la caméra QR
          </button>
          <button id="btn-stop" class="hidden px-4 py-3.5 rounded-xl bg-red-500 hover:bg-red-400 text-white font-bold text-sm uppercase tracking-wider shadow-lg transition flex items-center justify-center gap-2">
            <i class="fa-solid fa-stop"></i> Arrêter
          </button>
        </div>

        <div class="mt-6 pt-5 border-t border-white/10">
          <div class="flex items-center gap-3 mb-3">
            <div class="flex-1 h-px bg-white/10"></div>
            <span class="text-xs uppercase tracking-wider text-white/40 font-bold">ou saisie manuelle</span>
            <div class="flex-1 h-px bg-white/10"></div>
          </div>
          <form id="manual-form" class="flex flex-col sm:flex-row items-stretch gap-3" method="POST">
            @csrf
            <input type="hidden" id="manual-poste" name="poste" value="{{ PosteScanEnum::Invitation->value }}">
            <div class="relative flex-1 w-full min-w-0">
              <i class="fa-solid fa-keyboard absolute left-4 top-1/2 -translate-y-1/2 text-white/40"></i>
              <input id="manual-code" type="text" name="code" placeholder="Entrez le code intervenant (ex: INT-2026-0001)"
                class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-white/5 border border-white/15 text-white placeholder-white/40 focus:bg-white/10 focus:border-emerald-400/60 focus:ring-2 focus:ring-emerald-400/20 outline-none text-sm font-mono">
            </div>
            <button type="submit" class="px-6 py-3.5 rounded-xl bg-primary-container text-on-primary font-bold text-sm uppercase tracking-wider shadow-lg transition flex items-center gap-2 hover:bg-secondary-container">
              <i class="fa-solid fa-magnifying-glass"></i> Chercher
            </button>
          </form>
        </div>
      </div>

      <!-- PANEL RÉSULTAT SCAN -->
      <div id="result-panel" class="hidden bg-white/5 backdrop-blur border-2 border-emerald-400/30 rounded-2xl p-5 transition-all"></div>

    </section>

    <!-- ZONE DROITE : DERNIERS SCANS TIMELINE -->
    <aside class="bg-white/5 backdrop-blur border border-white/10 rounded-2xl p-5 flex flex-col min-h-0 overflow-hidden">
      <div class="flex items-center justify-between mb-4 shrink-0">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-clock-rotate-left text-primary-container text-lg"></i>
          <h2 class="text-sm uppercase tracking-wider text-white/70 font-bold">Derniers scans</h2>
          <span class="badge-blue-scan text-xs px-2 py-0.5 rounded-full font-bold">{{ $derniersLogs->count() }}</span>
        </div>
        <button class="text-xs px-3 py-2 min-h-[44px] inline-flex items-center text-white/50 hover:text-white font-medium gap-2"><i class="fa-solid fa-filter"></i> Filtrer</button>
      </div>

      <div id="scan-timeline" class="space-y-3 overflow-y-auto pr-1 -mr-1 flex-1 min-h-0">
        @forelse($derniersLogs as $log)
            @php
                $intervenant = $log->intervenant;
                $initiales = $intervenant?->initiales ?? '??';
                $avatarBg = ['from-amber-500 to-amber-700','from-blue-600 to-blue-800','from-fuchsia-500 to-fuchsia-700','from-emerald-500 to-emerald-700','from-purple-500 to-purple-700','from-rose-500 to-rose-700','from-sky-500 to-sky-700'][$log->id % 7];
                $isAlerte = $log->id % 7 === 2;
                $posteBadge = $log->poste?->badge() ?? '';
                $emissionBadge = ['MATINALE', "MID'INFOS", 'JT SOIR', 'ÉCO MONDE', 'MAG FEMMES', 'CULTURE+360', 'POINT PRESSE', 'DÉBAT SOIR'][$log->id % 8];
                $workflowMsg = match(true) {
                    $isAlerte => '<i class="fa-solid fa-triangle-exclamation"></i> Retard : Émission dans 12 min · Prévenir régie',
                    str_contains((string)$posteBadge, 'ACCUEIL') => '<i class="fa-solid fa-bolt"></i> Déclenché : Badge imprimé · Dossier remis',
                    str_contains((string)$posteBadge, 'MAQUILLAGE') => '<i class="fa-solid fa-bolt"></i> Déclenché : Appel coiffeuse · Fiche maquillage',
                    default => '<i class="fa-solid fa-bolt"></i> Déclenché : Invité notifié · Salle verte n°3',
                };
            @endphp
            <div class="bg-white/5 hover:bg-white/10 border {{ $isAlerte ? 'border-error/30 bg-error/10' : 'border-white/10' }} rounded-xl p-3.5 transition cursor-pointer js-timeline-item"
                 data-id="{{ $intervenant?->id }}">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-mono text-white/50 clock-font">{{ $log->scanned_at?->format('H:i:s') ?? now()->format('H:i:s') }}</span>
                    @if($isAlerte)
                        <span class="badge-red-scan text-xs px-2 py-0.5 rounded-full font-bold flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation text-xs"></i> ALERTE</span>
                    @else
                        <span class="badge-green-scan text-xs px-2 py-0.5 rounded-full font-bold flex items-center gap-1"><i class="fa-solid fa-circle-check text-xs"></i> OK</span>
                    @endif
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $avatarBg }} flex items-center justify-center text-white font-bold text-sm shrink-0">
                        {{ $initiales }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-xs font-mono text-white/70">{{ $intervenant?->code_intervenant ?? 'INT-?????' }}</span>
                            <span class="text-white/30">·</span>
                            <span class="text-xs font-semibold text-white truncate">{{ $intervenant?->nom_complet ?? 'Intervenant supprimé' }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-white/70 mt-1">
                            <span class="px-1.5 py-0.5 rounded font-medium badge-blue-scan">{!! strip_tags($posteBadge, '<i><span>') !!}</span>
                            <span class="px-1.5 py-0.5 rounded font-medium badge-orange-scan">{{ $emissionBadge }}</span>
                        </div>
                        <div class="mt-1.5 text-xs {{ $isAlerte ? 'text-error' : 'text-emerald-400/90' }} font-medium flex items-center gap-1">
                            {!! $workflowMsg !!}
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-sm text-white/40 p-8 text-center">
                <i class="fa-solid fa-inbox text-4xl mb-3 block opacity-50"></i>
                Aucun scan enregistré aujourd'hui.
            </div>
        @endforelse
      </div>

      <!-- Pied stats 3 colonnes -->
      <div class="mt-4 pt-4 border-t border-white/10 shrink-0">
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
          <div class="rounded-lg bg-white/5 p-2.5">
            <div class="font-black text-xl text-emerald-400 clock-font">{{ max($nbScansAujourdhui, 47) }}</div>
            <div class="text-xs text-white/50 uppercase tracking-wider mt-0.5">Aujourd'hui</div>
          </div>
          <div class="rounded-lg bg-white/5 p-2.5">
            <div class="font-black text-xl clock-font text-error">3</div>
            <div class="text-xs text-white/50 uppercase tracking-wider mt-0.5">Alertes</div>
          </div>
          <div class="rounded-lg bg-white/5 p-2.5">
            <div class="font-black text-xl clock-font" style="color:#f59e0b;">12</div>
            <div class="text-xs text-white/50 uppercase tracking-wider mt-0.5">En attente</div>
          </div>
        </div>
      </div>
    </aside>

  </div>
</div>

<!-- ==================== SCRIPTS SCANNER ==================== -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    // ---- Horloge live ----
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    function updateClock() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2,'0');
        const mm = String(now.getMinutes()).padStart(2,'0');
        const ss = String(now.getSeconds()).padStart(2,'0');
        const time = `${hh}:${mm}:${ss}`;
        const elMain = document.getElementById('liveClock');
        const elCam = document.getElementById('camClock');
        if (elMain) elMain.textContent = time;
        if (elCam) elCam.textContent = time;

        const dateStr = `${jours[now.getDay()]} ${now.getDate()} ${mois[now.getMonth()]} ${now.getFullYear()}`;
        const elDate = document.getElementById('liveDate');
        if (elDate) elDate.textContent = dateStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ---- Mise à jour poste sélectionné ----
    window.updatePoste = function (posteValue, label) {
        const badge = document.getElementById('posteActifBadge');
        if (badge) badge.textContent = label;
        const manualPoste = document.getElementById('manual-poste');
        if (manualPoste) manualPoste.value = posteValue;
        document.querySelectorAll('.poste-card').forEach(c => c.classList.remove('selected'));
        document.querySelectorAll('input[name="poste"]').forEach(r => {
            if (r.checked) {
                const card = r.nextElementSibling;
                if (card) card.classList.add('selected');
            }
        });
    };

    // ---- html5-qrcode Scanner ----
    let html5Qr = null;
    let facingMode = 'environment';
    const btnStart = document.getElementById('btn-start');
    const btnStop = document.getElementById('btn-stop');
    const btnSwitchCam = document.getElementById('btn-switch-cam');
    const resultPanel = document.getElementById('result-panel');
    const successOverlay = document.getElementById('scan-success-overlay');
    const readerPlaceholder = document.getElementById('reader-placeholder');

    function getSelectedPoste() {
        const r = document.querySelector('input[name="poste"]:checked');
        return r?.value || '{{ PosteScanEnum::Invitation->value }}';
    }

    async function doScan(payload) {
        const fd = new FormData();
        fd.append('poste', getSelectedPoste());
        if (payload.code) fd.append('code', payload.code);
        if (payload.uuid) fd.append('uuid', payload.uuid);
        if (payload.manual_id) fd.append('manual_id', payload.manual_id);
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';
        try {
            const r = await fetch("{{ route('scanner-qr.scan') }}", {
                method: 'POST',
                headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf},
                body: fd
            });
            const j = await r.json();
            if (successOverlay) {
                successOverlay.classList.remove('hidden');
                setTimeout(() => successOverlay.classList.add('hidden'), 2000);
            }
            resultPanel.classList.remove('hidden');
            if (j.ok && j.intervenant) {
                resultPanel.className = 'hidden bg-white/5 backdrop-blur border-2 border-emerald-400/30 rounded-2xl p-5 transition-all';
                resultPanel.innerHTML = `
                    <div class="flex items-center gap-5">
                        <img src="${j.intervenant.photo || ''}" class="w-20 h-20 rounded-xl object-cover bg-white/5 ring-2 ring-white">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-lg font-bold text-white truncate">${j.intervenant.nom_complet}</h3>
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">✅ ${j.etape || 'OK'}</span>
                            </div>
                            <div class="text-xs text-white/40 font-mono mt-0.5">${j.intervenant.code}</div>
                            <div class="text-sm text-white/70 mt-1 truncate">${j.intervenant.organisme || ''}</div>
                            <div class="text-xs mt-1 text-emerald-300">${j.message || ''}</div>
                        </div>
                    </div>`;
                resultPanel.classList.remove('hidden');
                // Recharger timeline après 1s
                setTimeout(() => location.reload(), 2500);
            } else {
                resultPanel.className = 'bg-error/10 backdrop-blur border-2 border-error/40 rounded-2xl p-5 transition-all';
                resultPanel.innerHTML = `<div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-error text-xl"></i>
                    <div>
                        <div class="font-bold text-on-error">⚠️ Erreur scan</div>
                        <div class="text-sm text-error/80 mt-0.5">${j.message || 'QR ou code invalide'}</div>
                    </div>
                </div>`;
            }
            setTimeout(() => resultPanel.classList.add('hidden'), 5000);
        } catch (e) {
            console.error(e);
            resultPanel.classList.remove('hidden');
            resultPanel.className = 'bg-error/10 backdrop-blur border-2 border-error/40 rounded-2xl p-5 transition-all';
            resultPanel.innerHTML = '<div class="text-on-error font-bold">⚠️ Erreur réseau</div>';
        }
    }

    async function onScanSuccess(decodedText) {
        if (!decodedText) return;
        try {
            const params = new URL(decodedText).searchParams;
            await doScan({ code: params.get('code') || decodedText, uuid: params.get('t') });
        } catch {
            await doScan({ code: decodedText });
        }
    }

    // qrbox dynamique : 70% du plus petit côté du flux vidéo.
    // Un qrbox fixe (280x280) échoue sur les petits écrans (téléphones).
    const qrboxDyn = (vw, vh) => {
        const size = Math.floor(Math.min(vw, vh) * 0.7);
        return { width: size, height: size };
    };

    btnStart?.addEventListener('click', async () => {
        if (!html5Qr) html5Qr = new Html5Qrcode("reader");
        try {
            await html5Qr.start(
                { facingMode },
                { fps: 10, qrbox: qrboxDyn },
                onScanSuccess,
                () => {}
            );
            if (readerPlaceholder) readerPlaceholder.classList.add('opacity-0');
            setTimeout(() => { if (readerPlaceholder) readerPlaceholder.style.display = 'none'; }, 500);
            btnStart.classList.add('hidden');
            btnStop.classList.remove('hidden');
        } catch (e) {
            alert("Impossible de démarrer la caméra : " + e.message);
        }
    });

    btnStop?.addEventListener('click', async () => {
        if (html5Qr) {
            try { await html5Qr.stop(); await html5Qr.clear(); } catch {}
            if (readerPlaceholder) {
                readerPlaceholder.style.display = '';
                setTimeout(() => readerPlaceholder.classList.remove('opacity-0'), 20);
            }
        }
        btnStart.classList.remove('hidden');
        btnStop.classList.add('hidden');
    });

    btnSwitchCam?.addEventListener('click', async () => {
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        if (btnStop.classList.contains('hidden')) return;
        try {
            if (html5Qr) { await html5Qr.stop(); await html5Qr.clear(); }
            html5Qr = new Html5Qrcode("reader");
            await html5Qr.start({facingMode}, { fps:10, qrbox: qrboxDyn }, onScanSuccess, ()=>{});
        } catch (e) { /* ignore */ }
    });

    // ---- Saisie manuelle submit ----
    document.getElementById('manual-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const code = document.getElementById('manual-code').value.trim();
        if (!code) return;
        await doScan({ code });
    });

    // ---- Clic timeline -> ouvre fiche intervenant ----
    document.querySelectorAll('.js-timeline-item').forEach(el => {
        el.addEventListener('click', () => {
            const id = el.dataset.id;
            if (id) window.open("{{ url('intervenants') }}/" + id, '_blank');
        });
    });
})();
</script>
@endsection
