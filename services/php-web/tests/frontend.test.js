// Front-end unit tests (Node + assert)
const assert = require('assert');
const {
  toNumber,
  parseAstroEvents,
  parseAstroPositionsFallback,
  guessInstruments,
  filterByInstrument,
} = require('../resources/js/astro_utils');

let passed = 0;
function test(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`✔ ${name}`);
  } catch (e) {
    console.error(`✖ ${name}`);
    throw e;
  }
}

// toNumber
test('toNumber parses dot/comma and clamps ranges', () => {
  assert.strictEqual(toNumber('10.5', -90, 90), 10.5);
  assert.strictEqual(toNumber('10,5', -90, 90), 10.5);
  assert.strictEqual(toNumber('abc', -90, 90), null);
  assert.strictEqual(toNumber('100', -90, 90), null);
});

// Astro events parsing
test('parseAstroEvents extracts body, type, time, extra', () => {
  const data = {
    rows: [
      {
        body: { id: 'sun', name: 'Sun' },
        events: [{ type: 'rise', date: '2025-01-01T00:00:00Z', extraInfo: { magnitude: -26.7 } }],
      },
    ],
  };
  const flat = parseAstroEvents(data);
  assert.strictEqual(flat.length, 1);
  assert.strictEqual(flat[0].name, 'Sun');
  assert.strictEqual(flat[0].type, 'rise');
  assert.strictEqual(flat[0].when, '2025-01-01T00:00:00Z');
  assert.ok(String(flat[0].extra).includes('-26.7'));
});

test('parseAstroEvents handles empty rows', () => {
  const flat = parseAstroEvents({ rows: [] });
  assert.deepStrictEqual(flat, []);
});

test('parseAstroEvents tolerates row without events', () => {
  const flat = parseAstroEvents({ rows: [{ body: { id: 'sun' } }] });
  assert.deepStrictEqual(flat, []);
});

// Astro positions fallback
test('parseAstroPositionsFallback formats alt/az/mag/dist', () => {
  const data = {
    positions_rows: [
      {
        body: { name: 'Moon' },
        positions: [
          {
            date: '2025-01-01T00:00:00Z',
            position: { horizontal: { altitude: { degrees: '10.0' }, azimuth: { degrees: '20.0' } } },
            extraInfo: { magnitude: -12 },
            distance: { fromEarth: { km: '384400' } },
          },
        ],
      },
    ],
  };
  const flat = parseAstroPositionsFallback(data);
  assert.strictEqual(flat.length, 1);
  assert.strictEqual(flat[0].type, 'position');
  assert.ok(flat[0].extra.includes('alt 10.0'));
  assert.ok(flat[0].extra.includes('az 20.0'));
  assert.ok(flat[0].extra.includes('mag -12'));
  assert.ok(flat[0].extra.includes('dist 384400'));
});

test('parseAstroPositionsFallback handles empty positions', () => {
  const flat = parseAstroPositionsFallback({ positions_rows: [] });
  assert.deepStrictEqual(flat, []);
});

// JWST instrument guessing/filtering
test('guessInstruments detects NIRCam from instruments/suffix', () => {
  const it = { details: { instruments: [{ instrument: 'NIRCam' }] }, suffix: '_nrca1' };
  const inst = guessInstruments(it);
  assert.ok(inst.includes('NIRCAM'));
});

test('filterByInstrument keeps only requested instrument', () => {
  const items = [
    { id: 1, details: { instruments: [{ instrument: 'FGS' }] } },
    { id: 2, details: { instruments: [{ instrument: 'MIRI' }] } },
  ];
  const onlyMiri = filterByInstrument(items, 'MIRI');
  assert.strictEqual(onlyMiri.length, 1);
  assert.strictEqual(onlyMiri[0].id, 2);
});

test('filterByInstrument drops unknown instrument items when filter set', () => {
  const items = [{ id: 3 }]; // unknown instrument
  const filtered = filterByInstrument(items, 'FGS');
  assert.strictEqual(filtered.length, 0);
});

test('filterByInstrument with empty filter keeps all', () => {
  const items = [{ id: 1 }, { id: 2 }];
  const filtered = filterByInstrument(items, '');
  assert.strictEqual(filtered.length, 2);
});

test('toNumber respects min bound', () => {
  assert.strictEqual(toNumber('-100', -90, 90), null);
});

console.log(`Frontend unit tests passed (${passed} tests).`);

