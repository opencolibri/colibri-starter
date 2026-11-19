<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OpenColibri\Starter\Monorepo\Monorepo;

final class MonorepoTest extends TestCase
{
    private Monorepo $monorepo;

    protected function setUp(): void
    {
        $this->monorepo = new Monorepo(\dirname(__DIR__, 2));
    }

    public function testTheMonorepoIsValid(): void
    {
        static::assertSame([], $this->monorepo->validate());
    }

    public function testCoreIsTheFirstPackage(): void
    {
        static::assertSame('opencolibri/starter-core', $this->monorepo->packages()[0]['name']);
    }

    public function testRootComposerReplacesEveryPackage(): void
    {
        $replace = $this->monorepo->rootComposer()['replace'] ?? [];

        foreach ($this->monorepo->packages() as $package) {
            static::assertSame('self.version', $replace[$package['name']] ?? null, $package['name']);
        }
    }

    public function testItResolvesPackagesByShortNameOrDirectory(): void
    {
        static::assertSame('src/Themes', $this->monorepo->package('themes')['path']);
        static::assertSame('src/Themes', $this->monorepo->package('Themes')['path']);
        static::assertSame('src/Themes', $this->monorepo->package('opencolibri/starter-themes')['path']);
    }

    public function testItBuildsRemoteUrls(): void
    {
        static::assertSame('git@github.com:opencolibri/starter-core.git', Monorepo::remoteUrl('git@github.com:{name}.git', 'opencolibri/starter-core'));
        static::assertSame('https://github.com/acme/sylius-core.git', Monorepo::remoteUrl('https://github.com/acme/sylius-{short}.git', 'opencolibri/starter-core'));
        static::assertSame('git@github.com:opencolibri/starter-core.git', Monorepo::remoteUrl('git@github.com:{repository}.git', 'opencolibri/starter-core'));
        static::assertSame('git@github.com:opencolibri/starter-menu.git', Monorepo::remoteUrl('git@github.com:{repository}.git', 'opencolibri/starter-menu'));
    }

    public function testItDetectsAnUnsyncedRootComposer(): void
    {
        $root = $this->monorepo->rootComposer();
        unset($root['replace']['opencolibri/starter-themes']);

        static::assertNotSame($root, $this->monorepo->mergedRootComposer($root));
        static::assertSame($this->monorepo->rootComposer(), $this->monorepo->mergedRootComposer($root));
    }

    public function testTheDependenciesOfTheSymfonyBundlesStayOutOfTheCastorRuntime(): void
    {
        $merged = $this->monorepo->mergedRootComposer();

        // castor.composer.json installs the root package as a dependency: its require-dev is ignored.
        static::assertArrayNotHasKey('sylius/shadcn-admin-ui', $merged['require']);
        static::assertArrayHasKey('sylius/shadcn-admin-ui', $merged['require-dev']);
        static::assertContains('sylius/shadcn-admin-ui', $merged['extra']['monorepo'][Monorepo::BUNDLE_DEV_DEPENDENCIES_KEY]);
        // A dependency of a Castor package stays in require.
        static::assertArrayHasKey('symfony/yaml', $merged['require']);
        static::assertArrayNotHasKey('symfony/yaml', $merged['require-dev']);
        // The root-only dev dependencies are kept.
        static::assertArrayHasKey('phpunit/phpunit', $merged['require-dev']);
    }

    public function testItRemovesADependencyABundleNoLongerRequires(): void
    {
        $root = $this->monorepo->rootComposer();
        $root['require-dev']['acme/dropped-bundle-dependency'] = '^1.0';
        $root['extra']['monorepo'][Monorepo::BUNDLE_DEV_DEPENDENCIES_KEY][] = 'acme/dropped-bundle-dependency';

        static::assertSame($this->monorepo->rootComposer(), $this->monorepo->mergedRootComposer($root));
    }
}
