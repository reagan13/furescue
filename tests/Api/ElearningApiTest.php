<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class ElearningApiTest extends ApiTestCase
{
    public function testResidentListsOnlyPublishedModules(): void
    {
        $resident = $this->seedResident();
        $this->seedModule(['published_status' => 'published']);
        $this->seedModule(['published_status' => 'draft', 'title' => 'Draft only']);
        $response = $this->get('/api/v1/elearning/modules', [], $this->actingAs($resident));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testAdminCanFilterDrafts(): void
    {
        $admin = $this->seedAdmin();
        $this->seedModule(['published_status' => 'draft']);
        $response = $this->get('/api/v1/elearning/modules', ['published_status' => 'draft'], $this->actingAs($admin));
        $this->assertOk($response);
        $this->assertSame(1, $response['body']['meta']['total']);
    }

    public function testListRejectsInvalidCategory(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/elearning/modules', ['category' => 'cooking'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListRejectsInvalidPublishedStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/elearning/modules', ['published_status' => 'archived'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testListRejectsBadPerPage(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->get('/api/v1/elearning/modules', ['per_page' => '0'], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testResidentCannotOpenDraftModule(): void
    {
        $resident = $this->seedResident();
        $module = $this->seedModule(['published_status' => 'draft']);
        $this->assertError(
            $this->get('/api/v1/elearning/modules/' . $module['id'], [], $this->actingAs($resident)),
            'FORBIDDEN',
            403
        );
    }

    public function testResidentCanOpenPublishedModule(): void
    {
        $resident = $this->seedResident();
        $module = $this->seedModule(['published_status' => 'published']);
        $data = $this->assertOk($this->get('/api/v1/elearning/modules/' . $module['id'], [], $this->actingAs($resident)));
        $this->assertSame($module['id'], $data['module']['id']);
        $this->assertArrayNotHasKey('quiz_json', $data['module']);
        $this->assertSame([], $data['module']['quiz']);
    }

    public function testModuleNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/elearning/modules/' . $this->uuid(), [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }

    public function testResidentCannotCreateModule(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/elearning/modules', [
            'title' => 'Nope',
            'category' => 'general_care',
            'content_body' => 'x',
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }

    public function testAdminCanCreateModule(): void
    {
        $admin = $this->seedAdmin();
        $data = $this->assertOk($this->post('/api/v1/elearning/modules', [
            'title' => 'Cat care 101',
            'category' => 'cat_behavior',
            'content_body' => 'Keep water available.',
        ], $this->actingAs($admin)), 201);
        $this->assertSame('draft', $data['module']['published_status']);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/elearning/modules', [], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsInvalidCategory(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/elearning/modules', [
            'title' => 'X',
            'category' => 'cooking',
            'content_body' => 'Y',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsNumericTitle(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/elearning/modules', [
            'title' => 12,
            'category' => 'general_care',
            'content_body' => 'Y',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testCreateRejectsOverMaxTitle(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/elearning/modules', [
            'title' => str_repeat('T', 151),
            'category' => 'general_care',
            'content_body' => 'Y',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRevalidatesCategory(): void
    {
        $admin = $this->seedAdmin();
        $module = $this->seedModule();
        $this->assertError($this->patch(
            '/api/v1/elearning/modules/' . $module['id'],
            ['category' => 'cooking'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchRevalidatesPublishedStatus(): void
    {
        $admin = $this->seedAdmin();
        $module = $this->seedModule();
        $this->assertError($this->patch(
            '/api/v1/elearning/modules/' . $module['id'],
            ['published_status' => 'archived'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }

    public function testPatchHappyPath(): void
    {
        $admin = $this->seedAdmin();
        $module = $this->seedModule();
        $data = $this->assertOk($this->patch(
            '/api/v1/elearning/modules/' . $module['id'],
            ['title' => 'Updated title', 'published_status' => 'published'],
            $this->actingAs($admin)
        ));
        $this->assertSame('Updated title', $data['module']['title']);
    }

    public function testPatchEmptyBodyFails(): void
    {
        $admin = $this->seedAdmin();
        $module = $this->seedModule();
        $this->assertError(
            $this->patch('/api/v1/elearning/modules/' . $module['id'], [], $this->actingAs($admin)),
            'VALIDATION_ERROR',
            400
        );
    }

    public function testPatchUnknownModule(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError(
            $this->patch('/api/v1/elearning/modules/' . $this->uuid(), ['title' => 'X'], $this->actingAs($admin)),
            'NOT_FOUND',
            404
        );
    }

    public function testProgressForMissingModuleIsNotFound(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/elearning/progress', [
            'module_id' => $this->uuid(),
            'status' => 'completed',
        ], $this->actingAs($resident)), 'NOT_FOUND', 404);
    }

    public function testProgressRejectsInvalidUuid(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/elearning/progress', [
            'module_id' => 'mod-1',
            'status' => 'completed',
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testProgressHappyPath(): void
    {
        $resident = $this->seedResident();
        $module = $this->seedModule();
        $data = $this->assertOk($this->post('/api/v1/elearning/progress', [
            'module_id' => $module['id'],
            'status' => 'completed',
        ], $this->actingAs($resident)));
        $this->assertSame('completed', $data['progress']['status']);
        $this->assertNotEmpty($data['progress']['completed_at']);
    }

    public function testProgressListIsScoped(): void
    {
        $resident = $this->seedResident();
        $data = $this->assertOk($this->get('/api/v1/elearning/progress', [], $this->actingAs($resident)));
        $this->assertIsArray($data['progress']);
    }

    public function testQuizPassCompletesModuleAndAwardsBadge(): void
    {
        $resident = $this->seedResident();
        $module = $this->seedModule([
            'quiz_json' => json_encode([
                ['q' => 'When should you bait a TNR trap?', 'options' => ['At dusk', 'At noon'], 'a' => 0],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $this->assertError($this->post('/api/v1/elearning/progress', [
            'module_id' => $module['id'],
            'status' => 'completed',
        ], $this->actingAs($resident)), 'QUIZ_REQUIRED', 409);

        $fail = $this->assertOk($this->post('/api/v1/elearning/quiz', [
            'module_id' => $module['id'],
            'answers' => [1],
        ], $this->actingAs($resident)));
        $this->assertFalse($fail['result']['passed']);
        $this->assertSame('in_progress', $fail['progress']['status']);

        $pass = $this->assertOk($this->post('/api/v1/elearning/quiz', [
            'module_id' => $module['id'],
            'answers' => [0],
        ], $this->actingAs($resident)));
        $this->assertTrue($pass['result']['passed']);
        $this->assertSame('completed', $pass['progress']['status']);
        $this->assertSame(1, (int) $pass['progress']['badge_earned']);

        $keep = $this->assertOk($this->post('/api/v1/elearning/progress', [
            'module_id' => $module['id'],
            'status' => 'in_progress',
            'read_pct' => 40,
        ], $this->actingAs($resident)));
        $this->assertSame('completed', $keep['progress']['status']);
    }
}
