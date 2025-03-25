const notyf = new Notyf({
    position: {
        x: 'right',
        y: 'top'
    },
    types: [
        {
            type: 'error',
            background: 'indianred',
            duration: 5000,
            dismissible: true
        },
        {
            type: 'success',
            background: '#6c4abf',
            duration: 5000,
            dismissible: true
        }
    ]
});


export function alert(status, message) {

    if(status == 'success') {
        notyf.success(message);
    }

    if(status == 'error') {
        notyf.error(message);
    }

}

export function remove(table, url, token) {
    Swal.fire({
        icon: "info",
        title: "Are your sure to delete?",
        text: `Please be informed that this action cannot be undone or reverted`,
        showCancelButton: true,
        confirmButtonText: "Yes, I Know",
        denyButtonText: `Don't save`,
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            $.ajax({
                url: url,
                type: "DELETE",
                data: {
                    _token: token,
                },
                success: function (result) {
                    alert(result.status, result.message);
                    table.ajax.reload();
                },
                error: function (xhr) {
                    alert(
                        "An error occurred while trying to delete the property type."
                    );
                },
            });
        }
    });
}

export function canvasDownload(elem, filename) { 
    const { jsPDF } = window.jspdf;

    let pageWidth = 100; 
    let pageHeight = 210; 

    html2canvas($(elem)[0], {
        scale: 5, 
        useCORS: true, 
        allowTaint: true
    }).then(canvas => {

        let imgData = canvas.toDataURL("image/png");

        let imgWidth = 450;  
        let imgHeight = pageHeight; 

        let doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: [pageWidth, pageHeight] 
        });

        let xPos = (pageWidth - imgWidth) / 2; 
        let yPos = (pageHeight - imgHeight) / 2; 

        doc.addImage(imgData, 'PNG', xPos, yPos, imgWidth, imgHeight);
        doc.save(filename + ".pdf");
        
    });
}

export function convertDateToWords(dateString) {
    console.log(dateString);
    var months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    var parts = dateString.split('-');
    if (parts.length !== 3) return "Invalid date format";
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10);
    var day = parseInt(parts[2], 10);

    if (!year || !month || !day) return "Invalid date"; 

    return months[month - 1] + " " + day + ", " + year;
}