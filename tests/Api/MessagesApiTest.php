<?php

namespace App\Tests\Api;

use App\Database;
use App\Tests\Support\ApiTestCase;

class MessagesApiTest extends ApiTestCase
{
    private function seedMessage(array $overrides = []): array
    {
        $id = $overrides['id'] ?? Database::uuidV4();
        $row = [
            'id' => $id,
            'sender_id' => $overrides['sender_id'],
            'receiver_id' => $overrides['receiver_id'],
            'related_type' => $overrides['related_type'] ?? 'report',
            'related_id' => $overrides['related_id'],
            'message_text' => $overrides['message_text'] ?? 'Hello',
        ];
        $this->pdo->prepare(
            'INSERT INTO messages (id, sender_id, receiver_id, related_type, related_id, message_text)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $row['id'], $row['sender_id'], $row['receiver_id'], $row['related_type'],
            $row['related_id'], $row['message_text'],
        ]);
        return $row;
    }

    public function testCannotMessageYourself(): void
    {
        $resident = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $resident['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 'Hi me',
        ], $this->actingAs($resident)), 'INVALID_RECEIVER', 422);
    }

    public function testCannotMessageMissingUser(): void
    {
        $resident = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $this->uuid(),
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 'Hi',
        ], $this->actingAs($resident)), 'NOT_FOUND', 404);
    }

    public function testCannotOpenSomeoneElsesAdoptionThread(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $adoption = $this->seedAdoption(['applicant_id' => $owner['id']]);
        $this->assertError($this->get('/api/v1/messages', [
            'related_type' => 'adoption',
            'related_id' => $adoption['id'],
        ], $this->actingAs($other)), 'FORBIDDEN', 403);
    }

    public function testCannotMessageAboutAReportYouDoNotOwn(): void
    {
        $owner = $this->seedResident();
        $other = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 'Not my report',
        ], $this->actingAs($other)), 'FORBIDDEN', 403);
    }

    public function testOwnerCanMessageAboutOwnReport(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $data = $this->assertOk($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 'Still there?',
        ], $this->actingAs($owner)), 201);
        $this->assertSame('Still there?', $data['message']['message_text']);
    }

    public function testMissingRelatedReportIsNotFound(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $this->uuid(),
            'message_text' => 'Hi',
        ], $this->actingAs($owner)), 'NOT_FOUND', 404);
    }

    public function testMissingRelatedAdoptionIsNotFound(): void
    {
        $resident = $this->seedResident();
        $admin = $this->seedAdmin();
        $this->assertError($this->get('/api/v1/messages', [
            'related_type' => 'adoption',
            'related_id' => $this->uuid(),
        ], $this->actingAs($resident)), 'NOT_FOUND', 404);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'adoption',
            'related_id' => $this->uuid(),
            'message_text' => 'Hi',
        ], $this->actingAs($resident)), 'NOT_FOUND', 404);
    }

    public function testSendRejectsEmptyBody(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/messages', [], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testSendRejectsInvalidReceiverUuid(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => 'user-1',
            'related_type' => 'report',
            'related_id' => $this->uuid(),
            'message_text' => 'Hi',
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testSendRejectsUnknownRelatedType(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $this->uuid(),
            'related_type' => 'listing',
            'related_id' => $this->uuid(),
            'message_text' => 'Hi',
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testSendRejectsNumericBody(): void
    {
        $resident = $this->seedResident();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $this->uuid(),
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => 12,
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testSendRejectsOverMaxText(): void
    {
        $resident = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => str_repeat('x', 4001),
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testThreadRequiresQuery(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->get('/api/v1/messages', [], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testApplicantCanOpenOwnAdoptionThread(): void
    {
        $resident = $this->seedResident();
        $adoption = $this->seedAdoption(['applicant_id' => $resident['id']]);
        $data = $this->assertOk($this->get('/api/v1/messages', [
            'related_type' => 'adoption',
            'related_id' => $adoption['id'],
        ], $this->actingAs($resident)));
        $this->assertIsArray($data['messages']);
    }

    public function testComposeTargetsListsOwnReportsAndAdoptions(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->seedAdoption(['applicant_id' => $owner['id']]);
        $data = $this->assertOk($this->get('/api/v1/messages/compose-targets', [], $this->actingAs($owner)));
        $this->assertSame($admin['id'], $data['receiver']['id']);
        $types = array_column($data['topics'], 'related_type');
        $this->assertContains('report', $types);
        $this->assertContains('adoption', $types);
        $this->assertSame($report['id'], $data['topics'][0]['related_id']);
    }

    public function testComposeTargetsWithoutStaffFails(): void
    {
        $owner = $this->seedResident();
        $this->assertError(
            $this->get('/api/v1/messages/compose-targets', [], $this->actingAs($owner)),
            'NO_STAFF',
            422
        );
    }

    public function testThreadsListsOwnConversations(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $this->seedMessage([
            'sender_id' => $owner['id'],
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
        ]);
        $data = $this->assertOk($this->get('/api/v1/messages/threads', [], $this->actingAs($owner)));
        $this->assertCount(1, $data['threads']);
    }

    public function testMarkReadForbiddenForSender(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $msg = $this->seedMessage([
            'sender_id' => $owner['id'],
            'receiver_id' => $admin['id'],
            'related_id' => $report['id'],
        ]);
        $this->assertError(
            $this->patch('/api/v1/messages/' . $msg['id'] . '/read', [], $this->actingAs($owner)),
            'FORBIDDEN',
            403
        );
    }

    public function testReceiverCanMarkRead(): void
    {
        $owner = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $owner['id']]);
        $msg = $this->seedMessage([
            'sender_id' => $owner['id'],
            'receiver_id' => $admin['id'],
            'related_id' => $report['id'],
        ]);
        $data = $this->assertOk($this->patch('/api/v1/messages/' . $msg['id'] . '/read', [], $this->actingAs($admin)));
        $this->assertNotEmpty($data['message']['read_at']);
    }

    public function testMarkReadUnknown(): void
    {
        $resident = $this->seedResident();
        $this->assertError(
            $this->patch('/api/v1/messages/' . $this->uuid() . '/read', [], $this->actingAs($resident)),
            'NOT_FOUND',
            404
        );
    }

    public function testCaseThreadForbiddenForResident(): void
    {
        $resident = $this->seedResident();
        $case = $this->seedCase();
        $this->assertError($this->get('/api/v1/messages', [
            'related_type' => 'case',
            'related_id' => $case['id'],
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }

    public function testAdminCanOpenCaseThread(): void
    {
        $admin = $this->seedAdmin();
        $case = $this->seedCase();
        $this->assertOk($this->get('/api/v1/messages', [
            'related_type' => 'case',
            'related_id' => $case['id'],
        ], $this->actingAs($admin)));
    }

    public function testThreadRejectsInvalidRelatedId(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->get('/api/v1/messages', [
            'related_type' => 'report',
            'related_id' => 'nope',
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }

    public function testSendWhitespaceTextFails(): void
    {
        $resident = $this->seedResident();
        $admin = $this->seedAdmin();
        $report = $this->seedReport(['resident_id' => $resident['id']]);
        $this->assertError($this->post('/api/v1/messages', [
            'receiver_id' => $admin['id'],
            'related_type' => 'report',
            'related_id' => $report['id'],
            'message_text' => '   ',
        ], $this->actingAs($resident)), 'VALIDATION_ERROR', 400);
    }
}
