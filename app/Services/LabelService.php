<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Contact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LabelService
{
    // Cache key format: labels:vault:{vault_id}:list
    private function cacheKey(string $vaultId): string
    {
        return "labels:vault:{$vaultId}:list";
    }

    // Get all labels with usage count — Redis cached for 10 minutes
    public function listLabels(string $vaultId): array
    {
        return Cache::remember(
            $this->cacheKey($vaultId),
            600,
            fn() => Label::forVault($vaultId)
                ->withUsageCount()
                ->orderBy('name')
                ->get()
                ->toArray()
        );
    }

    // Create a new label and bust cache
    public function createLabel(string $vaultId, array $data): Label
    {
        $label = Label::create([
            'vault_id' => $vaultId,
            'name' => $data['name'],
            'slug' => str($data['name'])->slug(),
            'tag_category' => $data['tag_category'] ?? null,
            'bg_color' => $data['bg_color'] ?? 'bg-zinc-200',
            'text_color' => $data['text_color'] ?? 'text-zinc-700',
        ]);

        $this->invalidateCache($vaultId);
        return $label;
    }

    // Update a label and bust cache
    public function updateLabel(Label $label, array $data): Label
    {
        $label->update([
            'name' => $data['name'] ?? $label->name,
            'tag_category' => $data['tag_category'] ?? $label->tag_category,
            'bg_color' => $data['bg_color'] ?? $label->bg_color,
            'text_color' => $data['text_color'] ?? $label->text_color,
        ]);

        $this->invalidateCache($label->vault_id);
        return $label->fresh();
    }

    // Delete a label — optionally reassign contacts to another label
    public function deleteLabel(Label $label, ?int $reassignToLabelId = null): void
    {
        if ($reassignToLabelId) {
            DB::statement("
                INSERT IGNORE INTO contact_label (label_id, contact_id, created_at, updated_at)
                SELECT ?, contact_id, NOW(), NOW()
                FROM contact_label
                WHERE label_id = ?
            ", [$reassignToLabelId, $label->id]);
        }

        $label->delete();
        $this->invalidateCache($label->vault_id);
    }

    // Attach multiple labels to a contact
    public function attachLabels(Contact $contact, array $labelIds, string $vaultId): void
    {
        $validIds = Label::forVault($vaultId)
            ->whereIn('id', $labelIds)
            ->pluck('id')
            ->toArray();

        $contact->labels()->syncWithoutDetaching($validIds);
        $this->invalidateCache($vaultId);
    }

    // Detach a label from a contact
    public function detachLabel(Contact $contact, int $labelId, string $vaultId): void
    {
        $contact->labels()->detach($labelId);
        $this->invalidateCache($vaultId);
    }

    // Invalidate cache — called on every create/update/delete/attach/detach
    public function invalidateCache(string $vaultId): void
    {
        Cache::forget($this->cacheKey($vaultId));
    }
}
