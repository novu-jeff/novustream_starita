import './bootstrap';

import { alert, remove , canvasDownload, convertDateToWords} from './helper';
import { createApp } from 'vue';
import addConcessioner from './vue/addConcessioner.vue'

window.alert = alert;
window.remove = remove;
window.convertDateToWords = convertDateToWords;

// Authentication layout
const authApp = createApp({
    components: {
        addConcessioner,
    },
});

authApp.mount('#app');

$('.download-js').on('click', function() {
    const target = $(this).data('target');
    const filename = $(this).data('filename');
    canvasDownload(target, filename);
});

$('.print-js').on('click', function() {
    window.print();
});