/* Public catalogue - progressive enhancement only.
   Everything works with JavaScript switched off; this just smooths it out. */
(function () {
  'use strict';

  // --- Mobile navigation ---------------------------------------------------
  var navToggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // --- Mobile filter panel -------------------------------------------------
  var filterToggle = document.querySelector('.filter-toggle');
  var filters = document.getElementById('filters');
  if (filterToggle && filters) {
    filterToggle.addEventListener('click', function () {
      var open = filters.classList.toggle('is-open');
      filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // --- Sort dropdown -------------------------------------------------------
  // Each <option> value is a ready-made URL, so this is one line and the
  // select still degrades to a plain list without JS.
  var sort = document.querySelector('select[data-autonav]');
  if (sort) {
    sort.addEventListener('change', function () {
      if (this.value) { window.location.href = this.value; }
    });
  }

  // --- Availability checkboxes submit the filter form ----------------------
  var form = document.getElementById('filter-form');
  if (form) {
    form.querySelectorAll('input[name="availability[]"], select[name="brand"]')
      .forEach(function (el) {
        el.addEventListener('change', function () { form.submit(); });
      });
  }

  // --- Product gallery -----------------------------------------------------
  var mainImg = document.getElementById('gallery-main-img');
  var thumbs = document.querySelectorAll('.thumb');
  if (mainImg && thumbs.length) {
    thumbs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        mainImg.src = btn.getAttribute('data-full');
        mainImg.alt = btn.getAttribute('data-alt') || '';
        thumbs.forEach(function (t) { t.classList.remove('is-active'); });
        btn.classList.add('is-active');
      });
    });
  }

  // --- Shortlist -----------------------------------------------------------
  // The list lives in this browser: a buyer can build one over several visits
  // without an account, and nothing is written to the server until they press
  // send. Only ids are stored - names, images and availability are read back
  // from the database on the shortlist page, so a list built weeks ago still
  // shows current information rather than a stale cached copy.

  var KEY = 'ds_shortlist';

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      var ids = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(ids)) { return []; }
      // Anything that is not a positive integer is dropped rather than sent to
      // the server to be rejected there.
      return ids.map(Number).filter(function (n) {
        return Number.isInteger(n) && n > 0;
      });
    } catch (e) {
      // Private mode, a full quota or hand-edited storage all land here. A
      // broken shortlist should not take the whole page down with it.
      return [];
    }
  }

  function write(ids) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(ids));
    } catch (e) { /* nothing useful to do - the buttons still work in-page */ }
    paintCount(ids.length);
  }

  function paintCount(n) {
    document.querySelectorAll('[data-shortlist-count]').forEach(function (el) {
      el.textContent = n;
      // An empty badge is noise, so the counter is hidden at zero rather than
      // sitting there showing "0" on every page.
      el.hidden = n === 0;
    });
  }

  function paintButtons() {
    var ids = read();
    document.querySelectorAll('[data-shortlist]').forEach(function (btn) {
      var on = ids.indexOf(Number(btn.getAttribute('data-shortlist'))) !== -1;
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.classList.toggle('is-on', on);
    });
    paintCount(ids.length);
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-shortlist]') : null;
    if (!btn) { return; }
    ev.preventDefault();

    var id = Number(btn.getAttribute('data-shortlist'));
    if (!Number.isInteger(id) || id <= 0) { return; }

    var ids = read();
    var at = ids.indexOf(id);
    if (at === -1) { ids.push(id); } else { ids.splice(at, 1); }
    write(ids);
    paintButtons();
  });

  paintButtons();

  // The confirmation page carries this marker. Clearing here rather than at
  // submit time means a rejected enquiry comes back with the list intact.
  if (document.getElementById('clear-shortlist')) {
    write([]);
  }

  // --- Shortlist page ------------------------------------------------------
  var slForm = document.getElementById('shortlist-form');
  var slRows = document.getElementById('shortlist-rows');
  var slEmpty = document.getElementById('shortlist-empty');

  if (slForm && slRows && slEmpty) {
    (function () {
      var ids = read();

      function showEmpty() {
        slEmpty.hidden = false;
        slForm.hidden = true;
      }

      if (!ids.length) {
        showEmpty();
        return;
      }

      fetch(window.DS_SHORTLIST_URL + '?ids=' + ids.join(','), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (r) {
          if (!r.ok) { throw new Error('HTTP ' + r.status); }
          return r.json();
        })
        .then(function (data) {
          var items = (data && data.items) || [];
          if (!items.length) {
            // Every id resolved to nothing, so the products are gone. Forget
            // them rather than leaving a shortlist that can never be sent.
            write([]);
            showEmpty();
            return;
          }

          // Prune ids the server did not return - withdrawn products - so the
          // stored list matches what is actually on screen.
          var live = items.map(function (i) { return i.id; });
          if (live.length !== ids.length) { write(live); }

          slRows.innerHTML = '';
          items.forEach(function (item) { slRows.appendChild(row(item)); });

          slEmpty.hidden = true;
          slForm.hidden = false;
          count(items.length);
          slForm.addEventListener('submit', serialise);
        })
        .catch(function () {
          slRows.innerHTML =
            '<li class="sl-error">Your shortlist could not be loaded. ' +
            'Please reload the page.</li>';
          slEmpty.hidden = true;
          slForm.hidden = false;
        });

      function count(n) {
        var c = document.getElementById('sl-count');
        var p = document.getElementById('sl-plural');
        if (c) { c.textContent = n; }
        if (p) { p.textContent = n === 1 ? '' : 's'; }
        paintCount(n);
      }

      // Built with createElement and textContent rather than an HTML string:
      // a product name is data, and a name containing an apostrophe or an
      // angle bracket must not become markup here.
      function row(item) {
        var li = document.createElement('li');
        li.className = 'sl-row';
        li.setAttribute('data-id', item.id);

        var img = document.createElement('img');
        img.className = 'sl-thumb';
        img.src = item.image;
        img.alt = '';
        img.width = 72;
        img.height = 72;
        img.loading = 'lazy';
        li.appendChild(img);

        var main = document.createElement('div');
        main.className = 'sl-main';

        if (item.category) {
          var cat = document.createElement('p');
          cat.className = 'sl-cat';
          cat.textContent = item.origin
            ? item.category + ' · ' + item.origin
            : item.category;
          main.appendChild(cat);
        }

        var h = document.createElement('p');
        h.className = 'sl-name';
        var a = document.createElement('a');
        a.href = item.url;
        a.textContent = item.name;
        h.appendChild(a);
        main.appendChild(h);

        var stock = document.createElement('p');
        stock.className = 'sl-stock stock stock-' + item.stockClass;
        stock.textContent = item.stock;
        main.appendChild(stock);

        li.appendChild(main);

        var fields = document.createElement('div');
        fields.className = 'sl-fields';

        fields.appendChild(field(item.id, 'qty', 'Quantity',
          'e.g. 2 x 40ft, 500 kg'));
        fields.appendChild(field(item.id, 'note', 'Notes',
          'packing, grade, certification…'));

        li.appendChild(fields);

        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'sl-remove';
        rm.setAttribute('data-remove', item.id);
        rm.setAttribute('aria-label', 'Remove ' + item.name + ' from your shortlist');
        rm.textContent = '×';
        rm.addEventListener('click', function () {
          var list = read();
          var at = list.indexOf(item.id);
          if (at !== -1) { list.splice(at, 1); write(list); }
          li.remove();
          var left = slRows.children.length;
          count(left);
          if (!left) { showEmpty(); }
        });
        li.appendChild(rm);

        return li;
      }

      function field(id, name, labelText, placeholder) {
        var wrap = document.createElement('div');
        wrap.className = 'field field-sm';
        var lab = document.createElement('label');
        lab.htmlFor = 'sl-' + name + '-' + id;
        lab.textContent = labelText;
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.id = 'sl-' + name + '-' + id;
        inp.className = 'sl-input';
        inp.setAttribute('data-field', name);
        inp.placeholder = placeholder;
        inp.maxLength = name === 'qty' ? 60 : 300;
        wrap.appendChild(lab);
        wrap.appendChild(inp);
        return wrap;
      }

      // The rows are collected into one hidden field at submit time so the
      // form still posts as a normal form - no fetch, no JSON endpoint to
      // authorise, and the browser's own required-field checks still run.
      function serialise() {
        var payload = [];
        slRows.querySelectorAll('.sl-row').forEach(function (li) {
          var qty = li.querySelector('[data-field="qty"]');
          var note = li.querySelector('[data-field="note"]');
          payload.push({
            id: Number(li.getAttribute('data-id')),
            qty: qty ? qty.value : '',
            note: note ? note.value : ''
          });
        });
        document.getElementById('shortlist-payload').value = JSON.stringify(payload);
      }

      var clear = document.getElementById('sl-clear');
      if (clear) {
        clear.addEventListener('click', function () {
          write([]);
          slRows.innerHTML = '';
          showEmpty();
        });
      }
    })();
  }
})();
