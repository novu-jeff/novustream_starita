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
        },
        {
            type: 'warning', 
            background: '#f4b400', 
            color: '#fff',
            icon: {
                className: 'text-light bx bx-error-circle',
                tagName: 'i',
                text: ''
            },
            duration: 5000,
            dismissible: true
        }
    ]
});


export function alert(status, message) {
    if (status === 'success') {
        notyf.open({ type: 'success', message });
    } else if (status === 'error') {
        notyf.open({ type: 'error', message });
    } else if (status === 'warning') {
        notyf.open({ type: 'warning', message });
    }
}

export function remove(table = null, url, token) {
    Swal.fire({
        icon: "info",
        title: "Are your sure to delete?",
        text: `Please be informed that this action cannot be undone or reverted`,
        showCancelButton: true,
        confirmButtonText: "Yes, I Know",
        denyButtonText: `Don't save`,
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: url,
                type: "DELETE",
                data: {
                    _token: token,
                },
                success: function (result) {
                    if(!table) {
                        window.location.reload();
                    }
                    else {
                        alert(result.status, result.message);
                        table.ajax.reload();
                    }
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

    const element = document.querySelector(elem);

    if (!element) {
        console.error("Element not found");
        return;
    }

    html2canvas(element, {
        scale: 3, // Adjust for better resolution
        useCORS: true,
        allowTaint: true
    }).then(canvas => {
        const imgData = canvas.toDataURL("image/png", 1.0);

        const imgWidth = canvas.width * 0.264583; // Convert px to mm
        const imgHeight = canvas.height * 0.264583; 

        const doc = new jsPDF({
            orientation: imgWidth > imgHeight ? 'landscape' : 'portrait',
            unit: 'mm',
            format: [imgWidth, imgHeight]
        });

        doc.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
        doc.save(`${filename}.pdf`);
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