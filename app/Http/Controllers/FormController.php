<?php

namespace App\Http\Controllers;

use App\Models\Issuance;
use App\Models\OfficeFormDocument;
use App\Services\ActivityLogService;
use App\Services\FormService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Office forms (SDO_Reports_Reference_Pack): Local RIV and ICS per issuance, IIRUP as of a date.
 * PDF and Excel come from the same form data. Generating a form never changes records.
 */
class FormController extends Controller
{
    public function __construct(
        private readonly FormService $forms,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Whether the form was issued, and the draft values to confirm when issuing.
     */
    public function status(Request $request, string $form, Issuance $issuance): JsonResponse
    {
        $document = $this->forms->issuedDocument($form, $issuance);

        try {
            $draft = $document ? null : ($form === OfficeFormDocument::RIV
                ? $this->forms->riv($issuance, [], $request->user())
                : $this->forms->ics($issuance, [], $request->user()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['issued' => false, 'available' => false, 'message' => $e->getMessage()]);
        }

        return response()->json([
            'issued' => (bool) $document,
            'available' => true,
            'document_number' => $document?->document_number,
            'issued_at' => $document?->issued_at?->toIso8601String(),
            'issued_by' => $document?->issuer?->name,
            'draft' => $draft ? [
                'entity_name' => $draft['entity_name'] ?? null,
                'fund_cluster' => $draft['fund_cluster'] ?? null,
                'excluded' => $draft['excluded'] ?? [],
                'lines' => collect($draft['lines'])->map(fn ($line) => [
                    'key' => $line['key'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'useful_life' => $line['useful_life'] ?? null,
                    'unit_cost' => $line['unit_cost'],
                ])->values(),
            ] : null,
        ]);
    }

    public function issue(Request $request, string $form, Issuance $issuance): JsonResponse
    {
        $data = $request->validate(($form === OfficeFormDocument::ICS ? [
            'entity_name' => ['required', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.key' => ['required', 'string'],
            'lines.*.unit' => ['required', 'string', 'max:50'],
            'lines.*.useful_life' => ['nullable', 'string', 'max:50'],
        ] : []) + [
            'number' => ['nullable', 'string', 'max:255'],
        ], [
            'entity_name.required' => 'Confirm the entity name.',
            'lines.*.unit.required' => 'Enter the unit of measure for every line.',
        ]);

        $existing = $this->forms->issuedDocument($form, $issuance);

        try {
            $document = $this->forms->issue($form, $issuance, $request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $existing) {
            $this->activityLogService->log(
                $request->user(),
                'Issued',
                'Office Forms',
                sprintf('Issued %s %s for issuance %s', strtoupper($form), $document->document_number, $issuance->issuance_number)
            );
        }

        return response()->json([
            'document_number' => $document->document_number,
            'already_issued' => (bool) $existing,
            'message' => $existing
                ? "{$document->document_number} was already issued; reprints use the saved copy."
                : "{$document->document_number} issued. Reprints will use this saved copy.",
        ], $existing ? 200 : 201);
    }

    public function issuanceForm(Request $request, string $form, Issuance $issuance, string $format): Response
    {
        try {
            $data = $this->forms->forIssuance($form, $issuance, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->render($data, $format, $request->boolean('preview'));
    }

    /**
     * Issue an IIRUP as of a date (numbered, locked copy).
     */
    public function issueIirup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'as_of' => ['required', 'date', 'before_or_equal:today'],
            'number' => ['nullable', 'string', 'max:255'],
        ]);

        $document = $this->forms->issueIirup($data['as_of'], $request->user(), $data['number'] ?? null);

        $this->activityLogService->log(
            $request->user(),
            'Issued',
            'Office Forms',
            "Issued IIRUP {$document->document_number} as of {$data['as_of']}"
        );

        return response()->json([
            'id' => $document->id,
            'document_number' => $document->document_number,
            'message' => "IIRUP {$document->document_number} issued. Reprints use this saved copy.",
        ], 201);
    }

    public function iirupDocuments(): JsonResponse
    {
        return response()->json(
            OfficeFormDocument::query()
                ->with('issuer:id,name')
                ->where('form_type', 'iirup')
                ->orderByDesc('id')
                ->get(['id', 'document_number', 'as_of_date', 'issued_by', 'issued_at'])
        );
    }

    public function iirupDocument(Request $request, OfficeFormDocument $document, string $format): Response
    {
        abort_unless($document->form_type === 'iirup', 404);

        return $this->render($document->payload, $format, $request->boolean('preview'));
    }

    public function iirup(Request $request, string $format): Response
    {
        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        return $this->render($this->forms->iirup($filters['as_of'] ?? null, $request->user()), $format, $request->boolean('preview'));
    }

    private function render(array $form, string $format, bool $preview): Response
    {
        $name = match ($form['form']) {
            'riv' => 'Local-RIV-'.($form['number'] ?? 'DRAFT-'.$form['issuance_number']),
            'ics' => 'ICS-'.($form['number'] ?? 'DRAFT-'.$form['issuance_number']),
            default => 'IIRUP-'.($form['number'] ?? 'DRAFT-'.$form['as_of']),
        };
        $filename = Str::slug($name) ?: $form['form'];

        if ($format === 'xlsx') {
            return response($this->forms->toXlsx($form)->toString(), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
            ]);
        }

        if ($format !== 'pdf') {
            abort(404);
        }

        $pdf = Pdf::loadView('forms.'.$form['form'], [
            'form' => $form,
            'logos' => $this->logos(),
            'metaLine' => $this->forms->metaLine($form),
        ])->setPaper('a4', $form['form'] === 'iirup' ? 'landscape' : 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($preview ? 'inline' : 'attachment').'; filename="'.$filename.'.pdf"',
        ]);
    }

    /**
     * @return array{deped: string, sdo: string}
     */
    private function logos(): array
    {
        $encode = fn (string $path) => is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : '';

        return [
            'deped' => $encode(resource_path('forms/deped_seal.png')),
            'sdo' => $encode(resource_path('forms/sdo_iriga_seal.png')),
        ];
    }
}
