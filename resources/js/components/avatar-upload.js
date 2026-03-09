export default function avatarUpload(config) {
    return {
        ajaxUrl: config.ajaxUrl,
        uploadNonce: config.uploadNonce,
        removeNonce: config.removeNonce,
        currentAvatarUrl: config.initialAvatarUrl,
        gravatarUrl: config.gravatarUrl,
        hasLocal: config.hasLocalAvatar,
        maxSizeKb: config.maxSizeKb,
        loading: false,
        error: '',

        async uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate size
            if (file.size > this.maxSizeKb * 1024) {
                this.error = 'File too large.';
                return;
            }

            this.error = '';
            this.loading = true;

            const formData = new FormData();
            formData.append('action', 'authcred_upload_avatar');
            formData.append('nonce', this.uploadNonce);
            formData.append('avatar', file);

            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                });
                const result = await response.json();

                if (result.success && result.data && result.data.avatar) {
                    this.currentAvatarUrl = result.data.avatar.avatar_url;
                    this.hasLocal = result.data.avatar.has_local_avatar;
                    this.error = '';
                } else if (!result.success && result.data && result.data.message) {
                    this.error = result.data.message.body || 'Upload failed.';
                } else {
                    this.error = 'Upload failed.';
                }
            } catch (err) {
                this.error = 'Network error during upload.';
            } finally {
                this.loading = false;
                event.target.value = ''; // Reset input
            }
        },

        async removeAvatar() {
            this.loading = true;
            this.error = '';

            const formData = new FormData();
            formData.append('action', 'authcred_remove_avatar');
            formData.append('nonce', this.removeNonce);

            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                });
                const result = await response.json();

                if (result.success && result.data && result.data.avatar) {
                    this.currentAvatarUrl = result.data.avatar.avatar_url;
                    this.hasLocal = result.data.avatar.has_local_avatar;
                } else if (!result.success && result.data && result.data.message) {
                    this.error = result.data.message.body || 'Removal failed.';
                } else {
                    this.error = 'Removal failed.';
                }
            } catch (err) {
                this.error = 'Network error during removal.';
            } finally {
                this.loading = false;
            }
        }
    };
}
