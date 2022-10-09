import Alpine from 'alpinejs';
import Form from './components/form';
import AjaxMagic from './magics/ajax';

const alpine = () => {
    document.addEventListener('alpine:init', () => {
        Alpine.magic('ajax', () =>  AjaxMagic);

        Alpine.store('form', {
            step: parseInt(location.hash.substring(1).split('?')[0]) || 1,
        });

        Alpine.data('form', Form);
    });

    Alpine.start();
};

alpine();