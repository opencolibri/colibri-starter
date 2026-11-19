<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Tests\Unit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Import\Extension\ImportExtensions;
use OpenColibri\Starter\Import\Extension\ShopThemeExtension;
use OpenColibri\Starter\Import\ImportContext;
use OpenColibri\Starter\Import\Tests\CastorContainerTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function OpenColibri\Starter\Import\ensure_import_scaffold;
use function OpenColibri\Starter\Import\import_scaffold_marker_path;
use function OpenColibri\Starter\Import\list_import_shops;
use function OpenColibri\Starter\Import\write_project_config;

final class ShopThemeExtensionTest extends TestCase
{
    use CastorContainerTrait;

    private const FIXTURE = 'src/Fixture/ImportChannelThemeFixture.php';

    private string $root;
    private string $previousCwd;
    private App $app;

    protected function setUp(): void
    {
        $this->setUpCastorContainer();
        $this->root = sys_get_temp_dir() . '/castor-import-shop-theme-' . uniqid('', true);
        $this->app = new App('app', $this->root);
        (new Filesystem())->mkdir($this->root . '/.castor');
        $this->previousCwd = getcwd() ?: $this->root;
        chdir($this->root);
        ImportContext::setCurrent(new ImportContext($this->app, 'app'));

        // A deployed import scaffold.
        (new Filesystem())->dumpFile(import_scaffold_marker_path($this->app), "<?php\n\nreturn [];\n");
        // A dataset built here, with a theme.
        (new Filesystem())->dumpFile($this->root . '/.castor/import/datasets/wines/products.yaml', 'products: []');
        (new Filesystem())->dumpFile($this->root . '/.castor/import/datasets/wines/dataset.yaml', 'theme: cellar');
        // Three shops: a theme picked, the theme of their dataset, none.
        foreach (['picked' => ['theme' => 'retro_gaming', 'dataset' => 'wines'], 'from-dataset' => ['dataset' => 'wines'], 'plain' => []] as $slug => $config) {
            (new Filesystem())->dumpFile($this->root . '/.castor/import/var/' . $slug . '/products.yaml', 'products: []');
            write_project_config($slug, ['slug' => $slug, 'mode' => 'existing'] + $config);
        }
    }

    protected function tearDown(): void
    {
        ImportExtensions::unregister(ShopThemeExtension::NAME);
        chdir($this->previousCwd);
        (new Filesystem())->remove($this->root);
    }

    public function testNothingReachesTheAppUntilAProjectEnablesIt(): void
    {
        ensure_import_scaffold($this->app, 'app');

        static::assertFileDoesNotExist($this->root . '/' . self::FIXTURE);
        static::assertArrayNotHasKey('theme', list_import_shops()[0]);
    }

    public function testItCopiesItsFixtureIntoTheApp(): void
    {
        ImportExtensions::register(ShopThemeExtension::NAME, new ShopThemeExtension());

        ensure_import_scaffold($this->app, 'app');

        static::assertFileEquals(ShopThemeExtension::resourcesDir() . '/' . self::FIXTURE, $this->root . '/' . self::FIXTURE);
    }

    public function testItGivesTheThemeOfEachShop(): void
    {
        ImportExtensions::register(ShopThemeExtension::NAME, new ShopThemeExtension());

        $themes = array_column(list_import_shops(), 'theme', 'slug');

        static::assertSame('retro_gaming', $themes['picked']);
        static::assertSame('cellar', $themes['from-dataset']);
        static::assertSame('default', $themes['plain']);
    }

    public function testItSetsTheThemeOnTheChannelTheFixturesRecreate(): void
    {
        $extension = new ShopThemeExtension();
        $target = ['channel' => 'PICKED', 'subdomain' => 'picked', 'shared' => false];

        static::assertSame(
            [['channel' => 'PICKED', 'theme_name' => 'opencolibri/retro-gaming']],
            $extension->buildFixtures('picked', $target)['channel_theme']['sylius_fixtures']['suites']['import']['fixtures']['import_channel_theme']['options']['custom'],
        );
        static::assertSame('opencolibri/cellar', $extension->buildFixtures('from-dataset', $target)['channel_theme']['sylius_fixtures']['suites']['import']['fixtures']['import_channel_theme']['options']['custom'][0]['theme_name']);
        // No theme picked and no dataset theme: the channel is left alone.
        static::assertSame([], $extension->buildFixtures('plain', $target));
    }

    public function testTheSyliusLookClearsTheThemeOfTheChannel(): void
    {
        write_project_config('plain', ['slug' => 'plain', 'mode' => 'existing', 'theme' => 'default']);

        $fixture = (new ShopThemeExtension())->buildFixtures('plain', ['channel' => 'PLAIN', 'subdomain' => 'plain', 'shared' => false]);

        static::assertNull($fixture['channel_theme']['sylius_fixtures']['suites']['import']['fixtures']['import_channel_theme']['options']['custom'][0]['theme_name']);
    }

    public function testItRefusesToPickTheThemeOfAnUnknownShop(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown shop "unknown".');

        ShopThemeExtension::set('unknown', 'cellar');
    }
}
