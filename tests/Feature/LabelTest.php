<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Label;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LabelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Vault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->vault = Vault::factory()->create([
            'account_id' => $this->user->account_id,
        ]);

        $contact = Contact::factory()->create(['vault_id' => $this->vault->id]);

        $this->vault->users()->attach($this->user->id, [
            'permission' => Vault::PERMISSION_MANAGE,
            'contact_id' => $contact->id,
        ]);
    }

    // TEST 1: Create a label and verify it appears in the list
    public function test_creates_a_label_and_it_appears_in_the_label_list(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', [
                'name' => 'colleague',
                'tag_category' => 'Work',
                'vault_id' => $this->vault->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'colleague');

        $listResponse = $this->actingAs($this->user)
            ->getJson("/api/tags?vault_id={$this->vault->id}");

        $listResponse->assertStatus(200);

        $labels = collect($listResponse->json('data'));
        $this->assertTrue($labels->contains('name', 'colleague'));
    }

    // TEST 2: Attach two labels to a contact and filter by both (AND logic)
    public function test_filters_contacts_by_multiple_labels_using_and_logic(): void
    {
        $label1 = Label::factory()->create(['vault_id' => $this->vault->id, 'name' => 'vip']);
        $label2 = Label::factory()->create(['vault_id' => $this->vault->id, 'name' => 'client']);

        $contactBoth = Contact::factory()->create(['vault_id' => $this->vault->id]);
        $contactOne = Contact::factory()->create(['vault_id' => $this->vault->id]);
        $contactNone = Contact::factory()->create(['vault_id' => $this->vault->id]);

        $contactBoth->labels()->attach([$label1->id, $label2->id]);
        $contactOne->labels()->attach([$label1->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/contacts?vault_id={$this->vault->id}&tags[]={$label1->id}&tags[]={$label2->id}");

        $response->assertStatus(200);

        $contactIds = collect($response->json('data'))->pluck('id');

        $this->assertContains($contactBoth->id, $contactIds->toArray());
        $this->assertNotContains($contactOne->id, $contactIds->toArray());
        $this->assertNotContains($contactNone->id, $contactIds->toArray());
    }

    // TEST 3: Delete a label — verify it detaches from all contacts
    public function test_detaches_a_label_from_all_contacts_when_deleted(): void
    {
        $label = Label::factory()->create(['vault_id' => $this->vault->id]);
        $contact = Contact::factory()->create(['vault_id' => $this->vault->id]);

        $contact->labels()->attach($label->id);

        $this->assertDatabaseHas('contact_label', [
            'label_id' => $label->id,
            'contact_id' => $contact->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/tags/{$label->id}?vault_id={$this->vault->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
        $this->assertDatabaseMissing('contact_label', ['label_id' => $label->id]);
    }

    // TEST 4: Cache invalidation on label creation
    public function test_invalidates_the_cache_when_a_label_is_created(): void
    {
        $cacheKey = "labels:vault:{$this->vault->id}:list";

        Cache::put($cacheKey, ['old_data'], 600);
        $this->assertNotNull(Cache::get($cacheKey));

        $this->actingAs($this->user)
            ->postJson('/api/tags', [
                'name' => 'new-label',
                'vault_id' => $this->vault->id,
            ])
            ->assertStatus(201);

        $this->assertNull(Cache::get($cacheKey));
    }
}
