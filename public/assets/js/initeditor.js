// Read when sending, because the token can be swapped in the background once it expires.
function formToken(editor) {
  var input = editor.formElement && editor.formElement.querySelector('input[name="_token"]');
  return input ? input.value : '';
}

/**
 * Start the editor in the skin that matches the current mode.
 */
function startEditor() {
  const isDark = document.documentElement.getAttribute('data-mode') === 'dark';

  tinymce.init({
    selector: '#content',
    height: 500,
    license_key: 'gpl',
    promotion: false,

    // Otherwise inserted images are saved relative to the editor page (../../../../uploads/...) and break everywhere else
    relative_urls: false,
    remove_script_host: true,

    skin: isDark ? 'oxide-dark' : 'oxide',
    content_css: [isDark ? 'dark' : 'default', '/assets/css/post-content.css'],

    menubar: 'edit insert format',
    menu: {
      insert: { title: 'Insert', items: 'medialibrary link table hr | emoticons codesample charmap accordion' }
    },
    plugins: 'lists link image table code fullscreen searchreplace emoticons preview ' +
             'wordcount visualblocks codesample charmap accordion quickbars',

    // Advanced tools sit last so they fold into the "..." drawer on narrow screens.
    toolbar: 'undo redo | styles | bold italic underline textcolour | alignleft aligncenter alignright | ' +
             'bullist numlist | link medialibrary table | ' +
             'emoticons codesample charmap accordion visualblocks preview code fullscreen',
    toolbar_mode: 'sliding',

    quickbars_insert_toolbar: 'medialibrary table',
    quickbars_selection_toolbar: 'bold italic textcolour | h2 h3 | link blockquote',
    quickbars_image_toolbar: 'alignleft aligncenter alignright | replaceimage imagesettings',
    object_resizing: 'img',

    image_class_list: [
      { title: 'None', value: '' },
      { title: 'Wrap right', value: 'img-wrap-right' },
      { title: 'Wrap left', value: 'img-wrap-left' }
    ],

    content_style: `
      body {
        background-color: ${isDark ? '#171717' : '#ffffff'};
        --post-accent: ${isDark ? '#a8c7fa' : '#2659b5'};
      }
      ${isDark ? '.mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before { color: rgba(255, 255, 255, 0.4); }' : ''}
      img {
        max-width: 100%;
        height: auto;
      }
    `,

    // Embeds and hotlinked images load from other sites, which the Content-Security-Policy blocks.
    invalid_elements: 'iframe,object,embed,video,audio,source,track',
    image_description: true,
    file_picker_types: 'image',
    file_picker_callback: function (callback) {
      if (!window.MediaPicker) return;
      window.MediaPicker.open(String(window.editorBlogId || ''), {
        csrfToken: formToken(tinymce.activeEditor),
        onSelect: function (picked) {
          callback(picked.url, { alt: picked.alt || '' });
        },
      });
    },

    branding: false,
    statusbar: true,
    automatic_uploads: true,
    paste_data_images: true,

    images_upload_handler: (blobInfo, progress) => {
      const blogId = window.editorBlogId ? String(window.editorBlogId) : '';

      return new Promise((resolve, reject) => {
        if (!blogId) {
          reject('Please select a blog before uploading images.');
          return;
        }

        const formData = new FormData();
        formData.append('image', blobInfo.blob(), blobInfo.filename());
        formData.append('blog_id', blogId);

        fetch('/dashboard/posts/image-upload', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': formToken(tinymce.get('content'))
          },
        })
        .then(response => response.json())
        .then(json => {
          if (json && json.location) {
            resolve(json.location);
          } else {
            reject(json && json.error ? json.error : 'Image upload failed.');
          }
        })
        .catch(() => {
          reject('Image upload error.');
        });
      });
    },

    setup: function (editor) {
      editor.on('init', function () {
        started = { text: editor.getElement().value, content: editor.getContent() };
      });

      editor.on('change', function () {
        tinymce.triggerSave();
        if (window.refreshPostPreviews) window.refreshPostPreviews();
      });

      EditorMedia.install(editor, {
        blogId: window.editorBlogId,
        token: function () { return formToken(editor); },
      });
      EditorColours.install(editor);
    }
  });
}

// The text box and the editor's reading of it when the current editor started.
var started = { text: '', content: '' };

startEditor();

// The skin is fixed when TinyMCE starts, so switching mode needs a fresh instance.
var lastMode = document.documentElement.getAttribute('data-mode');
new MutationObserver(function () {
  var mode = document.documentElement.getAttribute('data-mode');
  if (mode === lastMode) return;
  lastMode = mode;

  var editor = tinymce.get('content');
  var box = editor.getElement();
  // Removing an editor writes its content into the box in its own format. With
  // nothing changed, the box gets its starting text back, or the leave warning
  // would take the reformatting for an edit. The dirty flag can't decide this:
  // TinyMCE only sets it on key-up, and text can arrive without one.
  var unchanged = editor.initialized && editor.getContent() === started.content;
  editor.save();
  tinymce.remove('#content');
  if (unchanged) box.value = started.text;
  startEditor();
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-mode'] });