export default function socialLogin() {
    return {
        loading: false,
        handleRedirect(e) {
            if (this.loading) {
                e.preventDefault();
                return;
            }
            this.loading = true;
        }
    };
}
