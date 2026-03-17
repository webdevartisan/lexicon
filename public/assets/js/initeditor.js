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
    'undo redo | styles | bold italic underline strikethrough| ' +
    'alignleft aligncenter alignright alignjustify',
    '|emoticons codesample charmap accordion|bullist numlist outdent indent | link image media table |visualblocks preview code fullscreen'
  ],

  branding: false,
  statusbar: true,

  automatic_uploads: true,
  paste_data_images: true, // allow paste & drag-drop images which will be uploaded

    images_upload_handler: (blobInfo, progress) => {
    const blogSelect = document.getElementById('blog_id')
    const blogId = blogSelect ? blogSelect.value : ''

    return new Promise((resolve, reject) => {
        if (!blogId) {
        reject('Please select a blog before uploading images.')
        return
        }

        const formData = new FormData()
        formData.append('image', blobInfo.blob(), blobInfo.filename())
        formData.append('blog_id', blogId)

        fetch('/dashboard/posts/image-upload', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            // todo: add CSRF header here
        },
        })
        .then(response => response.json())
        .then(json => {
            if (json && json.location) {
            // Resolve with the URL string; TinyMCE will insert <img src="...">
            resolve(json.location)
            } else {
            reject(json && json.error ? json.error : 'Image upload failed.')
            }
        })
        .catch(() => {
            reject('Image upload error.')
        })
    })
    },
    setup: function(editor) {
        editor.on('change', function() {
        tinymce.triggerSave();
        });
  }
});
