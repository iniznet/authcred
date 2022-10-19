export default (action, back = false, multiStep = false) => ({
    loading: false,
    fill: {},

    init() {
      // Prefill form inputs with data from query string
      const hash = location.hash.substring(1);
      const query = hash.split('?')[1];
      const params = new URLSearchParams(query);

      for (const key of params.keys()) {
        this.fill[key] = params.get(key);
      }
    },
  
    async dispatch() {
      if (this.loading) {
        return;
      }
  
      this.toggle();
      this.$store.form.success = null;
  
      try {
        const data = new FormData(this.$el);
        data.append('action', action);
  
        const response = await this.$ajax(data);
  
        if (response.success && back) {
          setTimeout(() => {
            if (back == true) {
              window.history.back() || window.location.replace('/');
            }

            if (typeof back == 'string' && back != '') {
              window.location.replace(back);
            }
          }, 3400);
        }
  
        if (response.success && multiStep) {
          this.$store.form.step++;
        }

        this.$store.form.success = response.success;
        let message = response.data.message;

        if (!message.title) {
          message.title = message.body;
          message.body = '';
        }

        this.$store.form.message = message;
      } catch (error) {}
  
      this.toggle();
    },
  
    toggle() {
      this.loading = !this.loading;
      this.$el.classList.toggle('opacity-60');
      this.$el.classList.toggle('pointer-events-none');
    }
  });
  