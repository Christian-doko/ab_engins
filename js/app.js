/* ============================================================
   AB ENGINS SARL — Tableau de bord
   Rendu piloté par les données. La navigation, le topbar et le
   pourcentage de disponibilité sont désormais rendus côté serveur
   (partials/head.php) ; ce fichier ne gère que le contenu de la page.
   Dépend de js/shell.js (apiFetch, $ , el) chargé avant ce script.
   ============================================================ */
"use strict";

/* ---------------- Icônes KPI (inline SVG) ---------------- */
const ICON = {
  active: '<svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>',
  gauge:  '<svg viewBox="0 0 24 24"><path d="M12 13l4-4"/><path d="M3 17a9 9 0 1 1 18 0"/></svg>',
  alert:  '<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>',
  cash:   '<svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>'
};

const API_URL = "api/dashboard.php";

// Données utilisées uniquement si l'API est injoignable (base indisponible, etc.).
const DEMO = {
  kpis: { contrats_actifs: 12, disponibilite_pct: 68, permis_bientot_expires: 3, factures_impayees: 4200000 },
  chart: [
    { mois: "Fév", valeur: 5 }, { mois: "Mar", valeur: 7 }, { mois: "Avr", valeur: 6 },
    { mois: "Mai", valeur: 10 }, { mois: "Juin", valeur: 8 }, { mois: "Juil", valeur: 12 }
  ],
  permis_a_renouveler: [
    { client: "SOTRAF SARL",    numero_permis: "PF-2024-118", jours_restants: 14, expiration: "02/08" },
    { client: "MINEX Cameroun", numero_permis: "PM-2025-046", jours_restants: 27, expiration: "15/08" }
  ],
  derniers_contrats: [
    { reference: "CT-2026-041", client: "SOTRAF SARL",    engin: "Bulldozer D7G",  date_effet: "15/07/2026", statut: "actif" },
    { reference: "CT-2026-040", client: "MINEX Cameroun", engin: "Grumier MAN",    date_effet: "10/07/2026", statut: "actif" },
    { reference: "CT-2026-039", client: "AGRO-PLUS",      engin: "Abatteuse 953M", date_effet: "02/07/2026", statut: "termine" },
    { reference: "CT-2026-038", client: "LOTICAM",        engin: "Niveleuse 140K", date_effet: "28/06/2026", statut: "resilie" }
  ]
};

const STATUS_LABEL = { actif: "Actif", termine: "Terminé", resilie: "Résilié", renouvele: "Renouvelé" };

async function fetchDashboard() {
  try {
    const data = await apiFetch(API_URL);
    return { data, live: true };
  } catch (err) {
    console.warn("API indisponible, affichage des données de démonstration.", err);
    return { data: DEMO, live: false };
  }
}

function buildKpiCards(k) {
  const fmtFcfa = (n) => new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 0 }).format(n);
  return [
    { label: "Contrats actifs",    value: String(k.contrats_actifs),        icon: ICON.active, color: "var(--green-600)", foot: "Contrats en cours" },
    { label: "Disponibilité parc", value: k.disponibilite_pct + "%",        icon: ICON.gauge,  color: "var(--blue)",      foot: "Engins disponibles" },
    { label: "Permis < 30 j",      value: String(k.permis_bientot_expires), icon: ICON.alert,  color: "var(--orange)",    foot: "À renouveler bientôt" },
    { label: "Factures impayées",  value: fmtFcfa(k.factures_impayees),     icon: ICON.cash,   color: "var(--red)",       foot: "FCFA en attente" }
  ];
}

function renderKpis(kpis) {
  const grid = $("#kpiGrid");
  grid.innerHTML = "";
  buildKpiCards(kpis).forEach((k) => {
    grid.appendChild(el(`
      <article class="kpi" style="--accent:${k.color}">
        <div class="kpi-top">
          <span class="kpi-label">${k.label}</span>
          <span class="kpi-icon">${k.icon}</span>
        </div>
        <div class="kpi-value">${k.value}</div>
        <div class="kpi-foot">${k.foot}</div>
      </article>`));
  });
}

function renderChart(points) {
  const chart = $("#chart");
  chart.innerHTML = "";
  const max = Math.max(1, ...points.map((c) => c.valeur));
  points.forEach((c) => {
    const h = Math.round((c.valeur / max) * 100);
    const peak = c.valeur === max ? 1 : 0;
    const col = el(`
      <div class="bar-col">
        <div class="bar" data-peak="${peak}" style="height:0"><span class="tip">${c.valeur} contrats</span></div>
        <span class="bar-label">${c.mois}</span>
      </div>`);
    chart.appendChild(col);
    // animation d'entrée
    requestAnimationFrame(() => setTimeout(() => { col.querySelector(".bar").style.height = h + "%"; }, 60));
  });
}

function renderPermits(permits) {
  const list = $("#permitList");
  const chip = $("#permitChip");
  list.innerHTML = "";
  if (chip) chip.textContent = permits.length ? `${permits.length} urgent${permits.length > 1 ? "s" : ""}` : "Aucun";
  if (!permits.length) {
    list.appendChild(el(`<li class="permit"><span class="permit-info"><strong>Aucun permis à renouveler</strong><small>Tous les permis valides sont à jour</small></span></li>`));
    return;
  }
  permits.forEach((p) => {
    const urgent = p.jours_restants <= 15;
    const initials = p.client.split(" ").map((w) => w[0]).slice(0, 2).join("");
    list.appendChild(el(`
      <li class="permit">
        <span class="permit-ico">${initials}</span>
        <span class="permit-info">
          <strong>${p.client}</strong>
          <small>${p.numero_permis} · ${p.jours_restants} j restants</small>
        </span>
        <span class="permit-tag ${urgent ? "tag-red" : "tag-orange"}">expire ${p.expiration}</span>
      </li>`));
  });
}

function renderContracts(contracts) {
  const tbody = $("#contractTable tbody");
  tbody.innerHTML = "";
  contracts.forEach((c) => {
    tbody.appendChild(el(`
      <tr>
        <td class="mono">${c.reference}</td>
        <td>${c.client}</td>
        <td>${c.engin}</td>
        <td>${c.date_effet}</td>
        <td><span class="status status-${c.statut}">${STATUS_LABEL[c.statut] || c.statut}</span></td>
      </tr>`));
  });
}

document.addEventListener("DOMContentLoaded", async () => {
  const { data, live } = await fetchDashboard();
  renderKpis(data.kpis);
  renderChart(data.chart);
  renderPermits(data.permis_a_renouveler);
  renderContracts(data.derniers_contrats);

  if (!live) {
    $(".content").prepend(el(`
      <div style="background:#fef3e2;color:#b45309;font-size:12.5px;font-weight:600;text-align:center;padding:8px;border-radius:10px;">
        Mode démonstration — API indisponible, données d'exemple affichées
      </div>`));
  }

  const bellDot = $("#bellDot");
  if (bellDot && data.permis_a_renouveler.length) bellDot.hidden = false;

  const bell = $("#bell");
  if (bell) {
    bell.addEventListener("click", () => {
      const first = data.permis_a_renouveler[0];
      alert(first
        ? `${data.permis_a_renouveler.length} permis à surveiller : ${first.client} expire dans ${first.jours_restants} jours.`
        : "Aucune notification pour le moment.");
    });
  }
});
