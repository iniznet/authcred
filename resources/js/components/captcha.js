import Form from './form';

const CAPTCHA_POLL_TIMEOUT = 120000;

export default (action, back = false, multiStep = false, captchaAction = '', provider = '') => ({
    ...Form(action, back, multiStep),

    async dispatch() {
      if (this.loading) {
        return;
      }

      this.toggle();
      this.$store.form.success = null;

      try {
        const token = await this.resolveCaptchaToken();

        if (!token) {
          this.toggle();
          return;
        }

        const data = new FormData(this.$el);
        data.set('captcha_token', token);
        data.append('action', action);

        await this.submitData(data);
      } catch (error) {
        if (!this.$store.form.message || !this.$store.form.message.title) {
          this.setCaptchaError(this.getErrorMessage('failed'));
        }
      }

      this.toggle();
    },

    async resolveCaptchaToken() {
      const config = this.getCaptchaConfig();

      if (!provider || !config) {
        this.setCaptchaError(this.getErrorMessage('load'));
        return '';
      }

      if (provider === 'recaptcha') {
        return await this.resolveRecaptchaToken(config);
      }

      if (provider === 'turnstile') {
        return this.resolveTurnstileToken(config);
      }

      this.setCaptchaError(this.getErrorMessage('failed'));
      return '';
    },

    async resolveRecaptchaToken(config) {
      if (typeof window.grecaptcha === 'undefined') {
        this.setCaptchaError(this.getErrorMessage('load'));
        return '';
      }

      if (config.version === 'v3') {
        try {
          return await new Promise((resolve, reject) => {
            window.grecaptcha.ready(() => {
              window.grecaptcha.execute(config.siteKey, { action: config.action }).then(resolve).catch(reject);
            });
          });
        } catch (error) {
          this.setCaptchaError(this.getErrorMessage('failed'));
          return '';
        }
      }

      if (typeof config.widgetId === 'undefined' && typeof window.authcredRegisterRecaptchaWidget === 'function') {
        window.authcredRegisterRecaptchaWidget(captchaAction);
      }

      if (config.version === 'v2_invisible') {
        if (typeof config.widgetId === 'undefined') {
          this.setCaptchaError(this.getErrorMessage('load'));
          return '';
        }

        this.clearToken(config);

        try {
          window.grecaptcha.execute(config.widgetId);
          return await this.waitForToken(config);
        } catch (error) {
          this.setCaptchaError(this.getErrorMessage('failed'));
          return '';
        }
      }

      const token = this.getToken(config);

      if (!token) {
        this.setCaptchaError(this.getErrorMessage('incomplete'));
      }

      return token;
    },

    resolveTurnstileToken(config) {
      if (typeof window.turnstile === 'undefined') {
        this.setCaptchaError(this.getErrorMessage('load'));
        return '';
      }

      const token = this.getToken(config);

      if (!token) {
        this.setCaptchaError(this.getErrorMessage('incomplete'));
      }

      return token;
    },

    getCaptchaConfig() {
      const configs = window.authcredCaptcha || {};
      const config = configs[captchaAction];

      if (!config || typeof config !== 'object' || !config.provider) {
        return null;
      }

      return config;
    },

    getErrorMessage(key) {
      const errors = window.authcredCaptcha && window.authcredCaptcha.errors;

      if (errors && typeof errors[key] === 'string') {
        return errors[key];
      }

      return '';
    },

    getToken(config) {
      const tokenField = this.$el.querySelector(`#${config.tokenFieldId}`) || document.getElementById(config.tokenFieldId);
      return tokenField ? tokenField.value.trim() : '';
    },

    clearToken(config) {
      const tokenField = this.$el.querySelector(`#${config.tokenFieldId}`) || document.getElementById(config.tokenFieldId);

      if (tokenField) {
        tokenField.value = '';
      }
    },

    waitForToken(config, timeout = CAPTCHA_POLL_TIMEOUT) {
      const startedAt = Date.now();

      return new Promise((resolve, reject) => {
        const poll = () => {
          const token = this.getToken(config);

          if (token) {
            resolve(token);
            return;
          }

          if (Date.now() - startedAt >= timeout) {
            reject(new Error('captcha-timeout'));
            return;
          }

          window.setTimeout(poll, 100);
        };

        poll();
      });
    },

    setCaptchaError(message) {
      if (!message) {
        return;
      }

      this.$store.form.success = false;
      this.$store.form.message = {
        title: message,
        body: '',
      };
    },
  });
