<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

class AnimalPresetTest extends ApiTestCase
{
    public function testEmptyPresetResolvesReferenceFromSpecies(): void
    {
        $admin = $this->seedAdmin();
        $dog = $this->assertOk($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'puspin',
            'sex' => 'male',
            'name' => 'Bantay',
        ], $this->actingAs($admin)), 201);
        $this->assertSame('puspin', $dog['animal']['breed_type']);
        $this->assertNull($dog['animal']['model_preset']);
        $this->assertSame('/assets/models/aspin.glb', $dog['animal']['reference_model_url']);

        $cat = $this->assertOk($this->post('/api/v1/animals', [
            'species' => 'cat',
            'breed_type' => 'aspin',
            'sex' => 'female',
            'name' => 'Ming',
        ], $this->actingAs($admin)), 201);
        $this->assertSame('aspin', $cat['animal']['breed_type']);
        $this->assertNull($cat['animal']['model_preset']);
        $this->assertSame('/assets/models/puspin.glb', $cat['animal']['reference_model_url']);
    }

    public function testPresetWhitelistRejectsUnknownReferenceAndBreed(): void
    {
        $admin = $this->seedAdmin();
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'hungarian',
            'sex' => 'male',
            'model_preset' => 'aspin',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'male',
            'model_preset' => 'labrador',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
        $this->assertError($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'german_shepherd',
            'sex' => 'male',
        ], $this->actingAs($admin)), 'VALIDATION_ERROR', 400);
    }

    public function testStoredPresetResolvesUrlAndHidesMissingFile(): void
    {
        $admin = $this->seedAdmin();
        $token = $this->actingAs($admin);
        $created = $this->assertOk($this->post('/api/v1/animals', [
            'species' => 'dog',
            'breed_type' => 'aspin',
            'sex' => 'female',
            'name' => 'Hera',
            'model_preset' => 'hungarian',
        ], $token), 201);
        $this->assertSame('aspin', $created['animal']['breed_type']);
        $this->assertSame('hungarian', $created['animal']['model_preset']);
        $this->assertNull($created['animal']['reference_model_url']);

        $id = $created['animal']['id'];
        $patched = $this->assertOk($this->patch('/api/v1/animals/' . $id, [
            'model_preset' => 'german_shepherd',
        ], $token));
        $this->assertSame('german_shepherd', $patched['animal']['model_preset']);
        $this->assertSame('aspin', $patched['animal']['breed_type']);
        $this->assertNull($patched['animal']['reference_model_url']);

        $cleared = $this->assertOk($this->patch('/api/v1/animals/' . $id, [
            'model_preset' => null,
        ], $token));
        $this->assertNull($cleared['animal']['model_preset']);
        $this->assertSame('/assets/models/aspin.glb', $cleared['animal']['reference_model_url']);

        $shown = $this->assertOk($this->get('/api/v1/animals/' . $id, [], $token));
        $this->assertSame('/assets/models/aspin.glb', $shown['animal']['reference_model_url']);
    }

    public function testListIncludesResolvedReferenceUrl(): void
    {
        $admin = $this->seedAdmin();
        $token = $this->actingAs($admin);
        $created = $this->assertOk($this->post('/api/v1/animals', [
            'species' => 'cat',
            'breed_type' => 'puspin',
            'sex' => 'male',
            'name' => 'Kit',
            'model_preset' => 'puspin',
        ], $token), 201);

        $rows = $this->assertOk($this->get('/api/v1/animals', [], $token));
        $match = null;
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $created['animal']['id']) {
                $match = $row;
            }
        }
        $this->assertIsArray($match);
        $this->assertSame('puspin', $match['model_preset']);
        $this->assertSame('/assets/models/puspin.glb', $match['reference_model_url']);
    }

    public function testPatchRejectsUnknownPreset(): void
    {
        $admin = $this->seedAdmin();
        $animal = $this->seedAnimal();
        $this->assertError($this->patch(
            '/api/v1/animals/' . $animal['id'],
            ['model_preset' => 'shepherd'],
            $this->actingAs($admin)
        ), 'VALIDATION_ERROR', 400);
    }
}
