import './bootstrap';

import { alert, remove , canvasDownload} from './helper';

window.alert = alert;
window.remove = remove;

$('.download-js').on('click', function() {
    const target = $(this).data('target');
    const filename = $(this).data('filename');
    canvasDownload(target, filename);
});

$('.print-js').on('click', function() {
    window.print();
});