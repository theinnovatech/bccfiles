<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Document numbering settings (feedback item 4): manual or automatic per document type, with a
 * user-defined format and a preview before saving.
 */
class DocumentNumberController extends Controller
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'types' => $this->numbers->all(),
            'tokens' => ['{YYYY}' => 'Year (2026)', '{YY}' => 'Year (26)', '{MM}' => 'Month (01-12)', '{DD}' => 'Day (01-31)', '{SEQ}' => 'Sequence, zero-padded'],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(DocumentNumberService::TYPES))],
            'mode' => ['required', Rule::in(DocumentNumberService::MODES)],
            'format' => ['nullable', 'string', 'max:120'],
            'padding' => ['required', 'integer'],
            'reset_rule' => ['required', Rule::in(DocumentNumberService::RESET_RULES)],
        ]);

        $settings = $this->numbers->validateSettings($data);
        $today = now();
        $nextMonth = $today->copy()->addMonthNoOverflow()->startOfMonth();
        $nextYear = $today->copy()->addYear()->startOfYear();

        return response()->json([
            'mode' => $settings['mode'],
            'examples' => $settings['mode'] === 'auto' ? [
                'first_today' => $this->numbers->preview($settings, $today, 1),
                'second_today' => $this->numbers->preview($settings, $today, 2),
                'next_if_saved' => $this->numbers->peekNext($data['document_type'], $settings, $today),
                'after_reset' => match ($settings['reset_rule']) {
                    'monthly' => $this->numbers->preview($settings, $nextMonth, 1),
                    'yearly' => $this->numbers->preview($settings, $nextYear, 1),
                    default => null,
                },
                'after_reset_date' => match ($settings['reset_rule']) {
                    'monthly' => $nextMonth->toDateString(),
                    'yearly' => $nextYear->toDateString(),
                    default => null,
                },
            ] : null,
        ]);
    }

    public function update(Request $request, string $type): JsonResponse
    {
        abort_unless(array_key_exists($type, DocumentNumberService::TYPES), 404);

        $data = $request->validate([
            'mode' => ['required', Rule::in(DocumentNumberService::MODES)],
            'format' => ['nullable', 'string', 'max:120'],
            'padding' => ['required', 'integer'],
            'reset_rule' => ['required', Rule::in(DocumentNumberService::RESET_RULES)],
        ]);

        $saved = $this->numbers->save($type, $data, $request->user()->id);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Settings',
            sprintf('Document numbering for %s: %s%s', $saved['label'], $saved['mode'],
                $saved['mode'] === 'auto' ? " ({$saved['format']}, padding {$saved['padding']}, reset {$saved['reset_rule']})" : '')
        );

        return response()->json($saved + ['preview' => $this->numbers->preview($saved, Carbon::now())]);
    }
}
