/**
 * Lets an author edit a post's title, text and cover on the post page itself.
 *
 * The theme marks the editable parts with data-edit-title, data-edit-body and
 * data-edit-cover. The edit bar carries the save address, the blog and the
 * editor script, so nothing here knows the server's routes.
 */
(function () {
  'use strict';

  var bar = document.querySelector('[data-edit-bar]');
  var titleEl = document.querySelector('[data-edit-title]');
  var bodyEl = document.querySelector('[data-edit-body]');
  if (!bar || !titleEl || !bodyEl) return;

  var coverEl = document.querySelector('[data-edit-cover]');
  var toolbarEl = bar.querySelector('[data-edit-toolbar]');
  var versionInput = bar.querySelector('[data-edit-version]');
  // The text as stored. What the page shows may have been rearranged by the theme's own script.
  var sourceEl = bar.querySelector('[data-edit-source]');
  var tokenInput = bar.querySelector('input[name="_token"]');
  var blogId = bar.getAttribute('data-blog-id');

  var editor = null;
  var original = null;
  // Undefined while the cover is untouched, '' once removed, a URL once picked.
  var pickedCover;

  // The theme's own texts on this page, when the author may also reword those.
  var texts = window.EditTexts ? window.EditTexts.attach(bar) : null;

  var editBar = window.EditBar.attach(bar, {
    isDirty: isDirty,
    isEditing: function () { return editor !== null; },
    discardMessage: 'Your changes to this post will be lost.'
  });
  var say = editBar.say;
  var setBusy = editBar.setBusy;

  function showEditing(on) {
    editBar.showEditing(on);
    toolbarEl.hidden = !on;
  }

  function loadEditorScript() {
    if (window.tinymce) return Promise.resolve();

    return new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = bar.getAttribute('data-editor-script');
      script.onload = resolve;
      script.onerror = function () { reject(new Error('The editor could not load. Reload the page and try again.')); };
      document.head.appendChild(script);
    });
  }

  // The editor's own menus should match the page: a dark theme gets the dark skin.
  function pageIsDark() {
    var sources = [document.body, document.documentElement];
    for (var i = 0; i < sources.length; i++) {
      var parts = getComputedStyle(sources[i]).backgroundColor.match(/[\d.]+/g);
      if (!parts || (parts.length === 4 && Number(parts[3]) === 0)) continue;
      return (0.299 * parts[0] + 0.587 * parts[1] + 0.114 * parts[2]) < 128;
    }
    return false;
  }

  function editorConfig() {
    return {
      target: bodyEl,
      inline: true,
      license_key: 'gpl',
      promotion: false,
      branding: false,
      menubar: false,
      skin: pageIsDark() ? 'oxide-dark' : 'oxide',
      // In the bar at the bottom of the window, so it stays in reach however far down the author is writing.
      fixed_toolbar_container_target: toolbarEl,
      toolbar_persist: true,
      // Pop ups next to text and images only show in the space TinyMCE thinks its toolbar leaves free.
      toolbar_location: 'bottom',
      // One swipeable row on a phone, where two wrapped rows would take half the screen.
      toolbar_mode: window.matchMedia('(max-width: 640px)').matches ? 'scrolling' : 'wrap',
      // Every tool lives in one place: paragraphs and inserting in the bar, styling words
      // next to the selected words, image tools next to the image.
      plugins: 'lists link autolink image table quickbars',
      toolbar: 'blocks | alignleft aligncenter alignright | bullist numlist blockquote | medialibrary table | undo redo',
      quickbars_insert_toolbar: false,
      quickbars_selection_toolbar: 'bold italic underline textcolour | link',
      quickbars_image_toolbar: window.EditorMedia.IMAGE_TOOLBAR,
      object_resizing: 'img',
      image_description: true,
      // Images come from the media library, which uploads them properly first.
      paste_data_images: false,
      relative_urls: false,
      remove_script_host: true,
      // Embeds load from other sites, which the Content-Security-Policy blocks.
      invalid_elements: 'iframe,object,embed,video,audio,source,track',
      setup: function (instance) {
        window.EditorMedia.install(instance, {
          blogId: blogId,
          token: function () { return tokenInput.value; }
        });
        window.EditorColours.install(instance);
      }
    };
  }

  function isDirty() {
    if (editor === null) return false;
    return editor.isDirty()
      || titleEl.textContent.trim() !== original.title.trim()
      || pickedCover !== undefined
      || (texts !== null && texts.isDirty());
  }

  function makeTitleEditable(on) {
    if (!on) {
      titleEl.removeAttribute('contenteditable');
      return;
    }
    titleEl.setAttribute('contenteditable', 'plaintext-only');
    // Firefox before 136 has no plaintext mode; the save sends textContent either way.
    if (titleEl.contentEditable !== 'plaintext-only') titleEl.setAttribute('contenteditable', 'true');
  }

  /* Cover */

  function coverImage() {
    return coverEl ? coverEl.querySelector('img') : null;
  }

  function showCover(url) {
    var image = coverImage();
    if (!url) {
      if (image) image.remove();
      return;
    }
    if (!image) {
      image = document.createElement('img');
      image.decoding = 'async';
      // A theme that frames its cover marks the frame the picture goes in
      var slot = coverEl.querySelector('[data-edit-cover-slot]') || coverEl;
      slot.insertBefore(image, slot.firstChild);
    }
    image.src = url;
    image.alt = titleEl.textContent.trim();
  }

  function coverButton(label, onClick) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'edit-bar-btn';
    button.textContent = label;
    button.addEventListener('click', onClick);
    return button;
  }

  function renderCoverControls() {
    var old = coverEl.querySelector('.edit-cover-controls');
    if (old) old.remove();

    var controls = document.createElement('div');
    controls.className = 'edit-cover-controls';
    var hasImage = coverImage() !== null;
    controls.appendChild(coverButton(hasImage ? 'Change cover' : 'Add a cover', pickCover));
    if (hasImage) controls.appendChild(coverButton('Remove cover', removeCover));

    coverEl.classList.toggle('is-empty', !hasImage);
    coverEl.appendChild(controls);
  }

  function pickCover() {
    if (!window.MediaPicker) {
      say('The media library could not load. Reload the page and try again.', 'error');
      return;
    }
    window.MediaPicker.open(blogId, {
      csrfToken: tokenInput.value,
      onSelect: function (picked) {
        pickedCover = picked.url;
        showCover(picked.url);
        renderCoverControls();
      }
    });
  }

  function removeCover() {
    pickedCover = '';
    showCover('');
    renderCoverControls();
  }

  function startCoverEditing() {
    if (!coverEl) return;
    coverEl.hidden = false;
    // Themes can hide a cover until it scrolls into view; one that was hidden when the page loaded never would.
    coverEl.classList.add('is-edited');
    renderCoverControls();
  }

  function stopCoverEditing(url) {
    if (!coverEl) return;
    var controls = coverEl.querySelector('.edit-cover-controls');
    if (controls) controls.remove();
    coverEl.classList.remove('is-empty');
    showCover(url);
    coverEl.hidden = !url;
  }

  /* Moving images */

  // Where in the text a point on screen falls, as a collapsed range.
  function rangeAtPoint(x, y) {
    if (document.caretPositionFromPoint) {
      var position = document.caretPositionFromPoint(x, y);
      if (!position) return null;
      var range = document.createRange();
      range.setStart(position.offsetNode, position.offset);
      return range;
    }
    return document.caretRangeFromPoint ? document.caretRangeFromPoint(x, y) : null;
  }

  // The browser's own drag of an image inside editable text is unreliable, so
  // images move with the pointer instead: press, drag past a few pixels, drop.
  var marker = document.createElement('div');
  marker.className = 'edit-drop-marker';
  marker.hidden = true;
  document.body.appendChild(marker);

  var moving = null;

  function dropRange(event) {
    var range = rangeAtPoint(event.clientX, event.clientY);
    if (!range || !bodyEl.contains(range.startContainer)) return null;
    return range;
  }

  function showMarker(range) {
    var rect = range ? range.getClientRects()[0] || range.getBoundingClientRect() : null;
    if (!rect || !rect.height) {
      marker.hidden = true;
      return;
    }
    marker.hidden = false;
    marker.style.left = rect.left + 'px';
    marker.style.top = rect.top + 'px';
    marker.style.height = rect.height + 'px';
  }

  function placeImage(image, range) {
    var from = image.parentNode;
    editor.undoManager.transact(function () {
      // Dropped between paragraphs rather than into one: it gets a paragraph of its own.
      if (range.startContainer === bodyEl) {
        var paragraph = editor.dom.create('p');
        range.insertNode(paragraph);
        paragraph.appendChild(image);
      } else {
        range.insertNode(image);
      }
      // A paragraph that only held the image would stay behind as an empty line.
      if (from !== bodyEl && from !== image.parentNode && !from.textContent.trim() && !from.querySelector('img, table')) {
        from.remove();
      }
    });
    editor.selection.select(image);
    editor.setDirty(true);
    editor.nodeChanged();
  }

  function onPointerMove(event) {
    if (!moving.started) {
      if (Math.abs(event.clientX - moving.x) + Math.abs(event.clientY - moving.y) < 6) return;
      moving.started = true;
      moving.image.setAttribute('data-mce-moving', '');
      document.documentElement.classList.add('is-moving-image');
    }
    event.preventDefault();
    moving.range = dropRange(event);
    showMarker(moving.range);
  }

  function endMove(event) {
    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', endMove);
    document.removeEventListener('pointercancel', endMove);
    marker.hidden = true;
    document.documentElement.classList.remove('is-moving-image');
    moving.image.removeAttribute('data-mce-moving');
    if (event.type === 'pointerup' && moving.started && moving.range && editor) placeImage(moving.image, moving.range);
    moving = null;
  }

  bodyEl.addEventListener('pointerdown', function (event) {
    // A finger on a picture is usually scrolling the page; touch uses the move up and down buttons.
    if (!editor || event.pointerType === 'touch' || event.button !== 0 || event.target.nodeName !== 'IMG') return;
    moving = { image: event.target, x: event.clientX, y: event.clientY, started: false, range: null };
    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', endMove);
    document.addEventListener('pointercancel', endMove);
  });

  bodyEl.addEventListener('dragstart', function (event) {
    if (editor && event.target.nodeName === 'IMG') event.preventDefault();
  });

  /* Pop ups */

  // TinyMCE does not know about a theme header fixed to the top of the window and can
  // put a pop up under it. Such a pop up goes below what it belongs to, or just under the header.
  var themeHeader = document.querySelector('[data-edit-header]');

  function popTarget(height) {
    var node = editor.selection.getNode();
    var anchor = node.nodeName === 'IMG' ? node.getBoundingClientRect() : editor.selection.getRng().getBoundingClientRect();
    var below = anchor.bottom + 8;
    return below + height <= bar.getBoundingClientRect().top ? below : themeHeader.getBoundingClientRect().bottom + 8;
  }

  function clearThemeHeader() {
    if (!editor) return;
    var limit = themeHeader.getBoundingClientRect().bottom + 8;
    toolbarEl.querySelectorAll('.tox-pop').forEach(function (pop) {
      var box = pop.getBoundingClientRect();
      // A hidden pop up measures as an empty box at the very top
      if (!box.height || box.top >= limit) return;
      // TinyMCE places a pop up above something by its bottom edge, which would fight a new top
      pop.style.bottom = '';
      pop.style.maxHeight = '';
      pop.style.top = popTarget(box.height) - pop.offsetParent.getBoundingClientRect().top + 'px';
    });
  }

  if (themeHeader) {
    new MutationObserver(clearThemeHeader)
      .observe(toolbarEl, { subtree: true, childList: true, attributes: true, attributeFilter: ['style'] });
  }

  /* Keeping the page still */

  // Showing the empty cover slot, or hiding it again, would push the text the author
  // is looking at out of view. Hold that text where it was instead.
  function keepInPlace(change) {
    var root = document.documentElement;
    var anchor = bodyEl.getBoundingClientRect().top < window.innerHeight / 2 ? bodyEl : titleEl;
    var before = anchor.getBoundingClientRect().top;
    // The browser's own scroll anchoring would correct the same shift a second time.
    root.style.overflowAnchor = 'none';
    change();
    window.scrollBy(0, anchor.getBoundingClientRect().top - before);
    requestAnimationFrame(function () { root.style.overflowAnchor = ''; });
  }

  /* Editing */

  function start() {
    setBusy(true);
    say('Opening the editor…');
    var image = coverImage();
    original = { title: titleEl.textContent, body: bodyEl.innerHTML, cover: image ? image.getAttribute('src') : '' };
    pickedCover = undefined;
    // TinyMCE lays out its toolbar when it starts, so the slot has to be showing by then.
    toolbarEl.hidden = false;
    bodyEl.innerHTML = sourceEl.innerHTML;

    loadEditorScript()
      .then(function () { return window.tinymce.init(editorConfig()); })
      .then(function (editors) {
        editor = editors[0];
        makeTitleEditable(true);
        if (texts) texts.start();
        // Nothing takes focus: the author starts typing wherever they tap.
        keepInPlace(function () {
          startCoverEditing();
          showEditing(true);
        });
        say('');
      })
      .catch(function (error) {
        toolbarEl.hidden = true;
        bodyEl.innerHTML = original.body;
        say(error.message, 'error');
      })
      .finally(function () { setBusy(false); });
  }

  function stop(cover) {
    if (editor) editor.remove();
    editor = null;
    toolbarEl.textContent = '';
    makeTitleEditable(false);
    keepInPlace(function () {
      stopCoverEditing(cover);
      showEditing(false);
    });
  }

  function cancel() {
    editBar.confirmDiscard().then(function (yes) {
      if (!yes) return;
      if (texts) texts.stop(false);
      stop(original.cover);
      titleEl.textContent = original.title;
      bodyEl.innerHTML = original.body;
      say('');
    });
  }

  function save(event) {
    event.preventDefault();
    if (!editor) return;

    var data = new FormData(bar);
    data.append('title', titleEl.textContent.trim());
    data.append('content', editor.getContent());
    if (pickedCover !== undefined) data.append('cover', pickedCover);

    setBusy(true);
    say('Saving…');

    editBar.send(data, 'The post could not be saved. Try again.')
      .then(function (saved) {
        // Kept before the theme texts save, so trying again after a failed one is not taken for an edit made elsewhere.
        versionInput.value = saved.version;
        return (texts ? texts.save(editBar.send) : Promise.resolve()).then(function () { return saved; });
      })
      .then(function (saved) {
        if (texts) texts.stop(true);
        stop(saved.cover || '');
        titleEl.textContent = saved.title;
        // The server's own cleaned copy of the text, the same HTML readers get.
        bodyEl.innerHTML = saved.content;
        sourceEl.innerHTML = saved.content;
        say('Saved');
      })
      .catch(function (error) { say(error.message, 'error'); })
      .finally(function () { setBusy(false); });
  }

  titleEl.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && editor) {
      event.preventDefault();
      editor.focus();
    }
  });

  editBar.startBtn.addEventListener('click', start);
  editBar.cancelBtn.addEventListener('click', cancel);
  bar.addEventListener('submit', save);
}());
