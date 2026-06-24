const isDark = localStorage.getItem('data-mode') === 'dark';

tinymce.init({
  selector: '#content',
  height: 500,
  license_key: 'gpl',
  promotion: false,

  skin: isDark ? 'oxide-dark' : 'oxide',
  content_css: isDark ? 'dark' : 'default',

  menubar: 'file edit view insert format tools table help',
  plugins: 'lists link image table code media fullscreen searchreplace emoticons preview ' +
           'wordcount visualblocks codesample charmap accordion quickbars',

  toolbar: [
    'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify',
    '| emoticons codesample charmap accordion | bullist numlist outdent indent | link image media table | visualblocks preview code fullscreen'
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
  }
});