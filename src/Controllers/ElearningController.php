<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;

class ElearningController extends AbstractController
{
    public function modules(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'category' => ['dog_behavior', 'cat_behavior', 'basic_training', 'general_care'],
            'published_status' => ['draft', 'published'],
        ])) {
            return;
        }
        $repo = $this->repo('elearning_modules', ['id','title','category','published_status','created_at']);
        $filters = [];
        if (in_array((string) ($req->user['role'] ?? ''), ['resident', 'rescuer'], true)) {
            $filters['published_status'] = 'published';
        } elseif (!empty($req->query['published_status'])) {
            $filters['published_status'] = $req->query['published_status'];
        }
        if (!empty($req->query['category'])) {
            $filters['category'] = $req->query['category'];
        }
        $result = $repo->paginate($this->page($req), $this->perPage($req), $filters);
        Response::paginated($result['items'], $this->meta($result['page'], $result['per_page'], $result['total']));
    }

    public function module(Request $req): void
    {
        $module = $this->repo('elearning_modules')->find($req->params['id']);
        if (!$module) {
            Response::error('NOT_FOUND', 'Module not found', 404);
            return;
        }
        if ($module['published_status'] !== 'published' && !in_array('elearning.write', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Module not published', 403);
            return;
        }
        Response::success(['module' => $this->presentModule($module)]);
    }

    public function createModule(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('title')->string('title', 150)
            ->required('category')->in('category', ['dog_behavior','cat_behavior','basic_training','general_care'])
            ->required('content_body')->string('content_body', 20000)
            ->optional('published_status')->in('published_status', ['draft','published']);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $id = $this->repo('elearning_modules')->create([
            'id' => Database::uuidV4(),
            'title' => $req->body['title'],
            'category' => $req->body['category'],
            'content_body' => $req->body['content_body'],
            'published_status' => $req->body['published_status'] ?? 'draft',
            'created_by' => $req->user['id'],
        ]);
        Response::success(['module' => $this->repo('elearning_modules')->find($id)], 201);
    }

    public function updateModule(Request $req): void
    {
        $repo = $this->repo('elearning_modules');
        $module = $repo->find($req->params['id']);
        if (!$module) {
            Response::error('NOT_FOUND', 'Module not found', 404);
            return;
        }
        $allowed = ['title','category','content_body','published_status'];
        $v = new \App\Validation\Validator($req->body);
        if (array_key_exists('title', $req->body)) {
            $v->optional('title')->string('title', 150);
        }
        if (array_key_exists('category', $req->body)) {
            $v->optional('category')->in('category', ['dog_behavior', 'cat_behavior', 'basic_training', 'general_care']);
        }
        if (array_key_exists('content_body', $req->body)) {
            $v->optional('content_body')->string('content_body', 20000);
        }
        if (array_key_exists('published_status', $req->body)) {
            $v->optional('published_status')->in('published_status', ['draft', 'published']);
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $data = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $req->body)) {
                $data[$f] = $req->body[$f];
            }
        }
        if (empty($data)) {
            Response::error('VALIDATION_ERROR', 'No fields provided', 400);
            return;
        }
        $repo->update($module['id'], $data);
        Response::success(['module' => $repo->find($module['id'])]);
    }

    public function progress(Request $req): void
    {
        $rows = $this->repo('elearning_progress')->all(['resident_id' => $req->user['id']], 'completed_at', 'DESC');
        Response::success(['progress' => $rows]);
    }

    public function upsertProgress(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('module_id')->uuid('module_id')
            ->required('status')->in('status', ['not_started','in_progress','completed']);
        if (array_key_exists('read_pct', $req->body)) {
            $v->optional('read_pct')->numeric('read_pct')->min('read_pct', 0);
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $module = $this->repo('elearning_modules')->find($req->body['module_id']);
        if (!$module) {
            Response::error('NOT_FOUND', 'Module not found', 404);
            return;
        }
        $quiz = \App\Services\ElearningQuiz::decode($module['quiz_json'] ?? null);
        $status = (string) $req->body['status'];
        $repo = $this->repo('elearning_progress');
        $existing = $repo->findByComposite(['resident_id','module_id'], [$req->user['id'], $req->body['module_id']]);
        if ($existing && ($existing['status'] ?? '') === 'completed' && $status !== 'completed') {
            $status = 'completed';
        }
        if ($status === 'completed' && $quiz !== [] && (int) ($existing['quiz_passed'] ?? 0) !== 1) {
            Response::error('QUIZ_REQUIRED', 'Finish the lesson quiz to complete this module.', 409);
            return;
        }
        $readPct = array_key_exists('read_pct', $req->body)
            ? max(0, min(100, (int) $req->body['read_pct']))
            : (int) ($existing['read_pct'] ?? 0);
        if ($status === 'completed') {
            $readPct = 100;
        }
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : ($existing['completed_at'] ?? null);
        $payload = [
            'status' => $status,
            'completed_at' => $completedAt,
            'read_pct' => $readPct,
        ];
        if ($status === 'completed') {
            $payload['badge_earned'] = 1;
            if ($quiz === []) {
                $payload['quiz_passed'] = 1;
            }
        }
        if ($existing) {
            $repo->update($existing['id'], $payload);
            $row = $repo->find($existing['id']);
        } else {
            $id = $repo->create(array_merge([
                'id' => Database::uuidV4(),
                'resident_id' => $req->user['id'],
                'module_id' => $req->body['module_id'],
                'quiz_passed' => $payload['quiz_passed'] ?? 0,
                'badge_earned' => $payload['badge_earned'] ?? 0,
            ], $payload));
            $row = $repo->find($id);
        }
        Response::success(['progress' => $row]);
    }

    public function submitQuiz(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('module_id')->uuid('module_id');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $answers = $req->body['answers'] ?? null;
        if (!is_array($answers)) {
            Response::error('VALIDATION_ERROR', 'answers must be an array', 400);
            return;
        }
        $module = $this->repo('elearning_modules')->find($req->body['module_id']);
        if (!$module) {
            Response::error('NOT_FOUND', 'Module not found', 404);
            return;
        }
        if ($module['published_status'] !== 'published' && !in_array('elearning.write', $req->permissions, true)) {
            Response::error('FORBIDDEN', 'Module not published', 403);
            return;
        }
        $quiz = \App\Services\ElearningQuiz::decode($module['quiz_json'] ?? null);
        if ($quiz === []) {
            Response::error('NO_QUIZ', 'This module does not have a quiz yet.', 404);
            return;
        }
        $result = \App\Services\ElearningQuiz::grade($quiz, array_values($answers));
        $repo = $this->repo('elearning_progress');
        $existing = $repo->findByComposite(['resident_id','module_id'], [$req->user['id'], $req->body['module_id']]);
        $status = $result['passed'] ? 'completed' : 'in_progress';
        $payload = [
            'status' => $status,
            'read_pct' => $result['passed'] ? 100 : (int) ($existing['read_pct'] ?? 0),
            'quiz_passed' => $result['passed'] ? 1 : 0,
            'badge_earned' => $result['passed'] ? 1 : (int) ($existing['badge_earned'] ?? 0),
            'completed_at' => $result['passed'] ? date('Y-m-d H:i:s') : ($existing['completed_at'] ?? null),
        ];
        if ($existing) {
            $repo->update($existing['id'], $payload);
            $row = $repo->find($existing['id']);
        } else {
            $id = $repo->create(array_merge([
                'id' => Database::uuidV4(),
                'resident_id' => $req->user['id'],
                'module_id' => $req->body['module_id'],
            ], $payload));
            $row = $repo->find($id);
        }
        Response::success(['result' => $result, 'progress' => $row]);
    }

    /**
     * @param array<string,mixed> $module
     * @return array<string,mixed>
     */
    private function presentModule(array $module): array
    {
        $quiz = \App\Services\ElearningQuiz::decode($module['quiz_json'] ?? null);
        unset($module['quiz_json']);
        $module['has_quiz'] = $quiz !== [];
        $module['quiz'] = \App\Services\ElearningQuiz::publicQuestions($quiz);

        return $module;
    }
}
