<?php

namespace App\Tests\Mysql;

use App\Tests\Support\MysqlTestCase;

/**
 * @group mysql
 */
final class ElearningMysqlTest extends MysqlTestCase
{
    public function testResidentCanCompleteProgressAndPassQuiz(): void
    {
        $resident = $this->seedResident();
        $plain = $this->seedModule();
        $progress = $this->assertOk($this->post('/api/v1/elearning/progress', [
            'module_id' => $plain['id'],
            'status' => 'completed',
        ], $this->actingAs($resident)));
        $this->assertSame('completed', $progress['progress']['status']);

        $quizModule = $this->seedModule([
            'quiz_json' => json_encode([
                ['q' => 'When should you bait a TNR trap?', 'options' => ['At dusk', 'At noon'], 'a' => 0],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $pass = $this->assertOk($this->post('/api/v1/elearning/quiz', [
            'module_id' => $quizModule['id'],
            'answers' => [0],
        ], $this->actingAs($resident)));
        $this->assertTrue($pass['result']['passed']);
    }

    public function testResidentCannotCreateModule(): void
    {
        $resident = $this->seedResident();
        $this->assertError($this->post('/api/v1/elearning/modules', [
            'title' => 'Blocked',
            'category' => 'general_care',
            'content_body' => 'Should be denied.',
        ], $this->actingAs($resident)), 'FORBIDDEN', 403);
    }
}
