<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        'allow_negative_stock' => [
            'label' => 'Allow stock below zero',
            'description' => 'When enabled, issuing items can reduce stock below zero. When disabled, the system blocks issuance if there is not enough stock.',
            'group' => 'inventory',
            'type' => 'boolean',
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

        foreach ($data['settings'] as $setting) {
            $definition = self::DEFINITIONS[$setting['key']] ?? null;
            $group = $setting['group'] ?? $definition['group'] ?? 'general';
            $value = $setting['value'] ?? null;

            if (($definition['type'] ?? 'text') === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }

            Setting::setValue($setting['key'], $value, $group);
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
        ];
    }
}
