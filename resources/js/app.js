import './password-toggle';
import axios from 'axios';
import { alert, remove , canvasDownload, convertDateToWords} from './helper';
import { createApp } from 'vue';
import addConcessioner from './vue/addConcessioner.vue'

window.axios = axios;
window.alert = alert;
window.remove = remove;
window.convertDateToWords = convertDateToWords;

const vueRoot = document.getElementById('app');

// Only mount Vue on pages that use its concessionaire form component.
// Mounting an empty app on every page re-renders the server HTML and removes
// page-specific inline styles after the initial render.
if (vueRoot?.querySelector('add-concessioner')) {
    const authApp = createApp({
        components: {
            addConcessioner,
        },
    });

    authApp.mount('#app');
}

$('.download-js').on('click', function() {
    const target = $(this).data('target');
    const filename = $(this).data('filename');
    canvasDownload(target, filename);
});

$('.print-js').on('click', function() {
    window.print();
});

$('.btn-navigate, .close-icon').on('click', function() {
    $('.header-navigation').toggleClass('active');
});

