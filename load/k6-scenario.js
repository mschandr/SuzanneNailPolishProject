// Realistic traffic for Nail Polish Collector. Run with k6 (https://k6.io): see load/README.md.
//
//   k6 run -e BASE=http://127.0.0.1:8081 -e VUS=1000 load/k6-scenario.js
//
// Each virtual user behaves like a person: about a third are signed in (through the real magic-link flow, using the
// single-use tokens the LoadTestSeeder writes), the rest are guests. They browse the Gallery, Catalog, Polish and
// Manicure pages with 2-6 seconds of thinking time between page views, and their "browser" then fetches the photos
// on each page, six at a time, exactly as a real browser would.
import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE || 'http://127.0.0.1:8081';
const VUS = parseInt(__ENV.VUS || '200');
const RAMP = __ENV.RAMP || '45s';
const HOLD = __ENV.HOLD || '90s';
const USERS_FILE = __ENV.USERS_FILE || '../storage/app/loadtest-users.json';

const users = new SharedArray('users', () => JSON.parse(open(USERS_FILE)));

const pageTime = new Trend('page_duration', true);      // HTML pages only
const photoTime = new Trend('photo_duration', true);    // thumbnails and full photos
const photoHit = new Rate('photo_cache_hit');           // served by nginx without PHP
const loginTime = new Trend('login_duration', true);
const loginOk = new Rate('login_success');
const pages = new Counter('page_views');

