<div class="max-w-7xl mx-auto space-y-8">
<!-- Page Header -->
<div class="flex justify-between items-end">
<div>
<h2 class="font-headline-xl text-headline-xl text-on-primary-fixed">Vue d'ensemble</h2>
<p class="text-on-surface-variant mt-1">Supervision globale de la production et diffusion.</p>
</div>
<button class="bg-primary-container text-white px-6 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2 hover:bg-primary transition-colors">
                        Créer une alerte
                        <span class="material-symbols-outlined text-sm" data-icon="arrow_forward">arrow_forward</span>
</button>
</div>
<!-- KPI Row (Glassmorphism inspired cards) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<!-- KPI 1 -->
<div class="bg-white rounded-xl p-6 card-shadow border border-outline-variant/30 flex flex-col relative overflow-hidden group">
<div class="absolute top-0 right-0 w-16 h-16 bg-primary-container/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
<div class="flex justify-between items-start mb-4">
<span class="text-on-surface-variant font-semibold text-sm">Diffusions Totales</span>
<span class="material-symbols-outlined text-primary-container" data-icon="live_tv">live_tv</span>
</div>
<div class="text-3xl font-black text-on-primary-fixed mb-1">142</div>
<div class="text-xs text-secondary-container font-medium flex items-center gap-1">
<span class="material-symbols-outlined text-sm" data-icon="trending_up">trending_up</span> +12% ce mois
                        </div>
</div>
<!-- KPI 2 -->
<div class="bg-white rounded-xl p-6 card-shadow border border-outline-variant/30 flex flex-col relative overflow-hidden group">
<div class="absolute top-0 right-0 w-16 h-16 bg-secondary-container/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
<div class="flex justify-between items-start mb-4">
<span class="text-on-surface-variant font-semibold text-sm">Invités Actifs</span>
<span class="material-symbols-outlined text-secondary-container" data-icon="group">group</span>
</div>
<div class="text-3xl font-black text-on-primary-fixed mb-1">28</div>
<div class="text-xs text-on-surface-variant font-medium">Sur 4 plateaux en cours</div>
</div>
<!-- KPI 3 -->
<div class="bg-white rounded-xl p-6 card-shadow border border-outline-variant/30 flex flex-col relative overflow-hidden group">
<div class="absolute top-0 right-0 w-16 h-16 bg-primary-container/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
<div class="flex justify-between items-start mb-4">
<span class="text-on-surface-variant font-semibold text-sm">Progression Prod.</span>
<span class="material-symbols-outlined text-primary-container" data-icon="donut_large">donut_large</span>
</div>
<div class="text-3xl font-black text-on-primary-fixed mb-1">85%</div>
<div class="w-full bg-surface-container h-1.5 rounded-full mt-2">
<div class="bg-primary-container h-1.5 rounded-full" style="width: 85%"></div>
</div>
</div>
<!-- KPI 4 -->
<div class="bg-white rounded-xl p-6 card-shadow border border-error/20 flex flex-col relative overflow-hidden group">
<div class="absolute top-0 right-0 w-16 h-16 bg-error/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
<div class="flex justify-between items-start mb-4">
<span class="text-error font-semibold text-sm">Alertes en attente</span>
<span class="material-symbols-outlined text-error" data-icon="warning">warning</span>
</div>
<div class="text-3xl font-black text-error mb-1">3</div>
<div class="text-xs text-error/80 font-medium">Action requise immédiatement</div>
</div>
</div>
<!-- Main Section: 60/40 Split -->
<div class="flex flex-col lg:flex-row gap-6">
<!-- Left Column (60%): Planning des Émissions -->
<div class="w-full lg:w-[60%] flex flex-col gap-4">
<div class="flex justify-between items-center">
<h3 class="font-headline-sm text-headline-sm text-on-primary-fixed uppercase tracking-wider">Planning des Émissions</h3>
<button class="text-xs font-bold text-primary-container flex items-center gap-1 hover:underline">Voir tout <span class="material-symbols-outlined text-sm" data-icon="arrow_forward">arrow_forward</span></button>
</div>
<div class="bg-white rounded-xl card-shadow border border-outline-variant/30 flex flex-col overflow-hidden">
<!-- List Header -->
<div class="grid grid-cols-12 gap-4 px-6 py-3 bg-surface border-b border-outline-variant/30 text-xs font-semibold text-on-surface-variant uppercase">
<div class="col-span-2">Heure</div>
<div class="col-span-5">Émission</div>
<div class="col-span-3">Plateau</div>
<div class="col-span-2 text-right">Statut</div>
</div>
<!-- Item 1 (En direct) -->
<div class="grid grid-cols-12 gap-4 px-6 py-4 border-b border-outline-variant/30 items-center hover:bg-surface-container-lowest transition-colors relative">
<div class="absolute left-0 top-0 bottom-0 w-1 bg-error"></div>
<div class="col-span-2 font-bold text-on-primary-fixed">08:00</div>
<div class="col-span-5">
<div class="font-bold text-on-primary-fixed">Le Grand Matin</div>
<div class="text-xs text-on-surface-variant">Présenté par A. Diallo</div>
</div>
<div class="col-span-3 text-sm text-on-surface-variant">Studio A</div>
<div class="col-span-2 flex justify-end">
<span class="px-2 py-1 bg-error/10 text-error text-xs font-bold rounded-md uppercase tracking-wider flex items-center gap-1">
<div class="w-1.5 h-1.5 bg-error rounded-full animate-pulse"></div>
                                        En direct
                                    </span>
