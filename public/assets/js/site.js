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
})();
