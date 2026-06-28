<?php

declare(strict_types=1);

namespace ICO\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseVersionTest extends TestCase
{
    public function testVersionFileMatchesLatestLocalReleaseTagWhenTagsAreAvailable(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $versionFile = $projectRoot . '/version.txt';

        $this->assertFileExists($versionFile);

        $latestTag = $this->latestLocalReleaseTag($projectRoot);
        if ($latestTag === null) {
            $this->markTestSkipped('Aucun tag git local disponible pour vérifier version.txt.');
        }

        $this->assertSame(
            $latestTag,
            trim((string) file_get_contents($versionFile)),
            'version.txt doit correspondre au dernier tag de release local.'
        );
    }

    private function latestLocalReleaseTag(string $projectRoot): ?string
    {
        $tags    = [];
        $command = 'git -C ' . escapeshellarg($projectRoot) . ' tag --list --sort=-version:refname';
        exec($command, $tags, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        foreach ($tags as $tag) {
            if (preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