</div>
</div>
<!-- Item 2 (Prêt) -->
<div class="grid grid-cols-12 gap-4 px-6 py-4 border-b border-outline-variant/30 items-center hover:bg-surface-container-lowest transition-colors">
<div class="col-span-2 font-bold text-on-primary-fixed">10:30</div>
<div class="col-span-5">
<div class="font-bold text-on-primary-fixed">Éco Afrique</div>
<div class="text-xs text-on-surface-variant">Dossier: ZLECAf</div>
</div>
<div class="col-span-3 text-sm text-on-surface-variant">Studio C</div>
<div class="col-span-2 flex justify-end">
<span class="px-2 py-1 bg-primary-container/10 text-primary-container text-xs font-bold rounded-md uppercase tracking-wider">
                                        Prêt
                                    </span>
</div>
</div>
<!-- Item 3 (En montage) -->
<div class="grid grid-cols-12 gap-4 px-6 py-4 items-center hover:bg-surface-container-lowest transition-colors">
<div class="col-span-2 font-bold text-on-primary-fixed">14:00</div>
<div class="col-span-5">
<div class="font-bold text-on-primary-fixed">Reportage Spécial</div>
<div class="text-xs text-on-surface-variant">Sujet: Tech Hubs</div>
</div>
<div class="col-span-3 text-sm text-on-surface-variant">Salle Montage 2</div>
<div class="col-span-2 flex justify-end">
<span class="px-2 py-1 bg-outline-variant/30 text-on-surface-variant text-xs font-bold rounded-md uppercase tracking-wider">
                                        En montage
                                    </span>
</div>
</div>
</div>
</div>
<!-- Right Column (40%): Suivi des Intervenants -->
<div class="w-full lg:w-[40%] flex flex-col gap-4">
<div class="flex justify-between items-center">
<h3 class="font-headline-sm text-headline-sm text-on-primary-fixed uppercase tracking-wider">Suivi Intervenants</h3>
<button class="text-on-surface-variant hover:text-primary-container transition-colors">
<span class="material-symbols-outlined text-[18px]" data-icon="more_horiz">more_horiz</span>
</button>
</div>
<div class="bg-white rounded-xl card-shadow border border-outline-variant/30 p-5 flex flex-col gap-4 h-full">
<!-- Guest 1 -->
<div class="flex items-center gap-4 p-3 rounded-lg border border-outline-variant/50 hover:border-primary-container/50 transition-colors">
<div class="relative">
<img alt="Guest 1" class="w-12 h-12 rounded-full object-cover border border-outline-variant" data-alt="A professional headshot of a mature male political analyst wearing a grey suit, modern corporate lighting, solid background, high resolution." src="https://lh3.googleusercontent.com/aida-public/AB6AXuAlwWYSMr83f5lOdLrUeAlnw-GTwok7SBADO1ihhB45bGKJsSaVGIdtuoCh1Sq0gzme9L9vETrkxlnccmKxQnpnOpMiZUdpKA2w2uHL4sKA1H7LH_GbaqAUmoeS28ZdaD2bk2LVNxZhglgSjx7ehQACIgSEhYd-hE2-eeJEt9eQF_TGWoFEnKZOqtsLt18Dc_fVQ439Pj5YKbsfBZGiGlrPHkgR1uqZ8jhMNP3e5bNkRzD2c5QsZINA"/>
<div class="absolute bottom-0 right-0 w-3 h-3 bg-secondary-container rounded-full border-2 border-white"></div>
</div>
<div class="flex-1">
<div class="font-bold text-sm text-on-primary-fixed">Dr. Ousmane Sy</div>
<div class="text-xs text-on-surface-variant">Analyste Politique</div>
</div>
<div class="text-right">
<div class="text-xs font-bold text-secondary-container uppercase tracking-wider mb-1">En loge</div>
<div class="text-xs text-on-surface-variant flex items-center gap-1 justify-end">
<span class="material-symbols-outlined text-xs" data-icon="schedule">schedule</span> 07:45
                                    </div>
