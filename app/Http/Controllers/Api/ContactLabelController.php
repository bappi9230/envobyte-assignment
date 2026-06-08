<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactLabelController extends Controller
{
    public function __construct(private readonly LabelService $labelService)
    {
    }

    // GET /api/contacts?tags[]=id1&tags[]=id2
    public function index(Request $request): JsonResponse
    {
        $vault = $this->resolveVault($request);

        $validated = $request->validate([
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'sort' => 'nullable|in:first_name,-first_name,created_at,-created_at',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Contact::where('vault_id', $vault->id);

        // AND filtering — chained whereHas, single SQL, no result looping
        if (!empty($validated['tags'])) {
            foreach ($validated['tags'] as $labelId) {
                $query->whereHas('labels', fn($q) => $q->where('labels.id', $labelId));
            }
        }

        $sort = $validated['sort'] ?? 'first_name';
        if (str_starts_with($sort, '-')) {
            $query->orderBy(ltrim($sort, '-'), 'desc');
        } else {
            $query->orderBy($sort, 'asc');
        }

        $query->with('labels');
        $contacts = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'success' => true,
            'message' => 'Contacts retrieved successfully',
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    // POST /api/contacts/{contactId}/tags
    public function attach(Request $request, string $contactId): JsonResponse
    {
        $vault = $this->resolveVault($request);
        $contact = Contact::where('vault_id', $vault->id)->findOrFail($contactId);

        $validated = $request->validate([
            'tag_ids' => 'required|array|min:1',
            'tag_ids.*' => 'string|exists:labels,id',
        ]);

        $this->labelService->attachLabels($contact, $validated['tag_ids'], $vault->id);
        $contact->load('labels');

        return response()->json([
            'success' => true,
            'message' => 'Tags attached successfully',
            'data' => [
                'contact_id' => $contact->id,
                'labels' => $contact->labels,
            ],
        ]);
    }

    // DELETE /api/contacts/{contactId}/tags/{labelId}
    public function detach(Request $request, string $contactId, string $labelId): JsonResponse
    {
        $vault = $this->resolveVault($request);
        $contact = Contact::where('vault_id', $vault->id)->findOrFail($contactId);

        $this->labelService->detachLabel($contact, $labelId, $vault->id);

        return response()->json([
            'success' => true,
            'message' => 'Tag detached successfully',
        ]);
    }

    // Resolve vault from authenticated user
    private function resolveVault(Request $request)
    {
        $vaultId = $request->query('vault_id') ?? $request->input('vault_id');

        if ($vaultId) {
            return $request->user()->account->vaults()->findOrFail($vaultId);
        }

        return $request->user()->account->vaults()->firstOrFail();
    }
}
