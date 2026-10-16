@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <div>
                    <h1>Import Files</h1>
                    <p class="text-muted mt-2 mb-0">
                        Upload a CSV or Excel workbook to import supported system data.
                    </p>
                </div>
            </div>

            <div class="inner-content mt-4 pb-5">
                <div class="card import-upload-card">
                    <div class="card-header py-3 px-4">
                        <h5 class="mb-1 fw-bold">Upload an import file</h5>
                        <p class="small text-muted mb-0">
                            Choose a file from your device, then submit it for processing.
                        </p>
                    </div>

                    <div class="card-body p-4">
                        <form id="importForm" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-4">
                                <label for="file" class="form-label">CSV or Excel file</label>
                                <input
                                    type="file"
                                    class="form-control @error('file') is-invalid @enderror"
                                    id="file"
                                    name="file"
                                    accept=".csv,.xls,.xlsx"
                                    required
                                    aria-describedby="fileHelp"
                                >

                                <div id="fileHelp" class="form-text mt-2">
                                    Supported formats: CSV, XLS, and XLSX.
                                </div>

                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="import-types rounded-3 border p-3 p-md-4 mb-4">
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <div class="import-types-icon rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
                                        <i class="bx bx-spreadsheet fs-3"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">What can you import?</h6>
                                        <p class="text-muted small mb-0">
                                            The available data types depend on the worksheets and columns in your file.
                                        </p>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    @foreach ([
                                        'Client Information',
                                        'Admin Accounts',
                                        'Technician Accounts',
                                        'Concessionaire Information',
                                        'Advances',
                                        'Outstanding Balance',
                                        'Senior Citizen Discounts',
                                        'Rates / Rate Codes',
                                        'Status Codes',
                                        'Zones',
                                        'Settings',
                                    ] as $item)
                                        <div class="col-12 col-sm-6 col-lg-4">
                                            <div class="import-type-item d-flex align-items-center gap-2 h-100 rounded-2 px-3 py-2">
                                                <i class="bx bx-check-circle" aria-hidden="true"></i>
                                                <span>{{ $item }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="warning" aria-live="polite" aria-atomic="false"></div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="showBtn btn btn-primary px-4 py-2">
                                    <i class="bx bx-upload me-1" aria-hidden="true"></i>
                                    <span class="submit-label">Submit import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
    <script>
        $(function () {
            @if (session('alert'))
                setTimeout(() => {
                    const alertData = @json(session('alert'));
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif

            const $form = $("#importForm");
            const $submitButton = $form.find(".showBtn");
            const originalButtonHTML = $submitButton.html();

            // Escape server-provided strings before inserting them into HTML.
            function escapeHTML(value) {
                return String(value ?? "").replace(/[&<>"']/g, function (character) {
                    return {
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        '"': "&quot;",
                        "'": "&#039;"
                    }[character];
                });
            }

            $form.on("submit", function (e) {
                e.preventDefault();

                const fileInput = document.getElementById("file");
                if (!fileInput.files || !fileInput.files.length) {
                    fileInput.reportValidity();
                    return;
                }

                showLoader();
                $submitButton.prop("disabled", true);
                $(".warning").empty();

                const formData = new FormData(this);

                axios.post("{{ route(Route::currentRouteName()) }}", formData, {
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                        "Content-Type": "multipart/form-data"
                    }
                })
                .then(response => {
                    const res = response.data || {};
                    const messages = Array.isArray(res.messages) ? res.messages : [];

                    let displayHTML = "";
                    let delay = 0;

                    messages.forEach(({ sheet = "Import result", status = "error", message = "", errors = [] }) => {
                        const safeStatus = ["success", "warning", "error"].includes(status) ? status : "error";
                        const borderColor = safeStatus === "success" ? "success" : (safeStatus === "warning" ? "warning" : "danger");
                        const textColor = safeStatus === "success" ? "text-success" : (safeStatus === "warning" ? "text-warning" : "text-danger");
                        const safeErrors = Array.isArray(errors) ? errors : [];

                        setTimeout(() => {
                            alert(safeStatus, String(message));
                        }, delay);
                        delay += 800;

                        displayHTML += `
                            <div class="card import-result-card mb-3 border-start border-3 border-${borderColor}">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold mb-2 ${textColor}">${escapeHTML(sheet)}</h6>
                                    <p class="text-muted mb-2">${escapeHTML(message)}</p>
                                    ${safeErrors.length ? `
                                        <div class="import-result-errors rounded-2 p-3">
                                            <ul class="list-unstyled small mb-0 ${textColor}">
                                                ${safeErrors.map(err => `<li class="mb-1">${escapeHTML(err)}</li>`).join("")}
                                            </ul>
                                        </div>` : ""
                                    }
                                </div>
                            </div>
                        `;
                    });

                    if (displayHTML) {
                        $(".warning").html(`<div class="mt-4"><h6 class="fw-bold mb-3">Import results</h6>${displayHTML}</div>`);
                    } else {
                        alert("warning", res.message || "The server did not return any import results.");
                    }
                })
                .catch(error => {
                    const message = error?.response?.data?.message || "Something went wrong while uploading. Please check the file and try again.";
                    alert("error", message);
                })
                .finally(() => {
                    hideLoader();
                    $submitButton.prop("disabled", false);
                });
            });

            function showLoader() {
                $submitButton.html("<i class='bx bx-loader-alt bx-spin' aria-hidden='true'></i><span class='ms-1'>Importing...</span>");
            }

            function hideLoader() {
                $submitButton.html(originalButtonHTML);
            }
        });
    </script>
@endsection
