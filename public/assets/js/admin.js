/* Admin panel helpers. Nothing here is required for the panel to work -
   forms post normally if JavaScript is off. */
(function () {
  'use strict';

  // --- Sidebar on small screens -------------------------------------------
  var navToggle = document.querySelector('.adm-nav-toggle');
  var side = document.getElementById('adm-side');
  if (navToggle && side) {
    navToggle.addEventListener('click', function () {
      var open = side.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // --- Confirm before destructive posts ------------------------------------
  // The server does not depend on this - it is a courtesy, not a safeguard.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        ev.preventDefault();
      }
    });
  });

  // --- Specification rows --------------------------------------------------
  var rows = document.getElementById('spec-rows');
  var tpl = document.getElementById('spec-template');
  var addBtn = document.getElementById('add-spec');

  function hideEmptyNote() {
    var note = document.getElementById('spec-empty');
    if (note) { note.style.display = 'none'; }
  }

  if (rows && tpl && addBtn) {
    addBtn.addEventListener('click', function () {
      var clone = tpl.content.cloneNode(true);
      rows.appendChild(clone);
      hideEmptyNote();
      var added = rows.lastElementChild;
      // Carry the group down from the row above - long spec lists usually
      // continue in the same section.
      var prev = added.previousElementSibling;
      if (prev) {
        added.querySelector('input[name="spec_group[]"]').value =
          prev.querySelector('input[name="spec_group[]"]').value;
      }
      added.querySelector('input[name="spec_name[]"]').focus();
    });

    rows.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.remove-spec');
      if (btn) { btn.closest('.spec-row').remove(); }
    });
  }

  // --- Slug preview --------------------------------------------------------
  var nameInput = document.getElementById('name');
  var slugInput = document.getElementById('slug');
  var slugPreview = document.getElementById('slug-preview');
  function slugify(s) {
    return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }
  if (slugPreview && nameInput) {
    var update = function () {
      var v = slugInput && slugInput.value ? slugInput.value : nameInput.value;
      slugPreview.textContent = slugify(v) || 'your-product';
    };
    nameInput.addEventListener('input', update);
    if (slugInput) { slugInput.addEventListener('input', update); }
  }

  // --- Image picker: drag and drop + thumbnails ----------------------------
  var dropzone = document.getElementById('dropzone');
  var fileInput = document.getElementById('images');
  var previewGrid = document.getElementById('preview-grid');

  function renderPreviews(files) {
    if (!previewGrid) { return; }
    previewGrid.innerHTML = '';
    Array.prototype.forEach.call(files, function (file) {
      if (!file.type.startsWith('image/')) { return; }
      var item = document.createElement('div');
      item.className = 'preview-item';
      var img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.onload = function () { URL.revokeObjectURL(img.src); };
      var label = document.createElement('span');
      label.textContent = file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)';
      item.appendChild(img);
      item.appendChild(label);
      previewGrid.appendChild(item);
    });
  }

  if (dropzone && fileInput) {
    fileInput.addEventListener('change', function () { renderPreviews(fileInput.files); });

    ['dragenter', 'dragover'].forEach(function (type) {
      dropzone.addEventListener(type, function (ev) {
        ev.preventDefault();
        dropzone.classList.add('is-over');
      });
    });
    ['dragleave', 'drop'].forEach(function (type) {
      dropzone.addEventListener(type, function (ev) {
        ev.preventDefault();
        dropzone.classList.remove('is-over');
      });
    });
    dropzone.addEventListener('drop', function (ev) {
      if (ev.dataTransfer && ev.dataTransfer.files.length) {
        fileInput.files = ev.dataTransfer.files;
        renderPreviews(fileInput.files);
      }
    });
  }
})();
