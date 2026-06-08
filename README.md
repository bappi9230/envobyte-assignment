# Envobyte Assignment — Monica CRM Tag System Extension

## Approach

Monica CRM uses **Label** model for contact tags. I extended the existing Label system with filtering, caching, and analytics.

### What I Built
| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/tags` | List all tags with usage count (Redis cached) |
| `POST` | `/api/tags` | Create a new tag |
| `GET` | `/api/tags/:id` | Show a single tag |
| `PUT` | `/api/tags/:id` | Update a tag |
| `DELETE` | `/api/tags/:id` | Delete a tag (detaches from all contacts) |
| `POST` | `/api/contacts/:id/tags` | Attach tags to a contact |
| `DELETE` | `/api/contacts/:id/tags/:tagId` | Detach a tag from a contact |
| `GET` | `/api/contacts?tags[]=1&tags[]=2` | Filter contacts by tags (AND logic) |
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
