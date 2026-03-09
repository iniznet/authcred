import Alpine from 'alpinejs';
import CaptchaForm from './components/captcha';
import Form from './components/form';
import AjaxMagic from './magics/ajax';
import AvatarUpload from './components/avatar-upload';
import SocialLogin from './components/social-login';

const alpine = () => {
    document.addEventListener('alpine:init', () => {
        Alpine.magic('ajax', () =>  AjaxMagic);

        Alpine.store('form', {
            success: null,
            message: {},
            step: parseInt(location.hash.substring(1).split('?')[0]) || 1,
        });

        Alpine.data('captchaForm', CaptchaForm);
        Alpine.data('form', Form);
        Alpine.data('avatarUpload', AvatarUpload);
        Alpine.data('socialLogin', SocialLogin);
    });

    Alpine.start();
};

alpine();
