/**
 * Image tools shared by every TinyMCE editor that writes post bodies: the
 * dashboard editor and editing on the post page.
 *
 * EditorMedia.install(editor, { blogId, token }) registers the buttons
 * medialibrary, replaceimage, imagesettings, wrapleft, wrapcenter, wrapright,
 * imageup and imagedown,
 * and drops images from other websites. token is a function, read on every
 * use, because the form's token can be renewed while the page is open.
 */
(function () {
  'use strict';

  var WRAPS = ['img-wrap-left', 'img-center', 'img-wrap-right'];

  function isOwnImage(src) {
    if (/^(data:image\/|blob:)/i.test(src)) return true;
    if (src.indexOf('//') === 0) return false;
    if (!/^[a-z][a-z0-9+.-]*:/i.test(src)) return true;
    try {
      return new URL(src).host === window.location.host;
    } catch (e) {
      return false;
    }
  }

  // Outside images the post already had when it opened. They are left alone,
  // matching the server, which only refuses newly added ones.
  function existingSources(html) {
    var found = {};
    var doc = new DOMParser().parseFromString(html, 'text/html');
    doc.querySelectorAll('img[src]').forEach(function (img) {
      found[img.getAttribute('src')] = true;
    });
    return found;
  }

  function startingHtml(editor) {
    var element = editor.getElement();
    return element.value !== undefined ? element.value : element.innerHTML;
  }

  function escapeAttribute(value) {
    return String(value || '')
      .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
      .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function selectedImage(editor) {
    var node = editor.selection.getNode();
    return node && node.nodeName === 'IMG' ? node : null;
  }

  function install(editor, options) {
    function openLibrary(onSelect) {
      if (!window.MediaPicker) {
        editor.notificationManager.open({ text: 'Media library not loaded.', type: 'error' });
        return;
      }
      if (!options.blogId) {
        editor.notificationManager.open({ text: 'Pick a blog before inserting images.', type: 'warning' });
        return;
      }
      window.MediaPicker.open(String(options.blogId), { csrfToken: options.token(), onSelect: onSelect });
    }

    function insertFromLibrary() {
      openLibrary(function (picked) {
        // Reuse the image's stored alt text so inserted images aren't left undescribed.
        editor.insertContent('<img src="' + escapeAttribute(picked.url) + '" alt="' + escapeAttribute(picked.alt) + '">');
      });
    }

    function setWrap(wrap) {
      var image = selectedImage(editor);
      if (!image) return;
      editor.undoManager.transact(function () {
        WRAPS.forEach(function (name) { editor.dom.removeClass(image, name); });
        if (wrap) editor.dom.addClass(image, wrap);
      });
      editor.nodeChanged();
    }

    // Moves the image to the start of the block before or after its own. A mouse can
    // drag it there too, but on a touch screen dragging scrolls the page instead.
    function moveImage(step) {
      var image = selectedImage(editor);
      if (!image) return;

      var body = editor.getBody();
      var block = image;
      while (block.parentNode !== body) block = block.parentNode;
      var neighbour = step < 0 ? block.previousElementSibling : block.nextElementSibling;
      if (!neighbour) return;

      editor.undoManager.transact(function () {
        if (editor.dom.is(neighbour, 'p,h1,h2,h3,h4,h5,h6,blockquote')) {
          neighbour.insertBefore(image, neighbour.firstChild);
        } else {
          var paragraph = editor.dom.create('p');
          paragraph.appendChild(image);
          body.insertBefore(paragraph, step < 0 ? neighbour : neighbour.nextSibling);
        }
        // A paragraph that only held the image would stay behind as an empty line.
        if (block !== image && !block.textContent.trim() && !block.querySelector('img, table')) {
          block.remove();
        }
      });
      editor.selection.select(image);
      editor.setDirty(true);
      editor.nodeChanged();
      image.scrollIntoView({ block: 'nearest' });
    }

    function wrapButton(name, wrap, icon, tooltip) {
      editor.ui.registry.addToggleButton(name, {
        icon: icon,
        tooltip: tooltip,
        onAction: function () { setWrap(wrap); },
        onSetup: function (api) {
          function update() {
            var image = selectedImage(editor);
            var current = image ? WRAPS.filter(function (n) { return editor.dom.hasClass(image, n); })[0] || '' : null;
            api.setEnabled(image !== null);
            api.setActive(current === wrap);
          }
          editor.on('NodeChange', update);
          return function () { editor.off('NodeChange', update); };
        }
      });
    }

    // Paste and the image dialog both route through the parser, so this catches
    // an outside address however it arrives. The server refuses them as well.
    editor.on('PreInit', function () {
      var alreadyThere = existingSources(startingHtml(editor));

      editor.parser.addNodeFilter('img', function (nodes) {
        var dropped = 0;
        nodes.forEach(function (node) {
          var src = node.attr('src') || '';
          if (!isOwnImage(src) && !alreadyThere[src]) {
            node.remove();
            dropped++;
          }
        });
        if (dropped > 0) {
          editor.notificationManager.open({
            text: 'Images from other websites are blocked by the site\'s security policy. Upload the image or pick it from the media library instead.',
            type: 'warning',
            timeout: 8000
          });
        }
      });
    });

    editor.ui.registry.addButton('medialibrary', {
      icon: 'gallery',
      tooltip: 'Insert image from library',
      onAction: insertFromLibrary
    });

    editor.ui.registry.addMenuItem('medialibrary', {
      icon: 'gallery',
      text: 'Image from library',
      onAction: insertFromLibrary
    });

    // The stock "imageoptions" button is a premium feature, so open the image dialog (size, alt, wrap) directly.
    editor.ui.registry.addButton('imagesettings', {
      icon: 'image-options',
      tooltip: 'Size, alt text and wrap',
      onAction: function () { editor.execCommand('mceImage'); }
    });

    editor.ui.registry.addButton('replaceimage', {
      icon: 'gallery',
      tooltip: 'Replace from library',
      onAction: function () {
        var image = selectedImage(editor);
        if (!image) return;
        openLibrary(function (picked) {
          editor.dom.setAttribs(image, { src: picked.url, 'data-mce-src': picked.url, alt: picked.alt || '' });
          editor.nodeChanged();
          editor.fire('change');
        });
      }
    });

    // A picture beside lines of text, so these never pass for the text alignment buttons.
    editor.ui.registry.addIcon('wrap-left', '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M3 5h8v8H3zM13 6h8v2h-8zM13 10h8v2h-8zM3 15h18v2H3zM3 19h18v2H3z"/></svg>');
    editor.ui.registry.addIcon('wrap-center', '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M3 3h18v2H3zM7 7h10v10H7zM3 19h18v2H3z"/></svg>');
    editor.ui.registry.addIcon('wrap-right', '<svg width="24" height="24" viewBox="0 0 24 24"><path d="M13 5h8v8h-8zM3 6h8v2H3zM3 10h8v2H3zM3 15h18v2H3zM3 19h18v2H3z"/></svg>');

    wrapButton('wrapleft', 'img-wrap-left', 'wrap-left', 'Left, text wraps on the right');
    wrapButton('wrapcenter', 'img-center', 'wrap-center', 'Centre, no text beside it');
    wrapButton('wrapright', 'img-wrap-right', 'wrap-right', 'Right, text wraps on the left');

    editor.ui.registry.addButton('imageup', {
      icon: 'chevron-up',
      tooltip: 'Move up a paragraph',
      onAction: function () { moveImage(-1); }
    });

    editor.ui.registry.addButton('imagedown', {
      icon: 'chevron-down',
      tooltip: 'Move down a paragraph',
      onAction: function () { moveImage(1); }
    });
  }

  window.EditorMedia = { install: install };
}());
