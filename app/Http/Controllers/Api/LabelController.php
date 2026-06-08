<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function __construct(private readonly LabelService $labelService)
    {
    }

    // GET /api/tags
    public function index(Request $request): JsonResponse
    {
        $vault = $this->resolveVault($request);
        $labels = $this->labelService->listLabels($vault->id);
        return $this->success($labels, 'Labels retrieved successfully');
    }

    // POST /api/tags
    public function store(Request $request): JsonResponse
    {
        $vault = $this->resolveVault($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tag_category' => 'nullable|string|max:100',
            'bg_color' => 'nullable|string|max:50',
            'text_color' => 'nullable|string|max:50',
        ]);

        $label = $this->labelService->createLabel($vault->id, $validated);
        return $this->success($label, 'Label created successfully', 201);
    }

    // GET /api/tags/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        $vault = $this->resolveVault($request);
        $label = Label::forVault($vault->id)->findOrFail($id);
        return $this->success($label, 'Label retrieved successfully');
    }

    // PUT /api/tags/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $vault = $this->resolveVault($request);
        $label = Label::forVault($vault->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'tag_category' => 'nullable|string|max:100',
            'bg_color' => 'nullable|string|max:50',
            'text_color' => 'nullable|string|max:50',
        ]);

        $label = $this->labelService->updateLabel($label, $validated);
        return $this->success($label, 'Label updated successfully');
    }

    // DELETE /api/tags/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        $vault = $this->resolveVault($request);
        $label = Label::forVault($vault->id)->findOrFail($id);

        $request->validate([
            'reassign_to' => 'nullable|integer|exists:labels,id',
        ]);

        $this->labelService->deleteLabel($label, $request->integer('reassign_to') ?: null);
        return $this->success(null, 'Label deleted successfully');
    }

    // Resolve vault from authenticated user
    private function resolveVault(Request $request)
    {
        $vaultId = $request->query('vault_id') ?? $request->input('vault_id');

        if ($vaultId) {
            return $request->user()->account->vaults()->findOrFail($vaultId);
        }

        // Default: first vault of user's account
        return $request->user()->account->vaults()->firstOrFail();
    }

    // Consistent JSON envelope
    private function success(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
