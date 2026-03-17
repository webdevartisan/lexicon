## Security and CSRF for AJAX

Lexicon uses CSRF tokens to protect state‑changing requests. To keep pages cacheable, CSRF tokens for AJAX are fetched on demand from a dedicated endpoint instead of being embedded in HTML.

---

## CSRF Token Endpoint

Add a route that returns a fresh token as JSON, for example:

```php
// CSRF token endpoint for AJAX requests (NOT cached)
$router->add('/csrf-token', [
    'controller' => 'HomeController',
    'action' => 'csrfToken',
    'method' => 'GET',
]);
```

Controller method:

```php
/**
 * Return a fresh CSRF token for AJAX requests.
 */
public function csrfToken(): Response
{
    $this->response->addHeader('Cache-Control', 'no-store, must-revalidate, no-cache');
    $this->response->addHeader('Pragma', 'no-cache');
    $this->response->addHeader('Expires', '0');

    return $this->json([
        'token' => csrf_token(),
        'expires_in' => 7200,
    ]);
}
```

This endpoint must be excluded from HTML/page caching to ensure fresh tokens.

---

## JavaScript CSRF Helper

Create `public/assets/js/csrf.js`:

```js
class CsrfProtection {
    constructor() {
        this.token = null;
        this.tokenPromise = null;
        this.tokenFetchedAt = null;
        this.tokenMaxAge = 3600000; // 1 hour
    }

    async getToken() {
        if (this.token && this.isTokenFresh()) {
            return this.token;
        }

        if (this.tokenPromise) {
            return this.tokenPromise;
        }

        this.tokenPromise = fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`CSRF token fetch failed: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                this.token = data.token;
                this.tokenFetchedAt = Date.now();
                this.tokenPromise = null;
                return this.token;
            })
            .catch(error => {
                this.tokenPromise = null;
                console.error('Failed to fetch CSRF token:', error);
                throw error;
            });

        return this.tokenPromise;
    }

    isTokenFresh() {
        if (!this.tokenFetchedAt) {
            return false;
        }
        const age = Date.now() - this.tokenFetchedAt;
        return age < this.tokenMaxAge;
    }

    async refreshToken() {
        this.token = null;
        this.tokenFetchedAt = null;
        this.tokenPromise = null;
        return this.getToken();
    }

    clearToken() {
        this.token = null;
        this.tokenFetchedAt = null;
        this.tokenPromise = null;
    }
}

window.csrf = new CsrfProtection();

document.addEventListener('DOMContentLoaded', () => {
    window.csrf.getToken().catch(err => {
        console.warn('Failed to preload CSRF token:', err);
    });
});
```

Include it in your base layout before any scripts that depend on it:

```html
<script src="/assets/js/csrf.js"></script>
```

---

## Integrating with TinyMCE

Example configuration:

```js
tinymce.init({
    selector: 'textarea#content',
    height: 500,
    plugins: 'image media link lists code',
    toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image',
    automatic_uploads: true,
    images_upload_url: '/dashboard/posts/image-upload',

    images_upload_handler: async function (blobInfo, success, failure) {
        try {
            const token = await window.csrf.getToken();

            const formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('_token', token);

            const response = await fetch('/dashboard/posts/image-upload', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (response.ok && data.location) {
                success(data.location);
            } else {
                failure('Upload failed: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            failure('Network error: ' + err.message);
        }
    },
});
```

---

## Integrating with Dropzone

```js
(async function () {
    const token = await window.csrf.getToken();

    const myDropzone = new Dropzone('#file-upload-dropzone', {
        url: '/dashboard/upload',
        maxFilesize: 5,
        acceptedFiles: 'image/*,.pdf,.doc,.docx',

        sending(file, xhr, formData) {
            formData.append('_token', token);
        },
    });
})();
```

---

## AJAX Helpers

Example helpers using the CSRF token:

```js
async function ajaxPost(url, data) {
    const token = await window.csrf.getToken();

    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token,
        },
        body: JSON.stringify(data),
        credentials: 'same-origin',
    });
}

async function ajaxDelete(url) {
    const token = await window.csrf.getToken();

    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token,
        },
        body: JSON.stringify({ _method: 'DELETE' }),
        credentials: 'same-origin',
    });
}
```

On the server, accept the token from either header or body:

```php
$token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

if (! csrf_verify($token)) {
    return $this->json(['error' => 'Invalid CSRF token'], 403);
}
```

---

## Testing and Troubleshooting

- Test the endpoint: visit `/csrf-token` and ensure a JSON token is returned.
- In the browser console:

```js
window.csrf.getToken().then(token => console.log('Token:', token));
```

- Verify uploads:
  - Use DevTools Network tab to confirm `_token` or `X-CSRF-Token` is being sent.
  - Ensure CSRF middleware and helpers are wired correctly.

Because tokens are fetched via AJAX and not embedded in HTML, full pages remain cacheable by `CacheMiddleware` and at the browser layer.

