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

function openLibrary(editor, onSelect) {
  if (!window.MediaPicker) {
    editor.notificationManager.open({ text: 'Media library not loaded.', type: 'error' });
    return;
  }
  var blogId = window.editorBlogId ? String(window.editorBlogId) : '';
  if (!blogId) {
    editor.notificationManager.open({ text: 'Pick a blog before inserting images.', type: 'warning' });
    return;
  }
  var postForm = document.querySelector('form[data-autosave-form]');
  var tokenInput = postForm && postForm.querySelector('input[name="_token"]');

  window.MediaPicker.open(blogId, {
    csrfToken: tokenInput ? tokenInput.value : '',
    onSelect: onSelect,
  });
}
const isDark = localStorage.getItem('data-mode') === 'dark';

tinymce.init({
  selector: '#content',
  height: 500,
  license_key: 'gpl',
  promotion: false,

  skin: isDark ? 'oxide-dark' : 'oxide',
  content_css: isDark ? 'dark' : 'default',

  menubar: 'file edit view insert format tools table help',
  plugins: 'lists link image table code fullscreen searchreplace emoticons preview ' +
           'wordcount visualblocks codesample charmap accordion quickbars',

  toolbar: [
    'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify',
    '| emoticons codesample charmap accordion | bullist numlist outdent indent | link image medialibrary table | visualblocks preview code fullscreen'
  ],

  image_class_list: [
    { title: 'None', value: '' },
    { title: 'Wrap right', value: 'img-wrap-right' },
    { title: 'Wrap left', value: 'img-wrap-left' }
  ],

  content_style: `
    img.img-wrap-right {
      float: right;
      margin: 0 0 12px 16px;
      max-width: 220px;
      height: auto;
    }

    img.img-wrap-left {
      float: left;
      margin: 0 16px 12px 0;
      max-width: 220px;
      height: auto;
    }
  `,

  // Embeds and hotlinked images load from other sites, which the Content-Security-Policy blocks.
  invalid_elements: 'iframe,object,embed,video,audio,source,track',
  image_description: true,
  file_picker_types: 'image',
  file_picker_callback: function (callback) {
    openLibrary(tinymce.activeEditor, function (picked) {
      callback(picked.url, { alt: picked.alt || '' });
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

      const postForm = document.querySelector('form[data-autosave-form]');
      const csrfToken = postForm.querySelector('input[name="_token"]').value;

      const formData = new FormData();
      formData.append('image', blobInfo.blob(), blobInfo.filename());
      formData.append('blog_id', blogId);

      fetch('/dashboard/posts/image-upload', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken
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
    editor.on('change', function () {
      tinymce.triggerSave();
    });

    // Paste and the image dialog both route through the parser, so this catches
    // an outside address however it arrives. The server refuses them as well.
    editor.on('PreInit', function () {
      var alreadyThere = existingSources(editor.getElement().value || '');

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

    // Custom toolbar button — opens the per-blog Media Library picker
    // and inserts the chosen image at the cursor.
    editor.ui.registry.addButton('medialibrary', {
      icon: 'gallery',
      tooltip: 'Insert from Media Library',
      onAction: function () {
        openLibrary(editor, function (picked) {
          // Reuse the image's stored alt text so inserted images aren't left undescribed.
          var alt = (picked.alt || '')
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
          editor.insertContent('<img src="' + picked.url + '" alt="' + alt + '">');
        });
      },
    });
  }
});