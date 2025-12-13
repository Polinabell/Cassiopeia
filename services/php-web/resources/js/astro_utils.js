function toNumber(val, min, max) {
  const num = parseFloat(String(val).replace(',', '.'));
  if (!Number.isFinite(num)) return null;
  if (typeof min === 'number' && num < min) return null;
  if (typeof max === 'number' && num > max) return null;
  return num;
}

function parseAstroEvents(data) {
  const rows = (data && data.rows) || [];
  const flat = [];
  rows.forEach(row => {
    const bodyName = (row.body && (row.body.name || row.body.id)) || '—';
    (row.events || []).forEach(ev => {
      const type = ev.type || ev.event_type || ev.category || ev.kind || '';
      const when =
        ev.date ||
        ev.time ||
        (ev.peak && ev.peak.date) ||
        (ev.eventHighlights && ev.eventHighlights.peak && ev.eventHighlights.peak.date) ||
        ev.rise ||
        ev.set ||
        '';
      const extra =
        (ev.extraInfo && (ev.extraInfo.magnitude || (ev.extraInfo.phase && ev.extraInfo.phase.string))) ||
        (ev.eventHighlights &&
          ev.eventHighlights.peak &&
          (ev.eventHighlights.peak.altitude?.string || ev.eventHighlights.peak.altitude?.degrees)) ||
        '';
      flat.push({ name: bodyName, type, when, extra });
    });
  });
  return flat;
}

function parseAstroPositionsFallback(data) {
  const flat = [];
  const rows = (data && data.positions_rows) || [];
  rows.forEach(row => {
    const bodyName = (row.body && (row.body.name || row.body.id)) || '—';
    (row.positions || []).forEach(p => {
      const when = p.date || '';
      const alt =
        (p.position &&
          p.position.horizontal &&
          (p.position.horizontal.altitude?.string || p.position.horizontal.altitude?.degrees)) ||
        '';
      const az =
        (p.position &&
          p.position.horizontal &&
          (p.position.horizontal.azimuth?.string || p.position.horizontal.azimuth?.degrees)) ||
        '';
      const mag = (p.extraInfo && p.extraInfo.magnitude) || '';
      const dist =
        (p.distance && p.distance.fromEarth && (p.distance.fromEarth.km || p.distance.fromEarth.au)) || '';
      const extra = `alt ${alt || '—'}, az ${az || '—'}, mag ${mag || '—'}, dist ${dist || '—'}`;
      flat.push({ name: bodyName, type: 'position', when, extra });
    });
  });
  return flat;
}

// Minimal JWST instrument filter mirror.
function guessInstruments(it) {
  const cand = [];
  (it.details?.instruments || []).forEach(I => {
    if (I && typeof I.instrument === 'string' && I.instrument.trim()) {
      cand.push(I.instrument.toUpperCase());
    }
  });
  ['instrument', 'inst', 'camera', 'detector'].forEach(k => {
    if (typeof it[k] === 'string' && it[k].trim()) cand.push(it[k].toUpperCase());
  });
  const fields = [
    it.details?.suffix,
    it.suffix,
    it.url,
    it.location,
    it.thumbnail,
    it.id,
    it.observation_id,
  ].filter(Boolean);
  fields.forEach(f => {
    const s = String(f).toLowerCase();
    if (s.includes('nircam') || s.includes('_nrc')) cand.push('NIRCAM');
    if (s.includes('miri')) cand.push('MIRI');
    if (s.includes('niriss')) cand.push('NIRISS');
    if (s.includes('nirspec') || s.includes('nrs')) cand.push('NIRSPEC');
    if (s.includes('fgs')) cand.push('FGS');
  });
  return Array.from(new Set(cand));
}

function filterByInstrument(items, instF) {
  const inst = instF ? instF.toUpperCase() : '';
  return items.filter(it => {
    const list = guessInstruments(it);
    if (!inst) return true;
    if (list.length === 0) return false;
    return list.includes(inst);
  });
}

module.exports = {
  toNumber,
  parseAstroEvents,
  parseAstroPositionsFallback,
  guessInstruments,
  filterByInstrument,
};


