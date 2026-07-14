<?php

declare(strict_types=1);

namespace Tests;

final class HealthAndDocsTest extends IntegrationTestCase
{
    public function testLiveEsAnonimoYResponde200(): void
    {
        $response = $this->send('GET', '/health/live');

        self::assertSame(200, $response->status);
        self::assertSame('Healthy', $this->decode($response)['status']);
    }

    public function testReadyComprueaLaBaseDeDatos(): void
    {
        $response = $this->send('GET', '/health/ready');

        self::assertSame(200, $response->status);
        self::assertSame('Healthy', $this->decode($response)['checks']['mysql']);
    }

    public function testOpenapiEsAnonimoYEsJsonValido(): void
    {
        $response = $this->send('GET', '/openapi.json');

        self::assertSame(200, $response->status);
        $spec = json_decode($response->body, true);
        self::assertSame('3.1.0', $spec['openapi']);
        self::assertArrayHasKey('/api/notes', $spec['paths']);
    }

    public function testDocsEsAnonimoYSirveHtml(): void
    {
        $response = $this->send('GET', '/docs');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('text/html', $response->headers['Content-Type']);
    }
}
