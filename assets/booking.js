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

  const st = { mode: null, month: C.today, date: null, start: null, hours: null, total: null, quoteOk: false };
  const cache = {};
  const maxMonth = addMonths(C.today, C.maxMonths);

  /* ---------- Étape 1 : formule ---------- */
  $$('.formula').forEach(b => b.addEventListener('click', () => {
    st.mode = b.dataset.mode;
    $$('.formula').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
    st.date = st.start = st.hours = null;
    $('#step2').classList.remove('locked');
    $('#legendPartial').hidden = st.mode !== 'hour';
    renderCal();
    updateSteps();
    quote();
    if (window.innerWidth < 980) $('#step2').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  /* ---------- Étape 2 : calendrier ---------- */
  async function loadMonth(ym, force) {
    if (cache[ym] && !force) return cache[ym];
    const r = await fetch('api.php?a=month&m=' + ym, { headers: { Accept: 'application/json' } });
    const j = await r.json();
    if (!r.ok) throw new Error(j.error || 'Erreur');
    return (cache[ym] = j.days || {});
  }

  function dayState(info) {
    if (!info) return '';
    if (st.mode === 'day') return info.day ? 'free' : '';
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
      const price = st.mode === 'day' && cls ? `<small>${eurShort(C.dayPrices[dow])}</small>` : '';
      const label = `${daysFr[dow - 1]} ${d} ${monthsFr[m - 1]}${cls ? '' : ' (indisponible)'}`;
      html += `<button type="button" class="day ${cls} ${key === st.date ? 'sel' : ''}" data-date="${key}" ${cls ? '' : 'disabled'} aria-label="${label}" aria-pressed="${key === st.date}">${d}${price}</button>`;
    }
    cal.innerHTML = html;
    $$('.day[data-date]:not([disabled])', cal).forEach(b => b.addEventListener('click', () => selectDate(b.dataset.date)));
  }
  $('#calPrev').addEventListener('click', () => { st.month = addMonths(st.month, -1); renderCal(); });
  $('#calNext').addEventListener('click', () => { st.month = addMonths(st.month, 1); renderCal(); });

  function selectDate(key) {
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
      mode: st.mode, date: st.date, start: st.start || '', hours: st.hours || 0,
      guests: parseInt(fd.get('guests'), 10) || 0,
      options: fd.getAll('options').map(Number),
      asso: !!(asso && asso.checked),
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
    $('#sumSlot').textContent = st.mode === 'day'
      ? `24 h — du ${daysFr[isoDow(d) - 1]} ${d.getDate()} ${monthsFr[d.getMonth()]} ${C.dayStart} au lendemain ${C.dayStart}`
      : `${daysFr[isoDow(d) - 1]} ${d.getDate()} ${monthsFr[d.getMonth()]} — ${st.start.replace(':', 'h')}, ${hoursLabel(st.hours)}`;
    lines.innerHTML = (j.lines || []).map((l, i) => `<li><span>${i === 0 ? 'Location' : esc(l.label)}</span><span>${eur.format(l.ttc)}</span></li>`).join('')
      + (j.discount_ttc > 0 ? `<li><span>Remise association</span><span>− ${eur.format(j.discount_ttc)}</span></li>` : '');
    $('#sumTotal').textContent = eur.format(j.total_ttc || 0);
    $('#sumVat').textContent = j.total_ttc ? `dont TVA ${eur.format(j.vat)} — total HT ${eur.format(j.total_ht)}` + (preview ? ' (hors options par personne)' : '') : '';
    const errs = (j.errors || []).filter(e => !(preview && /personnes/.test(e)));
    showErrors(errs);
    st.quoteOk = !!j.ok || (preview && !errs.length);
    setTotal(j.total_ttc || null);
    if (errs.some(e => /plus disponible|avance/.test(e))) { delete cache[st.month]; renderCal(true); }
  }
  function setTotal(t) {
    st.total = t;
    $$('.pay .btn').forEach(b => b.disabled = !t || !st.quoteOk);
    $('#barTotal').textContent = t ? eur.format(t) : '—';
    $('#mobileBar').hidden = !t;
    document.body.classList.toggle('has-bar', !!t);
  }
  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function showErrors(list) {
    $('#errors').innerHTML = list.length ? `<div class="alert alert-err" role="alert" style="margin-top:14px">${list.length > 1 ? '<ul>' + list.map(e => `<li>${esc(e)}</li>`).join('') + '</ul>' : esc(list[0])}</div>` : '';
  }

  // La barre mobile se cache quand le récapitulatif est visible
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(es => es.forEach(en => { if (st.total) $('#mobileBar').hidden = en.isIntersecting; }), { threshold: 0.15 }).observe($('#summary'));
  }

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
      provider: btn.dataset.provider, expected_total: st.total,
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
})();
