<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\UpdateService;
use PHPUnit\Framework\TestCase;

class UpdateServiceTest extends TestCase
{
    private UpdateService $service;

    protected function setUp(): void
    {
        $this->service = new UpdateService('1.0.0');
    }

    // -------------------------------------------------------------------------
    // compareVersions
    // -------------------------------------------------------------------------

    public function testCompareVersionsReturnsZeroForEqualVersions(): void
    {
        $this->assertSame(0, $this->service->compareVersions('1.2.3', '1.2.3'));
    }

    public function testCompareVersionsReturnsOneWhenFirstIsGreater(): void
    {
        $this->assertSame(1, $this->service->compareVersions('2.0.0', '1.9.9'));
    }

    public function testCompareVersionsReturnsMinusOneWhenFirstIsSmaller(): void
    {
        $this->assertSame(-1, $this->service->compareVersions('1.0.0', '1.0.1'));
    }

    public function testCompareVersionsHandlesMissingPatch(): void
    {
        // "1.1" doit être équivalent à "1.1.0"
        $this->assertSame(0, $this->service->compareVersions('1.1', '1.1.0'));
    }

    public function testCompareVersionsMinorVersionDifference(): void
    {
        $this->assertSame(1, $this->service->compareVersions('1.2.0', '1.1.9'));
    }

    public function testCompareVersionsPatchVersionDifference(): void
    {
        $this->assertSame(-1, $this->service->compareVersions('1.0.0', '1.0.1'));
    }

    public function testCompareVersionsMajorVersionDifference(): void
    {
        $this->assertSame(1, $this->service->compareVersions('3.0.0', '2.9.9'));
    }

    // -------------------------------------------------------------------------
    // checkUpdate (réseau non disponible en tests → null attendu)
    // -------------------------------------------------------------------------

    /**
     * En environnement de test sans accès réseau (ou si cURL n'est pas dispo),
     * checkUpdate doit retourner null sans lever d'exception.
     *
     * Ce test est marqué comme pouvant être ignoré si le réseau est disponible,
     * mais il valide que la méthode ne plante pas.
     */
    public function testCheckUpdateReturnsNullOrArrayWithRequiredKeys(): void
    {
        $result = $this->service->checkUpdate();

        if ($result === null) {
            // Réseau indisponible : comportement attendu en CI
            $this->addToAssertionCount(1);
        } else {
            $this->assertArrayHasKey('available', $result);
            $this->assertArrayHasKey('current', $result);
            $this->assertArrayHasKey('latest', $result);
            $this->assertArrayHasKey('url', $result);
            $this->assertSame('1.0.0', $result['current']);
        }
    }
}
