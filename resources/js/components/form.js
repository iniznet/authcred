export default (action, back = true, multiStep = false) => ({
    loading: false,
    success: null,
    message: {},
  
    async dispatch() {
      if (this.loading) {
        return;
      }
  
      this.toggle();
      this.success = null;
  
      try {
        const data = new FormData(this.$el);
        data.append('action', action);
  
        const response = await this.$ajax(data);
  
        if (response.success && back) {
          setTimeout(() => {
            window.history.back() || window.location.replace('/');
          }, 3400);
        }
  
        if (response.success && multiStep) {
          this.$store.form.step++;
        }

        this.success = response.success;
        let message = response.data.message;

        if (!message.title) {
          message.title = message.body;
          message.body = '';
        }

        this.message = message;
      } catch (error) {}
  
      this.toggle();
    },
  
    toggle() {
      this.loading = !this.loading;
      this.$el.classList.toggle('opacity-60');
      this.$el.classList.toggle('pointer-events-none');
    }
  });
  