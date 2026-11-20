<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogService;
use App\Services\PagePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    private const DEFINITIONS = [
        'organization_name' => [
            'label' => 'Organization name',
            'description' => 'Shown in the sidebar and browser tab. Use your office or division name.',
            'group' => 'general',
            'type' => 'text',
            'placeholder' => 'e.g. Supply Unit Office',
        ],
        'low_stock_percent_default' => [
            'label' => 'Default low-stock percentage',
            'description' => 'Low-stock threshold = reference stock x this percentage / 100, for items without their own percentage. Between 1 and 100. Example: reference 1,000 at 50% gives a threshold of 500.',
            'group' => 'inventory',
            'type' => 'text',
            'placeholder' => '50',
        ],
        'allow_negative_stock' => [
            'label' => 'Allow stock below zero',
            'description' => 'When enabled, issuing items can reduce stock below zero. When disabled, the system blocks issuance if there is not enough stock.',
            'group' => 'inventory',
            'type' => 'boolean',
        ],
        'form_entity_name' => [
            'label' => 'Entity name',
            'description' => 'Printed as Entity Name on the forms. Defaults to the organization name.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_fund_cluster' => [
            'label' => 'Fund cluster',
            'description' => 'Printed as Fund Cluster.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_approved_by_name' => [
            'label' => 'Approved by — name',
            'description' => 'APPROVED on the Local RIV and Approved by on the IIRUP.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_approved_by_designation' => [
            'label' => 'Approved by — designation',
            'description' => null,
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_issued_by_name' => [
            'label' => 'Supply officer / accountable officer — name',
            'description' => 'Accountable officer on the IIRUP. The ICS "Received from" prints the staff member who processed the issuance.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_issued_by_designation' => [
            'label' => 'Supply officer / accountable officer — designation',
            'description' => null,
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_inspection_officer_name' => [
            'label' => 'Inspection officer — name',
            'description' => 'IIRUP inspection officer.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_inspection_officer_designation' => [
            'label' => 'Inspection officer — designation',
            'description' => null,
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_agency' => [
            'label' => 'Stock Card agency',
            'description' => 'Agency line printed on the Stock Card. Defaults to the entity name.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_station' => [
            'label' => 'IIRUP station',
            'description' => 'Station of the accountable officer on the IIRUP, e.g. Iriga City.',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_witness_name' => [
            'label' => 'IIRUP witness — name',
            'description' => 'Witness of the disposition (IIRUP).',
            'group' => 'forms',
            'type' => 'text',
        ],
        'form_witness_designation' => [
            'label' => 'IIRUP witness — designation',
            'description' => null,
            'group' => 'forms',
            'type' => 'text',
        ],
        'department_user_pages' => [
            'label' => 'Pages employees can open',
            'description' => 'Turn on the screens that department user accounts may access after login. Dashboard stays on for everyone.',
            'group' => 'permissions',
            'type' => 'page_list',
        ],
    ];

    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get();

        return response()->json(
            $settings->map(fn (Setting $setting) => $this->present($setting))->values()
        );
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.group' => ['nullable', 'string'],
        ]);

        // Validate the percentage before anything is written, so a rejected save changes nothing.
        foreach ($data['settings'] as $setting) {
            if ($setting['key'] === 'low_stock_percent_default') {
                $percent = is_numeric($setting['value'] ?? null) ? (float) $setting['value'] : null;
                if ($percent === null || $percent < 1 || $percent > 100) {
                    throw ValidationException::withMessages([
                        'settings' => ['The default low-stock percentage must be a number between 1 and 100.'],
                    ]);
                }
            }
        }

        foreach ($data['settings'] as $setting) {
            $definition = self::DEFINITIONS[$setting['key']] ?? null;
            $group = $setting['group'] ?? $definition['group'] ?? 'general';
            $value = $setting['value'] ?? null;

            if (($definition['type'] ?? 'text') === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }

            if ($setting['key'] === PagePermissionService::SETTING_KEY
                || ($definition['type'] ?? null) === 'page_list') {
                $pages = $this->decodePageList($value);
                $allowed = PagePermissionService::setDepartmentUserPages($pages);
                $value = json_encode($allowed);
                $group = 'permissions';
            }

            if ($setting['key'] === 'low_stock_percent_default') {
                $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
                $percentChanged = Setting::getValue('low_stock_percent_default') !== $value;
            }

            Setting::setValue($setting['key'], $value, $group);
        }

        if ($percentChanged ?? false) {
            // Threshold setting changed: recalculate every item's status and alert.
            \App\Support\StockStatus::flushDefault();
            app(\App\Services\LowStockAlertService::class)->scanAllActiveItems();
        }

        $this->activityLogService->log($request->user(), 'Updated', 'Settings', 'Updated system settings');

        return response()->json(
            Setting::query()->orderBy('group')->orderBy('key')->get()
                ->map(fn (Setting $setting) => $this->present($setting))
                ->values()
        );
    }

    private function present(Setting $setting): array
    {
        $definition = self::DEFINITIONS[$setting->key] ?? [
            'label' => ucwords(str_replace('_', ' ', $setting->key)),
            'description' => null,
            'group' => $setting->group ?? 'general',
            'type' => 'text',
            'placeholder' => null,
        ];

        return [
            'key' => $setting->key,
            'value' => $setting->value,
            'group' => $setting->group ?? $definition['group'],
            'label' => $definition['label'],
            'description' => $definition['description'],
            'type' => $definition['type'],
            'placeholder' => $definition['placeholder'] ?? null,
            'options' => ($definition['type'] ?? null) === 'page_list'
                ? PagePermissionService::grantablePages()
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function decodePageList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return PagePermissionService::DEFAULT_PAGES;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'settings' => ['Employee page permissions must be a valid page list.'],
            ]);
        }

        return array_values(array_map('strval', $decoded));
    }
}