export const options = {
  scenarios: {
    visitors: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: RAMP, target: VUS },
        { duration: HOLD, target: VUS },
        { duration: '15s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  // What "the site holds" means: almost no errors, snappy pages, and photos that don't drag.
  thresholds: {
    http_req_failed: ['rate<0.01'],
    page_duration: ['p(95)<1500'],
    photo_duration: ['p(95)<500'],
    login_success: ['rate>0.99'],
  },
  discardResponseBodies: false,
  noConnectionReuse: false,
  userAgent: 'NailPolishLoadTest/1.0',
};

// Collect real URLs to visit once, before the test starts.
export function setup() {
  const manicures = new Set();
  const polishes = new Set();
  for (let page = 1; page <= 6; page++) {
    const html = http.get(`${BASE}/gallery?page=${page}${page % 2 ? '' : '&sort=loved'}`).body;
    for (const link of all(/href="(\/manicures\/[0-9a-f-]{36})"/, html)) manicures.add(link);
  }
  const brandLinks = all(/href="(\/catalog\/[a-z0-9-]+)"/, http.get(`${BASE}/catalog`).body);
  const brands = brandLinks.slice(0, 40);
  for (const b of brands) {
    for (const link of all(/href="(\/[a-z0-9-]+\/[0-9a-f-]{36}\/[a-z0-9-]+)"/, http.get(`${BASE}${b}`).body)) polishes.add(link);
  }
  const data = { manicures: Array.from(manicures), polishes: Array.from(polishes), brands };
  console.log(`setup found ${data.manicures.length} manicures, ${data.polishes.length} polishes, ${data.brands.length} brands`);
  return data;
}

const pick = (list) => list[Math.floor(Math.random() * list.length)];

// All matches of capture group 1 (or the whole match). k6's JS engine has no matchAll iterator helpers.
function all(pattern, text, group = 1) {
  const re = new RegExp(pattern.source, 'g');
  const found = [];
  let m;
  while ((m = re.exec(text)) !== null) found.push(m[group]);
  return found;
}

function page(path, name) {
  const res = http.get(`${BASE}${path}`, { tags: { name: `GET ${name}`, kind: 'page' } });
  pageTime.add(res.timings.duration);
  pages.add(1);
  check(res, { [`${name} 200`]: (r) => r.status === 200 });
  return res;
}

// What a browser does after receiving a page: fetch its photos, six at a time.
function loadPhotos(html, variant, limit) {
  const found = all(new RegExp(`(/photos/[0-9a-f-]{36}/${variant}(?:\\?v=\\d+)?)`), html);
  const urls = found.filter((u, i) => found.indexOf(u) === i).slice(0, limit);
  for (let i = 0; i < urls.length; i += 6) {
    const batch = urls.slice(i, i + 6).map((u) => ['GET', `${BASE}${u}`, null, { tags: { name: `GET /photos/${variant}`, kind: 'photo' } }]);
    for (const res of http.batch(batch)) {
      photoTime.add(res.timings.duration);
      photoHit.add(res.headers['X-Cache-Status'] === 'HIT');
      check(res, { 'photo 200': (r) => r.status === 200 });
    }
  }
}

function signIn(email, token) {
  const started = Date.now();
  const confirm = http.get(`${BASE}/login/${token}`, { tags: { name: 'GET /login/{token}', kind: 'login' } });
  const csrf = (confirm.body.match(/name="_token" value="([^"]+)"/) || [])[1];
  const res = http.post(`${BASE}/login/${token}`, { _token: csrf }, { tags: { name: 'POST /login/{token}', kind: 'login' } });
  loginTime.add(Date.now() - started);
  const ok = res.status === 200 && !res.url.includes('/login');
  loginOk.add(ok);
  return ok;
}

let signedIn = false;
let triedLogin = false;

export default function (data) {
  // The first 30% of virtual users (by id) sign in once; they stay signed in for the whole test.
  if (!triedLogin) {
    triedLogin = true;
    const idx = __VU - 1;
    if (idx < users.length && idx < VUS * 0.3) signedIn = signIn(users[idx].email, users[idx].token);
  }

  const roll = Math.random();
  if (roll < 0.30) {
    group('gallery', () => {
      const q = pick(['', '', '?sort=loved', `?page=${1 + Math.floor(Math.random() * 5)}`, `?brand=brand+${1 + Math.floor(Math.random() * 100)}`]);
      const res = page(`/gallery${q}`, '/gallery');
      loadPhotos(res.body, 'thumb', 24);
    });
  } else if (roll < 0.50) {
    group('polish', () => {
      const res = page(pick(data.polishes), '/{brand}/{id}/{shade}');
      loadPhotos(res.body, 'thumb', 12);
    });
  } else if (roll < 0.65) {
    group('catalog', () => {
      page(pick(data.brands), '/catalog/{brand}');
    });
  } else if (roll < 0.80) {
    group('manicure', () => {
      const res = page(pick(data.manicures), '/manicures/{id}');
      loadPhotos(res.body, 'full', 3);
    });
  } else if (signedIn && roll < 0.90) {
    group('my collection', () => {
      page(`/collection${Math.random() < 0.5 ? '?q=shade+' + Math.floor(Math.random() * 100) : ''}`, '/collection');
    });
  } else if (signedIn && roll < 0.95) {
    group('heart', () => {
      const m = pick(data.manicures);
      const res = page(m, '/manicures/{id}');
      const csrf = (res.body.match(/name="_token" value="([^"]+)"/) || [])[1];
      if (csrf) http.post(`${BASE}${m}/heart`, { _token: csrf }, { tags: { name: 'POST /manicures/{id}/heart', kind: 'page' } });
    });
  } else {
    group('catalog index', () => {
      page('/catalog', '/catalog');
    });
  }

  sleep(2 + Math.random() * 4); // thinking time
}

export function handleSummary(data) {
  const m = (name, stat) => (data.metrics[name] && data.metrics[name].values[stat] !== undefined ? data.metrics[name].values[stat] : NaN);
  const ms = (v) => (Number.isFinite(v) ? `${v.toFixed(0)}ms` : 'n/a');
  const lines = [
    '',
    `=== Nail Polish Collector load test: ${VUS} virtual users against ${BASE} ===`,
    `requests:        ${m('http_reqs', 'count')} total, ${m('http_reqs', 'rate').toFixed(0)}/s   failed: ${(m('http_req_failed', 'rate') * 100).toFixed(2)}%`,
    `page views:      ${m('page_views', 'count')}`,
    `HTML pages:      p50 ${ms(m('page_duration', 'med'))}  p95 ${ms(m('page_duration', 'p(95)'))}  p99 ${ms(m('page_duration', 'p(99)'))}  max ${ms(m('page_duration', 'max'))}`,
    `photos:          p50 ${ms(m('photo_duration', 'med'))}  p95 ${ms(m('photo_duration', 'p(95)'))}  p99 ${ms(m('photo_duration', 'p(99)'))}   served from nginx cache: ${(m('photo_cache_hit', 'rate') * 100).toFixed(1)}%`,
    `sign-in:         p95 ${ms(m('login_duration', 'p(95)'))}   success ${(m('login_success', 'rate') * 100).toFixed(1)}%`,
    `data received:   ${(m('data_received', 'count') / 1048576).toFixed(0)} MB`,
    '',
  ];
  const out = { stdout: lines.join('\n') };
  if (__ENV.SUMMARY_FILE) out[__ENV.SUMMARY_FILE] = JSON.stringify(data, null, 2);
  return out;
}
