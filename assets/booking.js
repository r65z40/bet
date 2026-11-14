/* Tunnel de réservation — La Salle RN6. Le prix affiché est toujours recalculé par le serveur. */
(function () {
  'use strict';
  const C = window.BK;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const form = $('#bk');
  const eur = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
  const eurShort = v => (Math.round(v) === v ? v.toLocaleString('fr-FR') : v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) + ' €';
  const monthsFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  const daysFr = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
  const hoursLabel = h => { const H = Math.floor(h), m = Math.round((h - H) * 60); return H + ' h' + (m ? ' ' + String(m).padStart(2, '0') : ''); };
  const addMonths = (ym, n) => { const [y, m] = ym.split('-').map(Number); const d = new Date(y, m - 1 + n, 1); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); };
  const isoDow = d => (d.getDay() + 6) % 7 + 1;

  const st = { promo: '', mode: null, days: 1, month: C.today, date: null, start: null, hours: null, total: null, quoteOk: false };
  const cache = {};
  const maxMonth = addMonths(C.today, C.maxMonths);

  /* ---------- Étape 1 : formule ---------- */
  $$('.formula').forEach(b => b.addEventListener('click', () => {
    st.mode = b.dataset.mode;
    $$('.formula').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
    st.date = st.start = st.hours = null;
    $('#step2').classList.remove('locked');
    $('#legendPartial').hidden = st.mode !== 'hour';
    $('#daysPick').hidden = st.mode !== 'day' || C.maxDays < 2;
    $('#slots').hidden = true; // heures d'arrivée : seulement en location à l'heure, après le choix du jour
    renderCal();
    updateSteps();
    quote();
    if (window.innerWidth < 980) $('#step2').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  /* ---------- Durée de la location à la journée (24 h, 48 h, 72 h) ---------- */
  $$('#daysPick .chip').forEach(c => c.addEventListener('click', () => {
    st.days = parseInt(c.dataset.days, 10);
    $$('#daysPick .chip').forEach(x => x.setAttribute('aria-pressed', x === c ? 'true' : 'false'));
    const d = st.date && cache[st.month] && cache[st.month][st.date];
    if (!d || (d.days || 0) < st.days) st.date = null; // la date choisie ne permet pas cette durée
    renderCal(); updateSteps(); quote();
  }));
  function dayPriceFrom(y, m, d) {
    let sum = 0;
    for (let i = 0; i < st.days; i++) {
      const dt = new Date(y, m - 1, d + i);
      const key = dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
      const info = (cache[key.slice(0, 7)] || {})[key];
      sum += info && info.p ? info.p : C.dayPrices[isoDow(dt)]; // prix réel du jour (dates spéciales)
    }
    const disc = C.dayDiscounts[st.days] || 0;
    return Math.round(sum * (1 - disc / 100) * 100) / 100;
  }

  /* ---------- Étape 2 : calendrier ---------- */
  // Disponibilités d'un mois, relues si elles ont plus d'une minute (une autre réservation a pu être prise entre-temps)
  const cacheAt = {};
  async function loadMonth(ym, force) {
    if (cache[ym] && !force && Date.now() - (cacheAt[ym] || 0) < 60000) return cache[ym];
    const r = await fetch('api.php?a=month&m=' + ym, { headers: { Accept: 'application/json' } });
    const j = await r.json();
    if (!r.ok) throw new Error(j.error || 'Erreur');
    cacheAt[ym] = Date.now();
    return (cache[ym] = j.days || {});
  }

  function dayState(info) {
    if (!info) return '';
    if (st.mode === 'day') return (info.days || 0) >= st.days ? 'free' : '';
    if (!Object.keys(info.hours || {}).length) return '';
    return info.full ? 'free' : 'partial';
  }

  async function renderCal(force) {
    if (!st.mode) return;
    const cal = $('#cal');
    const [y, m] = st.month.split('-').map(Number);
    $('#calTitle').textContent = monthsFr[m - 1] + ' ' + y;
    $('#calPrev').disabled = st.month <= C.today;
    $('#calNext').disabled = st.month >= maxMonth;
    cal.classList.add('cal-loading');
    let days;
    try { days = await loadMonth(st.month, force); } catch (e) { cal.innerHTML = '<p class="alert alert-err" style="grid-column:1/-1">Impossible de charger les disponibilités. Rechargez la page.</p>'; return; }
    cal.classList.remove('cal-loading');
    const first = new Date(y, m - 1, 1);
    let html = ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((d, i) => `<div class="dow" aria-label="${daysFr[i]}">${d}</div>`).join('');
    for (let i = 1; i < isoDow(first); i++) html += '<div class="day empty"></div>';
    const n = new Date(y, m, 0).getDate();
    for (let d = 1; d <= n; d++) {
      const key = `${st.month}-${String(d).padStart(2, '0')}`;
      const info = days[key];
      const cls = dayState(info);
      const dow = isoDow(new Date(y, m - 1, d));
      const price = st.mode === 'day' && cls ? `<small class="${info && info.sp ? 'sp' : ''}">${info && info.sp ? '★ ' : ''}${eurShort(dayPriceFrom(y, m, d))}</small>` : (cls && info && info.sp ? '<small class="sp">★</small>' : '');
      const label = escAttr(`${daysFr[dow - 1]} ${d} ${monthsFr[m - 1]}${cls ? '' : ' (indisponible)'}${info && info.sp ? ' — tarif spécial ' + info.sp : ''}`);
      html += `<button type="button" class="day ${cls} ${key === st.date ? 'sel' : ''}" data-date="${key}" ${cls ? '' : 'disabled'} aria-label="${label}" aria-pressed="${key === st.date}">${d}${price}</button>`;
    }
    cal.innerHTML = html;
    $$('.day[data-date]:not([disabled])', cal).forEach(b => b.addEventListener('click', () => selectDate(b.dataset.date)));
  }
  $('#calPrev').addEventListener('click', () => { st.month = addMonths(st.month, -1); renderCal(); });
  $('#calNext').addEventListener('click', () => { st.month = addMonths(st.month, 1); renderCal(); });

  async function selectDate(key) {
    // Données de plus d'une minute : on relit le mois avant de proposer les horaires
    if (Date.now() - (cacheAt[st.month] || 0) >= 60000) {
      await renderCal(true);
      const info = (cache[st.month] || {})[key];
      if (!info || !dayState(info) || (st.mode === 'day' && !info.day)) {
        st.date = null; $('#slots').hidden = true; updateSteps(); quote();
        showErrors(['Cette date vient d’être réservée : choisissez-en une autre.']);
        return;
      }
    }
    st.date = key; st.start = null; st.hours = null;
    $$('.day').forEach(b => { const on = b.dataset.date === key; b.classList.toggle('sel', on); b.setAttribute('aria-pressed', on); });
    const slots = $('#slots');
    if (st.mode === 'hour') {
      const info = cache[st.month][key];
      slots.hidden = false;
      $('#startChips').innerHTML = Object.keys(info.hours).map(t => `<button type="button" class="chip" data-start="${t}" aria-pressed="false">${t.replace(':', 'h')}</button>`).join('');
      $$('#startChips .chip').forEach(c => c.addEventListener('click', () => selectStart(c.dataset.start)));
      $('#duration').innerHTML = '<option value="">Choisissez d’abord l’heure d’arrivée</option>';
      $('#duration').disabled = true;
    } else {
      slots.hidden = true;
    }
    updateSteps();
    quote();
  }

  function selectStart(t) {
    st.start = t;
    $$('#startChips .chip').forEach(c => c.setAttribute('aria-pressed', c.dataset.start === t));
    const max = cache[st.month][st.date].hours[t];
    const sel = $('#duration');
    let opts = '';
    for (let h = C.minHours; h <= max + 1e-9; h += C.step / 60) {
      const [hh, mm] = t.split(':').map(Number);
      const endMin = hh * 60 + mm + Math.round(h * 60);
      const end = String(Math.floor(endMin / 60)).padStart(2, '0') + 'h' + String(endMin % 60).padStart(2, '0');
      opts += `<option value="${h}">${hoursLabel(h)} (jusqu’à ${end})</option>`;
    }
    sel.innerHTML = opts;
    sel.disabled = false;
    st.hours = C.minHours;
    updateSteps();
    quote();
  }
  $('#duration').addEventListener('change', e => { st.hours = parseFloat(e.target.value); quote(); });

  function slotReady() { return st.mode && st.date && (st.mode === 'day' || (st.start && st.hours)); }
  function updateSteps() {
    const ok = !!slotReady();
    ['#step3', '#step4'].forEach(s => $(s).classList.toggle('locked', !ok));
  }

  /* ---------- Étapes 3-4 : options & coordonnées ---------- */
  const asso = $('#asso');
  if (asso) asso.addEventListener('change', () => { $('#assoWrap').hidden = !asso.checked; quote(); });
  form.addEventListener('change', e => { if (e.target.name === 'options' || e.target.id === 'guests') quote(); });
  $('#guests').addEventListener('input', () => quote(400));

  function payload() {
    const fd = new FormData(form);
    return {
      mode: st.mode, date: st.date, days: st.days, start: st.start || '', hours: st.hours || 0,
      guests: parseInt(fd.get('guests'), 10) || 0,
      options: fd.getAll('options').map(Number),
      asso: !!(asso && asso.checked),
      promo: st.promo,
    };
  }

  /* ---------- Récapitulatif (prix calculé par le serveur) ---------- */
  let qTimer = null, qSeq = 0;
  function quote(delay = 120) {
    clearTimeout(qTimer);
    qTimer = setTimeout(doQuote, delay);
  }
  async function doQuote() {
    const lines = $('#sumLines');
    if (!slotReady()) {
      $('#sumSlot').textContent = !st.mode ? 'Choisissez une formule et une date.' : (!st.date ? 'Choisissez une date dans le calendrier.' : 'Choisissez votre heure d’arrivée.');
      lines.innerHTML = ''; $('#sumTotal').textContent = '—'; $('#sumVat').textContent = '';
      setTotal(null); return;
    }
    const p = payload();
    const preview = p.guests < 1;
    if (preview) p.guests = 1;
    const seq = ++qSeq;
    let j;
    try {
      const r = await fetch('api.php?a=quote', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p) });
      j = await r.json();
    } catch (e) { return; }
    if (seq !== qSeq) return; // réponse périmée
    const d = new Date(st.date + 'T12:00:00');
    const e = new Date(d.getTime()); e.setDate(e.getDate() + st.days);
    $('#sumSlot').textContent = st.mode === 'day'
      ? `${24 * st.days} h — du ${daysFr[isoDow(d) - 1]} ${d.getDate()} ${monthsFr[d.getMonth()]} ${C.dayArrive} au ${daysFr[isoDow(e) - 1]} ${e.getDate()} ${monthsFr[e.getMonth()]} ${C.dayStart}`
      : `${daysFr[isoDow(d) - 1]} ${d.getDate()} ${monthsFr[d.getMonth()]} — ${st.start.replace(':', 'h')}, ${hoursLabel(st.hours)}`;
    lines.innerHTML = (j.lines || []).map((l, i) => `<li><span>${i === 0 ? (/tarif « /.test(l.label) ? 'Location (' + esc(l.label.split('tarif « ')[1].replace(' »', '')) + ')' : 'Location') : esc(l.label)}</span><span>${eur.format(l.ttc)}</span></li>`).join('')
      + (j.discount_ttc > 0 ? `<li><span>Remise association</span><span>− ${eur.format(j.discount_ttc)}</span></li>` : '')
      + (j.promo_ttc > 0 ? `<li><span>${esc(j.promo_label)}</span><span>− ${eur.format(j.promo_ttc)}</span></li>` : '');
    const msg = $('#promoMsg');
    if (st.promo) {
      msg.style.color = j.code_error ? 'var(--err)' : 'var(--ok)';
      msg.textContent = j.code_error ? j.code_error : (j.gift_amount > 0 ? `Bon cadeau appliqué : ${eur.format(j.gift_amount)} déduits du montant à payer.` : 'Code appliqué ✓');
    } else { msg.textContent = ''; }
    const due = typeof j.amount_due === 'number' ? j.amount_due : j.total_ttc;
    $('#dueRow').hidden = !(j.gift_amount > 0);
    $('#sumDue').textContent = eur.format(due || 0);
    renderPayPlan(j.acompte, due);
    $$('.pay .btn').forEach((b, i) => {
      if (!b.dataset.label) b.dataset.label = b.innerHTML;
      const giftOnly = j.gift_amount > 0 && due <= 0;
      b.hidden = giftOnly && i > 0;
      b.innerHTML = giftOnly && i === 0 ? '🎁 Confirmer (réglé par bon cadeau)' : b.dataset.label;
    });
    $('#sumTotal').textContent = eur.format(j.total_ttc || 0);
    $('#sumVat').textContent = j.total_ttc ? `dont TVA ${eur.format(j.vat)} — total HT ${eur.format(j.total_ht)}` + (preview ? ' (hors options par personne)' : '') : '';
    const errs = (j.errors || []).filter(e => !(preview && /personnes/.test(e)) && e !== j.code_error);
    showErrors(errs);
    st.quoteOk = !!j.ok || (preview && !errs.length);
    setTotal(j.total_ttc || null, due);
    if (st.acompte && j.total_ttc) payLabels(due);
    if (errs.some(e => /plus disponible|avance/.test(e))) { delete cache[st.month]; renderCal(true); }
  }
  /* ---------- Acompte : imposé, au choix, ou totalité ---------- */
  function renderPayPlan(a, due) {
    const box = $('#payPlan'), note = $('#secureNote');
    st.acompte = a || null;
    if (!a || !(due > 0)) {
      box.hidden = true; box.innerHTML = ''; st.pay = 'total';
      $('#barLabel').textContent = 'Total TTC'; $('#payNow').hidden = true;
      if (note) note.textContent = 'Le montant total est réglé à la réservation.';
      return;
    }
    if (a.required) st.pay = 'acompte';
    else if (st.pay !== 'acompte') st.pay = 'total';
    const pct = String(a.percent).replace('.', ',');
    box.hidden = false;
    box.innerHTML = a.required
      ? '<div class="pp-info"><strong>Acompte de ' + pct + ' % à la réservation : ' + eur.format(a.amount) + '</strong><br>Solde de ' + eur.format(a.balance) + ' à régler avant le ' + esc(a.due) + ' (lien envoyé par e-mail).</div>'
      : '<strong class="pp-title">Comment souhaitez-vous payer ?</strong>'
        + '<label class="pp-opt"><input type="radio" name="pay" value="total"' + (st.pay === 'total' ? ' checked' : '') + '> <span><strong>Totalité maintenant</strong> — ' + eur.format(due) + '</span></label>'
        + '<label class="pp-opt"><input type="radio" name="pay" value="acompte"' + (st.pay === 'acompte' ? ' checked' : '') + '> <span><strong>Acompte de ' + pct + ' %</strong> — ' + eur.format(a.amount) + ' maintenant, puis ' + eur.format(a.balance) + ' avant le ' + esc(a.due) + '</span></label>';
    $$('input[name="pay"]', box).forEach(r => r.addEventListener('change', () => { st.pay = r.value; payLabels(due); }));
    if (note) note.textContent = 'Acompte possible : le solde se règle en ligne avant l’événement, depuis votre espace client.';
    payLabels(due);
  }
  function payLabels(due) {
    const now = st.pay === 'acompte' && st.acompte ? st.acompte.amount : due;
    $('#barTotal').textContent = now ? eur.format(now) : '—';
    $('#barLabel').textContent = st.acompte && st.pay === 'acompte' ? 'Acompte aujourd’hui' : 'Total TTC';
    const lbl = $('#payNow');
    if (lbl) { lbl.hidden = !(st.acompte && st.pay === 'acompte'); lbl.querySelector('strong').textContent = eur.format(now || 0); }
  }

  function setTotal(t, due) {
    st.total = t;
    $$('.pay .btn').forEach(b => b.disabled = !t || !st.quoteOk);
    const dv = $('#devisBtn'); if (dv && dv.textContent.indexOf('…') < 0) dv.disabled = !t || !st.quoteOk;
    $('#barTotal').textContent = t ? eur.format(typeof due === 'number' ? due : t) : '—';
    $('#mobileBar').hidden = !t;
    document.body.classList.toggle('has-bar', !!t);
  }
  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function escAttr(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function showErrors(list) {
    $('#errors').innerHTML = list.length ? `<div class="alert alert-err" role="alert" style="margin-top:14px">${list.length > 1 ? '<ul>' + list.map(e => `<li>${esc(e)}</li>`).join('') + '</ul>' : esc(list[0])}</div>` : '';
  }

  // La barre mobile se cache quand le récapitulatif est visible
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(es => es.forEach(en => { if (st.total) $('#mobileBar').hidden = en.isIntersecting; }), { threshold: 0.15 }).observe($('#summary'));
  }

  /* ---------- Code promo / bon cadeau ---------- */
  const applyPromo = () => { st.promo = $('#promoInput').value.trim().toUpperCase(); quote(0); };
  $('#promoApply').addEventListener('click', applyPromo);
  $('#promoInput').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyPromo(); } });

  /* ---------- Paiement ---------- */
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.submitter;
    if (!btn || !btn.dataset.provider) return;
    const missing = $$('#step3 [required], #step4 [required]').filter(i => !i.checkValidity());
    if (asso && asso.checked && !form.asso_name.value.trim()) missing.push(form.asso_name);
    if (missing.length) {
      showErrors(['Complétez les champs obligatoires : ' + missing.map(i => i.closest('label').querySelector('span').childNodes[0].textContent.trim()).join(', ') + '.']);
      missing[0].focus();
      return;
    }
    if (!$('#cgv').checked) { showErrors(['Vous devez accepter les conditions de location.']); $('#cgv').focus(); return; }
    const fd = new FormData(form);
    const data = Object.assign(payload(), {
      event_type: fd.get('event_type'), first_name: fd.get('first_name'), last_name: fd.get('last_name'), email: fd.get('email'),
      phone: fd.get('phone'), company: fd.get('company'), address: fd.get('address'), zip: fd.get('zip'), city: fd.get('city'),
      message: fd.get('message'), asso_name: fd.get('asso_name') || '', website: fd.get('website'), cgv: true,
      provider: btn.dataset.provider, expected_total: st.total, pay: st.pay || 'total',
      devis: C.prefill ? C.prefill.number : '', devis_t: C.prefill ? C.prefill.token : '',
    });
    const label = btn.innerHTML;
    $$('.pay .btn').forEach(b => b.disabled = true);
    btn.textContent = 'Redirection vers le paiement…';
    try {
      const r = await fetch('api.php?a=book', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      const j = await r.json();
      if (j.redirect) { window.location.href = j.redirect; return; }
      showErrors(j.errors || [j.error || 'Une erreur est survenue.']);
      if (r.status === 409) { delete cache[st.month]; renderCal(true); quote(0); }
    } catch (err) {
      showErrors(['Connexion impossible. Vérifiez votre connexion internet et réessayez.']);
    }
    btn.innerHTML = label;
    $$('.pay .btn').forEach(b => b.disabled = !st.quoteOk);
  });

  /* ---------- Devis établi par le client ---------- */
  const devisBtn = $('#devisBtn');
  if (devisBtn) devisBtn.addEventListener('click', async () => {
    const fd = new FormData(form);
    const need = [['event_type', 'le type d’événement'], ['guests', 'le nombre de personnes'], ['first_name', 'votre prénom'], ['last_name', 'votre nom'], ['email', 'votre e-mail']]
      .filter(([k]) => !String(fd.get(k) || '').trim());
    if (need.length) {
      showErrors(['Pour recevoir le devis, indiquez ' + need.map(n => n[1]).join(', ') + '.']);
      const el = form.querySelector('[name="' + need[0][0] + '"]'); if (el) el.focus();
      return;
    }
    const data = Object.assign(payload(), {
      event_type: fd.get('event_type'), first_name: fd.get('first_name'), last_name: fd.get('last_name'), email: fd.get('email'),
      phone: fd.get('phone'), company: fd.get('company'), address: fd.get('address'), zip: fd.get('zip'), city: fd.get('city'),
      message: fd.get('message'), asso_name: fd.get('asso_name') || '', website: fd.get('website'),
    });
    const label = devisBtn.innerHTML; devisBtn.disabled = true; devisBtn.textContent = 'Préparation du devis…';
    try {
      const r = await fetch('api.php?a=devis', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      const j = await r.json();
      if (j.redirect) { window.location.href = j.redirect; return; }
      showErrors(j.errors || [j.error || 'Le devis n’a pas pu être établi.']);
    } catch (e) { showErrors(['Connexion impossible. Réessayez.']); }
    devisBtn.innerHTML = label; devisBtn.disabled = !st.quoteOk;
  });

  /* ---------- Réservation à partir d'un devis : tout est prérempli ---------- */
  (async function () {
    const P = C.prefill;
    if (!P) return;
    const wait = async (sel, n = 40) => { for (let i = 0; i < n; i++) { const el = $(sel); if (el) return el; await new Promise(r => setTimeout(r, 150)); } return null; };
    Object.entries(P.fields).forEach(([k, v]) => { const el = form.querySelector('[name="' + k + '"]'); if (el && v) el.value = v; });
    $('#guests').value = P.guests || '';
    $$('input[name="options"]').forEach(c => { c.checked = P.options.includes(+c.value); });
    if (asso && P.asso) { asso.checked = true; $('#assoWrap').hidden = false; }
    if (P.promo) { $('#promoInput').value = P.promo; st.promo = P.promo; }
    st.month = P.date.slice(0, 7);
    const f = $('.formula[data-mode="' + P.mode + '"]'); if (!f) return;
    f.click();
    const day = await wait('#cal .day[data-date="' + P.date + '"]');
    if (!day || day.disabled) { showErrors(['La date du devis n’est plus disponible : choisissez-en une autre, les autres éléments sont conservés.']); quote(); return; }
    await selectDate(P.date);
    if (P.mode === 'day') {
      const chip = $('#daysPick .chip[data-days="' + P.days + '"]'); if (chip) chip.click();
    } else if (P.start) {
      const chip = await wait('#startChips .chip[data-start="' + P.start + '"]', 10);
      if (chip) { chip.click(); const sel = $('#duration'); if ([...sel.options].some(o => +o.value === P.hours)) { sel.value = String(P.hours); st.hours = P.hours; } }
    }
    updateSteps(); quote(0);
    day.scrollIntoView({ behavior: 'smooth', block: 'center' });
  })();

  // Retour sur l'onglet après un moment : le calendrier est relu (sans perdre la sélection si elle reste possible)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && st.mode && Date.now() - (cacheAt[st.month] || 0) >= 60000) {
      renderCal(true).then(() => {
        if (!st.date) return;
        const info = (cache[st.month] || {})[st.date];
        const ok = info && (st.mode === 'day' ? info.days >= st.days : (!st.start || (info.hours || {})[st.start] >= (st.hours || C.minHours)));
        if (!ok) { st.date = st.start = st.hours = null; $('#slots').hidden = true; updateSteps(); quote(); showErrors(['Le créneau choisi vient d’être réservé : choisissez-en un autre.']); }
        else $$('.day').forEach(b => b.classList.toggle('sel', b.dataset.date === st.date));
      });
    }
  });

  /* ---------- Lien direct depuis l'accueil (« prochaines dates libres ») : reservation?date=AAAA-MM-JJ ---------- */
  (async function () {
    const d = new URLSearchParams(location.search).get('date') || '';
    const f = $('.formula[data-mode="day"]');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d) || !f || d.slice(0, 7) < C.today || d.slice(0, 7) > maxMonth) return;
    st.month = d.slice(0, 7);
    f.click();
    for (let i = 0; i < 40; i++) {
      const b = $('#cal .day[data-date="' + d + '"]');
      if (b) {
        if (!b.disabled) { selectDate(d); b.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return;
      }
      await new Promise(r => setTimeout(r, 150));
    }
  })();
})();
