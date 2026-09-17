<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_openapi_document_lists_flutter_endpoints(): void
    {
        $response = $this->getJson('/docs/api.json');

        $response
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'Haragun API')
            ->assertJsonPath('info.version', '1.0.0');

        $paths = array_keys($response->json('paths') ?? []);

        $this->assertContains('/home', $paths);
        $this->assertContains('/auth/send-code', $paths);
        $this->assertContains('/auth/verify-code', $paths);
        $this->assertContains('/establishments/{establishment}', $paths);
        $this->assertContains('/establishments/{establishment}/menu', $paths);
        $this->assertContains('/establishments/{establishment}/cart', $paths);
        $this->assertNotEmpty($response->json('components.securitySchemes'));
    }

    public function test_docs_ui_is_available_in_local_and_testing(): void
    {
        $this->get('/docs/api')->assertOk();
    }
}
