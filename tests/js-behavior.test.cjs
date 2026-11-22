/* Comportamento dei moduli frontend corretti: eseguiti in un DOM minimale, senza browser. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const test = require('node:test');

const ROOT = path.join(__dirname, '..');

class ClassList {
  constructor() { this.items = new Set(); }
  add(...names) { names.forEach(name => this.items.add(name)); }
  remove(...names) { names.forEach(name => this.items.delete(name)); }
  toggle(name, force) {
    const on = force === undefined ? !this.items.has(name) : !!force;
    if (on) { this.items.add(name); } else { this.items.delete(name); }
    return on;
  }
  contains(name) { return this.items.has(name); }
}

class Element {
  constructor(tag = 'div') {
    this.tagName = tag.toUpperCase();
    this.children = [];
    this.events = new Map();
    this.dataset = {};
    this.attributes = {};
    this.classList = new ClassList();
    this.style = { setProperty() {}, removeProperty() {} };
    this.hidden = false; this.value = ''; this.textContent = '';
    this.checked = false; this.disabled = false;
    this.scrollTop = 0; this.scrollHeight = 0; this.clientHeight = 0;
    this._query = null; this._html = '';
  }
  addEventListener(type, fn) {
    if (!this.events.has(type)) { this.events.set(type, []); }
    this.events.get(type).push(fn);
  }
  removeEventListener() {}
  dispatchEvent(event) {
    (this.events.get(event.type) || []).slice().forEach(fn => fn(event));
    return true;
  }
  emit(type, extra = {}) {
    return this.dispatchEvent({ type, target: this, currentTarget: this, preventDefault() {}, stopPropagation() {}, ...extra });
  }
  append(...nodes) { this.children.push(...nodes); }
  appendChild(node) { this.children.push(node); return node; }
  replaceChildren(...nodes) { this.children = nodes; }
  remove() {}
  setAttribute(key, value) { this.attributes[key] = String(value); }
  getAttribute(key) { return key in this.attributes ? this.attributes[key] : null; }
  removeAttribute(key) { delete this.attributes[key]; }
  hasAttribute(key) { return key in this.attributes; }
  querySelector(selector) { return this._query ? this._query(selector) : null; }
  querySelectorAll() { return []; }
  closest() { return null; }
  focus() {} blur() {} select() {} setSelectionRange() {} scrollIntoView() {}
  get firstElementChild() { return this.children[0] || null; }
  get innerHTML() { return this._html; }
  set innerHTML(value) { this._html = value; }
  get elements() { return { namedItem: () => new Element('input') }; }
  get options() { return []; }
}

function environment(ids = {}, extra = {}) {
  const documentEvents = new Map();
  const windowEvents = new Map();
  const document = {
    readyState: 'complete', hidden: false, cookie: '',
    body: new Element('body'), documentElement: new Element('html'),
    getElementById: id => ids[id] || null,
    querySelector: selector => {
      if (!ids[selector]) { ids[selector] = new Element('div'); }
      return ids[selector];
    },
    querySelectorAll: () => [],
    createElement: tag => new Element(tag),
    createDocumentFragment: () => new Element('fragment'),
    createTextNode: text => ({ textContent: text }),
    addEventListener(type, fn) {
      if (!documentEvents.has(type)) { documentEvents.set(type, []); }
      documentEvents.get(type).push(fn);
    },
    removeEventListener() {},
    dispatchEvent(event) { (documentEvents.get(event.type) || []).slice().forEach(fn => fn(event)); return true; }
  };
  const win = {
    document, location: { href: 'https://example.test/page', search: '', reload() {} },
    matchMedia: () => ({ matches: false }), navigator: { onLine: true }, history: { replaceState() {} },
    setTimeout: () => 0, clearTimeout() {}, setInterval: () => 0, clearInterval() {},
    queueMicrotask: fn => fn(),
    crypto: { randomUUID: () => 'uuid-test' },
    fetch: async () => ({
      ok: true, status: 200,
      json: async () => ({
        success: true, messages: [], relations: {}, typing: [], quest: null, lastId: 0, more: false,
        catalogo: [], catalogoPers: [], catalogoPersOrg: [], riparazioni: [], tipi: [], taglie: [],
        organizzazioni: [], materie: [], groups: [], rules: [], images: [], audiences: {}, npcs: [], enabled: false
      }),
      headers: { get: () => null }, text: async () => ''
    }),
    addEventListener(type, fn) {
      if (!windowEvents.has(type)) { windowEvents.set(type, []); }
      windowEvents.get(type).push(fn);
    },
    removeEventListener() {},
    dispatchEvent(event) { (windowEvents.get(event.type) || []).slice().forEach(fn => fn(event)); return true; },
    DOMParser: class { parseFromString() { const body = new Element('body'); body.childNodes = []; return { body }; } },
    importNode: node => node,
    AbortController: class { constructor() { this.signal = {}; } abort() {} },
    MutationObserver: class { observe() {} disconnect() {} },
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init && init.detail; } },
    Event: class { constructor(type) { this.type = type; } },
    AbortSignal: { timeout: () => ({}) },
    URL, URLSearchParams, console, JSON, Math, Number, String, Object, Array, Set, Map, Promise, Date, RegExp, Error, Boolean, Symbol
  };
  win.window = win; win.self = win; win.top = win; win.parent = win;
  Object.assign(win, extra);
  const sandbox = { ...win, window: win, document, console };
  return { win, document, sandbox, documentEvents, windowEvents, ids };
}

function load(relativeFile, env, tail = '') {
  const absoluteFile = path.join(ROOT, relativeFile);
  const code = fs.readFileSync(absoluteFile, 'utf8');
  for (const key of Object.keys(env.sandbox)) { globalThis[key] = env.sandbox[key]; }
  const Module = require('node:module');
  const mod = new Module(absoluteFile, module);
  mod.filename = absoluteFile;
  mod.paths = Module._nodeModulePaths(path.dirname(absoluteFile));
  mod._compile('(function(){\n' + code + '\n' + tail + '\n})();', absoluteFile);
  return globalThis;
}

const flush = () => new Promise(resolve => setImmediate(resolve));

test('bacheche: stripHtml rimuove i tag senza regex super-lineari', () => {
  const env = environment();
  load('js/bacheche.js', env, 'globalThis.__stripHtml = stripHtml;');
  assert.equal(globalThis.__stripHtml('<b>Ciao</b> <i>mondo</i>'), 'Ciao mondo');
  assert.equal(globalThis.__stripHtml('nessun tag'), 'nessun tag');
  assert.equal(globalThis.__stripHtml('<a href="x">A</a><br>B'), 'AB');
});

test('catalogo: etichette di disponibilita e fatti delle regole', () => {
  const env = environment({ lista: new Element('div'), corpo: new Element('div'), 'catalogue-count': new Element('span') });
  load('js/corporazioni/catalogo.js', env, 'globalThis.__catalogo = Catalogo;');
  const catalogo = globalThis.__catalogo;
  assert.equal(typeof catalogo.currentView, 'function');
  assert.equal(catalogo.currentView(), 'Catalogo');
  catalogo.Mostra(0);
  catalogo.CaricaCatalogo();
});

test('chat HTTP: inizializzazione con DOM minimale', async () => {
  const ids = {};
  for (const id of ['chatbox', 'chat-connection-status', 'ai-chat-status', 'recordBtn', 'moodSelect', 'comodo', 'messageType', 'sendBtn']) {
    ids[id] = new Element(id === 'recordBtn' || id === 'moodSelect' || id === 'comodo' || id === 'messageType' || id === 'sendBtn' ? 'button' : 'div');
  }
  ids.comodo.value = 'ciao';
  const env = environment(ids, { chatRoom: 'generale', chatToken: 'token', myPgId: 7 });
  load('chat/js/chat_http.js', env);
  await flush();
  assert.equal(Array.isArray(ids.chatbox.children), true);
  ids.comodo.emit('input');
  ids.moodSelect.emit('change', { target: { value: '1' } });
  await flush();
});

test('chat scene: inizializzazione e chiusura pannelli', () => {
  const ids = {};
  for (const id of ['chat-drawer', 'chat-drawer-backdrop', 'chat-drawer-title', 'comodo', 'chat-mobile-menu', 'chat-expand']) {
    ids[id] = new Element('div');
  }
  ids['chat-drawer-backdrop'].hidden = true;
  const env = environment(ids, { chatRoom: 'generale', chatToken: 'token' });
  assert.equal(env.document.getElementById('chat-drawer-backdrop'), ids['chat-drawer-backdrop']);
  assert.equal(env.document.getElementById('comodo'), ids.comodo);
  load('chat/js/chat_scene.js', env);
  assert.equal(typeof env.win.OpenSessionManager, 'function');
  assert.equal(typeof env.win.CloseSessionManager, 'function');
});

test('privacy: esposizione API e commenti sulle preferenze', () => {
  const env = environment();
  const context = load('js/privacy.js', env);
  assert.equal(typeof env.win.alphanoPrivacy.open, 'function');
  assert.equal(typeof env.win.alphanoPrivacy.firstLogin, 'function');
  assert.equal(typeof env.win.alphanoPrivacy.externalMediaAllowed, 'function');
  assert.equal(env.win.alphanoPrivacy.externalMediaAllowed(), false);
});

test('discussioni: inizializzazione e cambio canale', async () => {
  const ids = {
    'menu-list': new Element('div'), 'panel-title': new Element('h2'), 'panel-container': new Element('div'),
    'btn-toggle-closed': new Element('button'), 'btn-create': new Element('button'), modal: new Element('div')
  };
  const label = new Element('span');
  ids.modal._query = () => label;
  const forum = {
    esc: value => String(value ?? ''), button: () => '<button></button>',
    notice: () => '<p></p>', table: () => '<table></table>', author: () => '<span></span>',
    date: () => '', newBadge: () => '', post: () => '<article></article>',
    editor() {}, markRead() {},
    config: { tipo: 'general', channels: { general: 'Generale' }, csrfToken: 'token' }
  };
  const env = environment(ids, { alphanoForum: forum });
  load('js/discussioni.js', env);
  env.document.dispatchEvent({ type: 'DOMContentLoaded' });
  await flush();
  const channel = new Element('button'); channel.dataset.tipo = 'general';
  ids['menu-list'].emit('click', { target: { closest: () => channel } });
  await flush();
  assert.equal(ids['panel-title'].textContent, 'Generale');
});

test('manuali: inizializzazione con configurazione pubblica', async () => {
  const config = new Element('script');
  config.textContent = JSON.stringify({ public: true, apiUrl: '/api/manuali.php', baseUrl: '/regole/' });
  const ids = {
    'manuals-config': config, 'manual-library': new Element('div'), 'manual-filters': new Element('form'),
    'manual-index': new Element('div'), 'manual-search': new Element('input'), 'manual-group': new Element('select'),
    'manual-audience': new Element('select'), 'manual-detail': new Element('article'), 'toast': new Element('div'),
    'manual-status': new Element('p'), 'modal': new Element('div')
  };
  const env = environment(ids);
  load('js/manuali.js', env);
  env.document.dispatchEvent({ type: 'DOMContentLoaded' });
  await flush();
  ids['manual-filters'].emit('submit');
  await flush();
});
