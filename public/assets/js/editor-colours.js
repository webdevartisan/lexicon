/**
 * The text colour button shared by both post editors.
 *
 * Colours are named rather than picked: each theme decides what Accent looks
 * like, so coloured words stay readable when the blog changes theme.
 * EditorColours.install(editor) registers the textcolour button.
 */
(function () {
  'use strict';

  var COLOURS = [
    { format: 'coloraccent', text: 'Accent', classes: 'color-accent' },
    { format: 'colormuted', text: 'Muted', classes: 'color-muted' }
  ];
  var SPANS = COLOURS.map(function (colour) { return 'span.' + colour.classes; }).join(',');

  function install(editor) {
    editor.on('PreInit', function () {
      COLOURS.forEach(function (colour) {
        editor.formatter.register(colour.format, { inline: 'span', classes: colour.classes });
      });
    });

    function current() {
      var found = COLOURS.filter(function (colour) { return editor.formatter.match(colour.format); })[0];
      return found ? found.format : '';
    }

    function use(format) {
      editor.undoManager.transact(function () {
        COLOURS.forEach(function (colour) { editor.formatter.remove(colour.format); });
        if (format) editor.formatter.apply(format);
        // A colour that also wrapped a picture would otherwise stay behind around the picture alone
        editor.dom.select(SPANS).forEach(function (span) {
          if (!span.textContent.trim()) editor.dom.remove(span, true);
        });
      });
      editor.nodeChanged();
    }

    function item(text, format) {
      return {
        type: 'togglemenuitem',
        text: text,
        onAction: function () { use(format); },
        onSetup: function (api) {
          api.setActive(current() === format);
          return function () {};
        }
      };
    }

    editor.ui.registry.addMenuButton('textcolour', {
      icon: 'text-color',
      tooltip: 'Text colour',
      fetch: function (callback) {
        callback([item('Normal', '')].concat(COLOURS.map(function (colour) {
          return item(colour.text, colour.format);
        })));
      }
    });
  }

  window.EditorColours = { install: install };
}());
