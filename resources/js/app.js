import './bootstrap';

import { alert, remove , canvasDownload, convertDateToWords} from './helper';

window.alert = alert;
window.remove = remove;
window.convertDateToWords = convertDateToWords;

$('.download-js').on('click', function() {
    const target = $(this).data('target');
    const filename = $(this).data('filename');
    canvasDownload(target, filename);
});

$('.print-js').on('click', function() {
    window.print();
});