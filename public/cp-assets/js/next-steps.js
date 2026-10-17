/**
 * "Your next steps" card on the Overview.
 *
 * The card is server-rendered and its forms work on their own. This takes over
 * hide, show, finish and copy so they happen in place: the change shows
 * straight away and is undone if the server answers with an error.
 *
 * The site's CSP has no unsafe-eval, so the page loads Alpine's CSP build.
 */
document.addEventListener('alpine:init', function () {
  var token = function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var input = document.querySelector('[data-next-steps] input[name="_token"]');

    return meta ? meta.content : (input ? input.value : '');
  };

  window.Alpine.data('nextSteps', function () {
    var config = {};

    return {
      state: 'open',
      done: 0,
      total: 0,
      shared: false,
      copied: false,
      fallback: false,
      ready: true,

      // The CSP build of Alpine only reads names, so every expression the card uses lives here
      get isOpen() { return this.state === 'open'; },
      get isHidden() { return this.state === 'hidden'; },
      get isFinished() { return this.state === 'finished'; },
      get notShared() { return !this.shared; },
      get progressText() { return this.format(config.text.progress); },
      get collapsedText() { return this.format(config.text.collapsed); },
      get copyLabel() { return this.copied ? config.text.copied : config.text.copy; },
      get barStyle() {
        var percent = this.total > 0 ? Math.round(this.done * 100 / this.total) : 0;

        return 'width: ' + percent + '%';
      },

      // Starting state comes from data-config: the CSP build can't pass arguments in x-data
      init: function () {
        config = JSON.parse(this.$el.getAttribute('data-config'));
        this.state = config.state;
        this.done = config.done;
        this.total = config.total;
        this.shared = config.shared;
      },

      format: function (template) {
        return template.replace('{done}', this.done).replace('{total}', this.total);
      },

      selectAddress: function (event) {
        event.target.select();
        this.markShared();
      },

      hide: function () {
        var self = this;
        this.change('hide', { state: 'hidden' }, function () {
          self.$nextTick(function () { self.$focus.focus(self.$refs.collapsed); });
        });
      },

      show: function () {
        var self = this;
        this.change('show', { state: 'open' }, function () {
          self.$nextTick(function () { self.$focus.focus(self.$refs.heading); });
        });
      },

      dismiss: function () {
        this.change('dismiss', { state: 'dismissed' });
      },

      copy: function () {
        var self = this;
        if (!navigator.clipboard || !window.isSecureContext) {
          this.showFallback();

          return;
        }

        navigator.clipboard.writeText(config.blogUrl).then(function () {
          self.copied = true;
          self.markShared();
        }, function () {
          self.showFallback();
        });
      },

      showFallback: function () {
        var self = this;
        this.fallback = true;
        this.$nextTick(function () { self.$refs.urlInput.focus(); });
      },

      markShared: function () {
        if (this.shared) {
          return;
        }

        var self = this;
        this.shared = true;
        this.done += 1;
        this.post('copied').then(function (data) {
          self.apply(data);
        }, function () {
          self.shared = false;
          self.done -= 1;
        });
      },

      /**
       * Show the new state now, then settle on what the server says, or put
       * the old state back if it refused.
       */
      change: function (action, optimistic, after) {
        var self = this;
        var previous = { state: this.state, done: this.done, total: this.total };
        Object.assign(this, optimistic);
        if (after) after();

        this.post(action).then(function (data) {
          self.apply(data);
        }, function () {
          Object.assign(self, previous);
        });
      },

      apply: function (data) {
        this.state = data.state;
        this.done = data.done;
        this.total = data.total;
      },

      post: function (action) {
        return fetch(config.urls[action], {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token()
          }
        }).then(function (response) {
          if (!response.ok) throw new Error('Request failed: ' + response.status);

          return response.json();
        });
      }
    };
  });
});
