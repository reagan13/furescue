<?php

namespace App\Services;

class ElearningQuiz
{
    /**
     * @return list<array{q:string,options:list<string>,a:int}>
     */
    public static function store(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return json_encode([]);
        }
        if (is_string($raw) && in_array(trim($raw), ['', '[]', 'null'], true)) {
            return json_encode([]);
        }
        $decoded = self::decode($raw);
        $empty = $decoded === [];
        $blank = $raw === null || $raw === '' || $raw === []
            || (is_string($raw) && in_array(trim($raw), ['', '[]', 'null'], true));
        if ($empty && !$blank) {
            throw new \InvalidArgumentException('Quiz must be a list of questions. Each question needs text, options, and an answer index.');
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    public static function decode(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw)) {
            $trim = trim($raw);
            if ($trim === '' || $trim === 'null' || $trim === '[]') {
                return [];
            }
            $decoded = json_decode($trim, true);
            $items = is_array($decoded) ? $decoded : [];
        } else {
            return [];
        }

        $quiz = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['q'] ?? ''));
            $options = $item['options'] ?? null;
            if ($question === '' || !is_array($options) || $options === []) {
                continue;
            }
            $clean = [];
            foreach ($options as $option) {
                if (is_string($option) && trim($option) !== '') {
                    $clean[] = $option;
                }
            }
            if ($clean === []) {
                continue;
            }
            $answer = (int) ($item['a'] ?? 0);
            if ($answer < 0 || $answer >= count($clean)) {
                $answer = 0;
            }
            $quiz[] = ['q' => $question, 'options' => $clean, 'a' => $answer];
        }

        return $quiz;
    }

    /**
     * @param list<array{q:string,options:list<string>,a:int}> $quiz
     * @return list<array{q:string,options:list<string>}>
     */
    public static function publicQuestions(array $quiz): array
    {
        $out = [];
        foreach ($quiz as $item) {
            $out[] = ['q' => $item['q'], 'options' => $item['options']];
        }

        return $out;
    }

    /**
     * @param list<array{q:string,options:list<string>,a:int}> $quiz
     * @param list<int|string> $answers
     * @return array{passed:bool,score:int,total:int}
     */
    public static function grade(array $quiz, array $answers): array
    {
        $total = count($quiz);
        $score = 0;
        foreach ($quiz as $i => $item) {
            if ((int) ($answers[$i] ?? -1) === $item['a']) {
                $score++;
            }
        }

        return [
            'passed' => $total > 0 && $score === $total,
            'score' => $score,
            'total' => $total,
        ];
    }
}