</div>
</div>
<!-- Guest 2 -->
<div class="flex items-center gap-4 p-3 rounded-lg border border-outline-variant/50 hover:border-primary-container/50 transition-colors">
<div class="relative">
<div class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center font-bold text-on-surface-variant border border-outline-variant">
                                        MK
                                    </div>
<div class="absolute bottom-0 right-0 w-3 h-3 bg-error rounded-full border-2 border-white"></div>
</div>
<div class="flex-1">
<div class="font-bold text-sm text-on-primary-fixed">Mariam Kamara</div>
<div class="text-xs text-on-surface-variant">Ministre de l'Économie</div>
</div>
<div class="text-right">
<div class="text-xs font-bold text-error uppercase tracking-wider mb-1">En transit</div>
<div class="text-xs text-on-surface-variant flex items-center gap-1 justify-end">
<span class="material-symbols-outlined text-xs" data-icon="directions_car">directions_car</span> 5 min
                                    </div>
</div>
</div>
<!-- Map visual snippet -->
<div class="mt-auto h-24 rounded-lg bg-surface-container-high border border-outline-variant overflow-hidden relative">
<div class="w-full h-full bg-cover bg-center opacity-80" data-alt="A highly stylized, minimalist digital map of a city focusing on routes to a central hub. Deep midnight blue and subtle grey tones with sharp neon blue accent lines indicating traffic flow. Corporate modern aesthetic, high contrast." data-location="Dakar" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuC0mSb3QgtU_8aKf2waIUQElenwEsaZTJ4cDy9iuVZg0A-MqvzlqKpCZSVfWW3xm0Whyy5T-Rco21h8c1YX20fjqtkrwnl8v85TQ5llJ3LU_E6EOZgYXSGi35H7791utZI6U3KbjXZ9sEqyVbVc_9K9F53by7IewC3Ir38Kc8QCrgy_LBHJ3ltXNkmQtEdYYyKK4iScuPztKXgCrfjKSuNxzCnVcLQp4yxsziuCFw0yRtVLNEWiP8l0')"></div>
<div class="absolute inset-0 flex items-center justify-center bg-primary-container/20 backdrop-blur-[1px]">
<button class="bg-white/90 px-3 py-1.5 rounded text-xs font-bold text-on-primary-fixed shadow-sm">Voir la carte flotte</button>
</div>
</div>
</div>
</div>
</div>
<!-- Bottom Section: Veille Info -->
<div class="flex flex-col gap-4 pt-4 border-t border-outline-variant/50">
<div class="flex justify-between items-center">
<h3 class="font-headline-sm text-headline-sm text-on-primary-fixed uppercase tracking-wider flex items-center gap-2">
<span class="material-symbols-outlined text-primary-container" data-icon="rss_feed">rss_feed</span>
                            Veille Info &amp; Alertes
                        </h3>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<!-- Alert 1 -->
<div class="bg-white p-4 rounded-xl border-l-4 border-error card-shadow">
<div class="text-xs font-bold text-on-surface-variant mb-2 flex justify-between">
<span>FLASH INFO</span>
<span>Il y a 2 min</span>
</div>
<p class="text-sm font-semibold text-on-primary-fixed mb-2 line-clamp-2">Rupture de signal signalée sur le faisceau satellite Ouest-Africain. Équipe technique notifiée.</p>
<a class="text-xs font-bold text-error hover:underline" href="#">Gérer l'incident</a>
</div>
<!-- Alert 2 -->
<div class="bg-white p-4 rounded-xl border-l-4 border-secondary-container card-shadow">
<div class="text-xs font-bold text-on-surface-variant mb-2 flex justify-between">
<span>AGENCE DE PRESSE</span>
<span>Il y a 15 min</span>
</div>
<p class="text-sm font-semibold text-on-primary-fixed mb-2 line-clamp-2">Nouveau communiqué publié concernant le sommet climat régional. Prêt pour intégration au prompteur.</p>
<a class="text-xs font-bold text-secondary-container hover:underline" href="#">Voir le texte</a>
</div>
<!-- Alert 3 -->
<div class="bg-white p-4 rounded-xl border-l-4 border-outline-variant card-shadow opacity-70">
<div class="text-xs font-bold text-on-surface-variant mb-2 flex justify-between">
<span>SYSTÈME</span>
<span>Il y a 1h</span>
</div>
<p class="text-sm font-semibold text-on-primary-fixed mb-2 line-clamp-2">Mise à jour planifiée des serveurs d'archivage ce soir à 02:00 GMT.</p>
<span class="text-xs font-bold text-on-surface-variant">Acquitté</span>
</div>
</div>
</div>
</div>