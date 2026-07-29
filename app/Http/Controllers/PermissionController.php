<?php

namespace App\Http\Controllers;

use App\Services\PagePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'pages' => PagePermissionService::grantablePages(),
            'allowed' => PagePermissionService::allowedPagesForDepartmentUsers(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pages' => ['required', 'array'],
            'pages.*' => ['string', 'in:'.implode(',', PagePermissionService::grantableKeys())],
        ]);

        $allowed = PagePermissionService::setDepartmentUserPages($validated['pages']);

        return response()->json([
            'message' => 'Employee page permissions updated.',
            'allowed' => $allowed,
        ]);
    }
}
