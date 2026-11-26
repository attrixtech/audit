<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QaAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_authenticated_and_login_pages_send_no_cache_headers_and_redirect_authenticated_users(): void
    {
        $loginResponse = $this->get(route('login'))->assertOk();
        $this->assertStringContainsString('no-store', (string) $loginResponse->headers->get('Cache-Control'));

        $clientUser = User::where('email', 'client@apexprecision.in')->firstOrFail();

        $this->actingAs($clientUser)
            ->get(route('login'))
            ->assertRedirect(route('portal.dashboard'));

        $this->actingAs($clientUser)
            ->post(route('login.submit'), ['email' => $clientUser->email, 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard'));

        $portalResponse = $this->actingAs($clientUser)->get(route('portal.dashboard'))->assertOk();
        $this->assertStringContainsString('no-store', (string) $portalResponse->headers->get('Cache-Control'));
    }

    public function test_portal_document_upload_rejects_images_with_pdf_only_error(): void
    {
        $clientUser = User::where('email', 'client@apexprecision.in')->firstOrFail();
        $client = $clientUser->clientProfile;
        $requestedDoc = $client->documents()->where('status', 'requested')->firstOrFail();

        $this->actingAs($clientUser)
            ->post(route('portal.documents.upload'), [
                'document_id' => $requestedDoc->id,
                'file' => UploadedFile::fake()->create('photo.png', 50, 'image/png'),
            ])
            ->assertSessionHasErrors(['file' => 'Please upload PDF only.']);

        $this->actingAs($clientUser)
            ->post(route('portal.documents.upload'), [
                'title' => 'Image Upload Attempt',
                'category' => 'gst',
                'file' => UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg'),
            ])
            ->assertSessionHasErrors(['file' => 'Please upload PDF only.']);
    }

    public function test_document_approve_returns_json_for_ajax_requests(): void
    {
        $manager = User::where('email', 'manager@thrumble.audit')->firstOrFail();
        $doc = Document::where('status', 'requested')->firstOrFail();
        $doc->update(['status' => 'uploaded']);

        $this->actingAs($manager)
            ->postJson(route('documents.approve', $doc))
            ->assertOk()
            ->assertJson([
                'status' => 'approved',
                'status_label' => 'Approved',
            ]);

        $this->assertEquals('approved', $doc->fresh()->status);
    }

    public function test_dashboard_displays_human_readable_role_label_and_sidebar_logout(): void
    {
        $employee = User::where('email', 'employee@thrumble.audit')->firstOrFail();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Accountant / Employee')
            ->assertSee('Sign Out');
    }

    public function test_reports_date_filters_and_settings_audit_trail_filters_work(): void
    {
        $admin = User::where('email', 'admin@thrumble.audit')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('reports.index', [
                'type' => 'client_compliance',
                'from_date' => '2026-01-01',
                'to_date' => '2026-12-31',
            ]))
            ->assertOk()
            ->assertSee('Filtered: 2026-01-01 to 2026-12-31');

        $this->actingAs($admin)
            ->get(route('settings.index', ['module' => 'auth', 'search' => 'signed']))
            ->assertOk()
            ->assertSee('System Audit Trail');
    }
}
