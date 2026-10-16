@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <div>
                    <h1>Import Previous Billing</h1>
                    <p class="text-muted mt-2 mb-0">
                        Upload an Excel workbook containing previous billing records by zone.
                    </p>
                </div>
            </div>

            <div class="inner-content mt-4 pb-5">
                <div class="card import-upload-card">
                    <div class="card-header py-3 px-4">
                        <h5 class="mb-1 fw-bold">Upload billing workbook</h5>
                        <p class="small text-muted mb-0">
                            Only Excel workbooks in .xlsx format are supported.
                        </p>
                    </div>

                    <div class="card-body p-4">
                        <form id="previousBillingImportForm" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-4">
                                <label for="file" class="form-label">CSV or Excel file</label>
                                <input
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    class="form-control @error('file') is-invalid @enderror"
                                    id="file"
                                    name="file"
                                    required
                                    aria-describedby="billingFileHelp"
                                >
                                <div id="billingFileHelp" class="form-text mt-2">
                                    Select the workbook you want to import.
                                </div>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="warning" aria-live="polite" aria-atomic="false"></div>

                            <section class="import-types rounded-3 border p-3 p-md-4 mt-3" aria-labelledby="workbookRequirementsTitle">
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <div class="import-types-icon rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
                                        <i class="bx bx-spreadsheet fs-3"></i>
                                    </div>
                                    <div>
                                        <h2 id="workbookRequirementsTitle" class="h6 fw-bold mb-1">Workbook requirements</h2>
                                        <p class="text-muted small mb-0">
                                            Check the workbook layout and required columns before uploading.
                                        </p>
                                    </div>
                                </div>

                                <h3 class="small fw-bold mb-2">Required worksheet headings</h3>
                                <div class="import-heading-list d-flex flex-wrap gap-2 mb-3">
                                    @foreach ([
                                        'reference_no',
                                        'account_no',
                                        'billing_from',
                                        'billing_to',
                                        'previous_reading',
                                        'present_reading',
                                        'consumption',
                                        'penalty',
                                        'unpaid',
                                        'arrears',
                                        'current_bill',
                                        'amount_paid',
                                        'date_paid',
                                        'due_date',
                                        'payor_name',
                                        'payment_reference_no',
                                    ] as $heading)
                                        <code class="import-heading-chip">{{ $heading }}</code>
                                    @endforeach
                                </div>

                                <div class="import-requirement-note rounded-3 p-3 mb-3">
                                    <h3 class="small fw-bold mb-1">Required values per record</h3>
                                    <p class="small mb-0">
                                        Each record must include <code>reference_no</code>, <code>account_no</code>,
                                        <code>billing_from</code>, and <code>billing_to</code>. Use numeric values for
                                        reading, amount, penalty, and payment columns.
                                    </p>
                                </div>

                                <p class="small text-muted mb-0">
                                    <i class="bx bx-info-circle me-1" aria-hidden="true"></i>
                                    The current importer requires the <code>unpaid</code> and
                                    <code>payment_reference_no</code> headings but does not use their cell values.
                                    Imported bills currently store penalty as 0.
                                </p>
                            </section>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="showBtn btn btn-primary px-4 py-2">
                                    <i class="bx bx-upload me-1" aria-hidden="true"></i>
                                    <span class="submit-label">Import billing</span>
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

        const $form = $("#previousBillingImportForm");
        const $submitButton = $form.find(".showBtn");
        const originalButtonHTML = $submitButton.html();

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

            const selectedFile = fileInput.files[0];
            if (!/\.xlsx$/i.test(selectedFile.name)) {
                alert("warning", "Please select an Excel workbook with the .xlsx extension.");
                fileInput.value = "";
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
                        alert(safeStatus, `${sheet}: ${message}`);
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
                                            ${safeErrors.map(err => {
                                                const errorText = typeof err === "string"
                                                    ? err
                                                    : `Row ${err?.row ?? "?"} - ${err?.reason ?? "Unknown error"}`;
                                                return `<li class="mb-1">${escapeHTML(errorText)}</li>`;
                                            }).join("")}
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
                const message = error?.response?.data?.message || "Something went wrong while uploading. Please check the workbook and try again.";
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
