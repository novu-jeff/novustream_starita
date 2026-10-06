<?php

namespace App\Http\Controllers;

use App\Models\ServiceApplication;
use App\Models\ConcessionerAccountLink;
use App\Models\UserAccounts;
use Illuminate\Support\Facades\Auth;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class RegistrantDocumentController extends Controller
{
    private const APPLICATION_DOCUMENTS = [
        'valid_id' => 'valid_id',
        'cedula' => 'cedula',
        'proof_of_billing' => 'proof_of_billing',
        'authorization_letter' => 'authorization_letter',
        'boring_permit' => 'boring_permit',
        'proof_of_ownership' => 'proof_of_ownership',
        'tax_declaration' => 'tax_declaration',
        'barangay_clearance' => 'barangay_clearance',
        'others' => 'others',
    ];

    public function viewApplicationDocument(ServiceApplication $application, string $field)
    {
        $this->authorizeApplication($application);
        abort_unless(isset(self::APPLICATION_DOCUMENTS[$field]), 404);

        $path = $application->documents?->{self::APPLICATION_DOCUMENTS[$field]};

        return $this->inlineFile($path);
    }

    public function viewRegistrantDocument(int $account, string $field)
    {
        abort_unless(Auth::guard('admins')->check(), 403);
        abort_unless(in_array($field, ['soa', ...array_keys(self::APPLICATION_DOCUMENTS)], true), 404);

        $account = UserAccounts::with(['user.serviceApplications' => function ($query) {
            $query->with('documents')->latest();
        }])->findOrFail($account);
        $application = $account->user?->serviceApplications?->first();

        $path = match ($field) {
            'soa' => $account->application_soa_path,
            'valid_id' => $application?->documents?->valid_id ?: $account->application_id_path,
            default => $application?->documents?->{$field},
        };

        return $this->inlineFile($path);
    }

    public function viewAccountLinkDocument(int $link, string $field)
    {
        abort_unless(Auth::guard('admins')->check(), 403);
        abort_unless(in_array($field, ['soa', 'valid_id'], true), 404);

        $accountLink = ConcessionerAccountLink::findOrFail($link);
        $path = $field === 'soa' ? $accountLink->soa_path : $accountLink->id_path;

        return $this->inlineFile($path);
    }

    public function applicationForm(int $account)
    {
        abort_unless(Auth::guard('admins')->check(), 403);

        $account = UserAccounts::with('user.serviceApplications')->findOrFail($account);
        abort_unless($account->application_type === 'new_connection', 404);

        $application = $account->user?->serviceApplications?->sortByDesc('created_at')->first();

        return view('application.print', [
            'printData' => $this->applicationFormData($account, $application),
            'autoPrint' => false,
        ]);
    }

    public function downloadRegistrantDocuments(int $account)
    {
        abort_unless(Auth::guard('admins')->check(), 403);

        $account = UserAccounts::with(['user.serviceApplications' => function ($query) {
            $query->with('documents')->latest();
        }])->findOrFail($account);
        $documentPaths = [
            $account->application_soa_path,
            $account->application_id_path,
        ];

        foreach ($account->user?->serviceApplications ?? [] as $serviceApplication) {
            foreach (self::APPLICATION_DOCUMENTS as $field) {
                $documentPaths[] = $serviceApplication->documents?->{$field};
            }
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $addedPages = 0;
        $includedPaths = [];

        foreach ($documentPaths as $path) {
            if (!$path || isset($includedPaths[$path])) {
                continue;
            }

            $filePath = $this->publicFilePath($path);
            if (!$filePath) {
                continue;
            }

            $includedPaths[$path] = true;
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $addedPages += $this->appendPdf($pdf, $filePath);
                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                $imageSize = @getimagesize($filePath);
                if (!$imageSize) {
                    continue;
                }

                $pdf->AddPage('P', 'A4');
                $ratio = min(190 / $imageSize[0], 277 / $imageSize[1]);
                $width = $imageSize[0] * $ratio;
                $height = $imageSize[1] * $ratio;
                $pdf->Image($filePath, (210 - $width) / 2, (297 - $height) / 2, $width, $height);
                $addedPages++;
            }
        }

        abort_if($addedPages === 0, 404, 'No downloadable documents were found for this record.');

        $filename = (preg_replace('/[^A-Za-z0-9._-]/', '-', $account->account_no) ?: 'account') . '-documents.pdf';
        $content = $pdf->Output('S');

        return response()->streamDownload(static function () use ($content) {
            echo $content;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function authorizeApplication(ServiceApplication $application): void
    {
        abort_unless(
            Auth::guard('admins')->check() || (int) $application->user_id === (int) Auth::id(),
            403
        );
    }

    private function inlineFile(?string $path)
    {
        $filePath = $this->publicFilePath($path);
        abort_unless($filePath, 404);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        if (in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            $image = base64_encode(file_get_contents($filePath));
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                . '<style>html,body{width:100%;height:100%;margin:0}body{display:flex;'
                . 'align-items:center;justify-content:center;background:#f1f3f5;overflow:hidden}'
                . 'img{display:block;max-width:100%;max-height:100%;object-fit:contain}</style>'
                . '</head><body><img alt="Document preview" src="data:' . $mimeType
                . ';base64,' . $image . '"></body></html>';

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function publicFilePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $publicRoot = realpath(storage_path('app/public'));
        $filePath = realpath(storage_path('app/public/' . ltrim($path, '/')));

        if (!$publicRoot || !$filePath || !is_file($filePath)
            || !str_starts_with($filePath, $publicRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $filePath;
    }

    private function applicationFormData(UserAccounts $account, ?ServiceApplication $application): array
    {
        $user = $account->user;

        return [
            'sc_no' => $account->sc_no ?? '',
            'meter_no' => $account->meter_serial_no ?? '',
            'account_no' => str_starts_with((string) $account->account_no, 'NEW-') ? '' : ($account->account_no ?? ''),
            'cellphone' => $application?->cellphone ?? $user?->contact_no ?? '',
            'applicant_name' => $application?->applicant_name ?? $user?->registrants ?? $user?->name ?? '',
            'service_address' => $application?->service_address ?? $account->address ?? '',
            'application_type' => $application?->application_type ?? 'Water Service Connection',
            'connection_size' => $application?->connection_size ?? '',
            'installation_location' => $application?->installation_location ?? $account->address ?? '',
            'signature_name' => $application?->applicant_name ?? $user?->registrants ?? $user?->name ?? '',
            'application_date' => optional($application?->created_at ?? $account->created_at)->format('Y-m-d'),
            'property_owner' => $application?->property_owner ?? $user?->registrants ?? $user?->name ?? '',
            'promissory_amount' => $application?->promissory_amount ?? '',
        ];
    }

    private function appendPdf(Fpdi $pdf, string $source, bool $isContent = false): int
    {
        $pageCount = $pdf->setSourceFile($isContent ? StreamReader::createByString($source) : $source);

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }

        return $pageCount;
    }
}