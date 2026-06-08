# Envobyte Assignment — Monica CRM Tag System Extension

## Approach

Monica CRM uses **Label** model for contact tags. I extended the existing Label system with filtering, caching, and analytics.

### What I Built
| `GET` | `/api/vaults/{vaultId}/labels` | List all labels with usage count (Redis cached) |
| `POST` | `/api/vaults/{vaultId}/labels` | Create a new label |
| `GET` | `/api/vaults/{vaultId}/labels/{id}` | Show a single label |
| `PUT` | `/api/vaults/{vaultId}/labels/{id}` | Update a label |
| `DELETE` | `/api/vaults/{vaultId}/labels/{id}` | Delete a label (detaches from all contacts) |
| `POST` | `/api/vaults/{vaultId}/contacts/{id}/labels` | Attach labels to a contact |
| `DELETE` | `/api/vaults/{vaultId}/contacts/{id}/labels/{labelId}` | Detach a label from a contact |
| `GET` | `/api/vaults/{vaultId}/contacts?labels[]=1&labels[]=2` | Filter contacts by labels (AND logic) |

## Files

| File | Description |
|------|-------------|
| `database/migrations/*_create_taggables_table.php` | Polymorphic pivot table |
| `database/migrations/*_add_category_color_to_labels_table.php` | New columns |
| `app/Models/Label.php` | Added scopes and fillable fields |
| `app/Models/Contact.php` | Added hasAllLabels scope for AND filtering |
| `app/Services/LabelService.php` | Business logic + Redis caching |
| `app/Http/Controllers/Api/LabelController.php` | CRUD endpoints |
| `app/Http/Controllers/Api/ContactLabelController.php` | Attach/detach/filter |
| `routes/api.php` | New API routes |
| `tests/Feature/LabelTest.php` | 4 feature tests |

## AND Filtering SQL

Single SQL subquery — no PHP loop:

```sql
SELECT contacts.*
FROM contacts
WHERE (
  SELECT COUNT(DISTINCT label_id)
  FROM contact_label
  WHERE contact_id = contacts.id
  AND label_id IN (1, 2)
) = 2
```

## Cache Strategy

- Key format: `labels:vault:{vault_id}:list`
- TTL: 600 seconds (10 minutes)
- Invalidation: create / update / delete / attach / detach

## Assumptions

- Monica uses UUID for primary keys
- Auth via Laravel Sanctum
- Vault-scoped multi-tenancy (each user has a vault)

## Trade-offs

- Used `Label` instead of `Tag` — follows Monica existing naming convention
- Polymorphic `taggables` table for future extensibility
- Cache per vault — safe for multi-tenant environment
- `syncWithoutDetaching` for attach — preserves existing labels
# envobyte-assignment
