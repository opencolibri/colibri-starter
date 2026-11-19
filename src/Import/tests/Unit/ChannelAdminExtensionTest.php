<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Tests\Unit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Import\Extension\ChannelAdminExtension;
use OpenColibri\Starter\Import\Extension\ImportExtensions;
use OpenColibri\Starter\Import\ImportContext;
use OpenColibri\Starter\Import\Tests\CastorContainerTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function OpenColibri\Starter\Import\ensure_import_scaffold;
use function OpenColibri\Starter\Import\import_scaffold_marker_path;
use function OpenColibri\Starter\Import\list_import_shops;
use function OpenColibri\Starter\Import\load_project_config;
use function OpenColibri\Starter\Import\write_project_config;

use const OpenColibri\Starter\Import\IMPORT_DEFAULT_CHANNEL_CODE;

final class ChannelAdminExtensionTest extends TestCase
{
    use CastorContainerTrait;

    private const ADMIN_USER = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Entity\User;

        use Doctrine\ORM\Mapping as ORM;
        use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;

        #[ORM\Entity]
        #[ORM\Table(name: 'sylius_admin_user')]
        class AdminUser extends BaseAdminUser
        {
        }

        PHP;

    private const SYLIUS = "imports:\n    - { resource: '@SyliusCoreBundle/Resources/config/app/config.yml' }\n";

    private string $root;
    private string $previousCwd;
    private App $app;
    private Filesystem $filesystem;
    private ?ImportContext $previousContext;

    protected function setUp(): void
    {
        $this->setUpCastorContainer();
        // Restored afterwards: the tests run after this one may rely on no import context being set.
        $this->previousContext = ImportContext::tryCurrent();
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/castor-import-channel-admin-' . uniqid('', true);
        $this->app = new App('app', $this->root);
        $this->filesystem->mkdir($this->root . '/.castor');
        $this->previousCwd = getcwd() ?: $this->root;
        chdir($this->root);
        ImportContext::setCurrent(new ImportContext($this->app, 'app'));

        $this->filesystem->dumpFile(import_scaffold_marker_path($this->app), "<?php\n\nreturn [];\n");
        $this->filesystem->dumpFile($this->root . '/src/Entity/User/AdminUser.php', self::ADMIN_USER);
        $this->filesystem->dumpFile($this->root . '/config/packages/_sylius.yaml', self::SYLIUS);
        foreach (['atelier' => [], 'main' => ['shop' => ['channel' => IMPORT_DEFAULT_CHANNEL_CODE]]] as $slug => $config) {
            $this->filesystem->dumpFile($this->root . '/.castor/import/var/' . $slug . '/products.yaml', 'products: []');
            write_project_config($slug, ['slug' => $slug, 'mode' => 'existing'] + $config);
        }
    }

    protected function tearDown(): void
    {
        ImportExtensions::unregister(ChannelAdminExtension::NAME);
        (new \ReflectionProperty(ImportContext::class, 'current'))->setValue(null, $this->previousContext);
        chdir($this->previousCwd);
        $this->filesystem->remove($this->root);
    }

    public function testNothingReachesTheAppUntilAProjectEnablesIt(): void
    {
        ensure_import_scaffold($this->app, 'app');

        // No schema change, no patched entity, no replaced Sylius service.
        static::assertFileDoesNotExist($this->root . '/migrations/Version20260821120000.php');
        static::assertFileDoesNotExist($this->root . '/config/sylius/import_channel_admin.yaml');
        static::assertSame(self::ADMIN_USER, file_get_contents($this->root . '/src/Entity/User/AdminUser.php'));
        static::assertStringNotContainsString('import_channel_admin', (string) file_get_contents($this->root . '/config/packages/_sylius.yaml'));
        static::assertArrayNotHasKey('adminPassword', list_import_shops()[0]);
    }

    public function testItCopiesItsFilesIntoTheApp(): void
    {
        static::assertTrue(ChannelAdminExtension::copyFiles($this->app));
        static::assertFileEquals(ChannelAdminExtension::resourcesDir() . '/config/sylius/import_channel_admin.yaml', $this->root . '/config/sylius/import_channel_admin.yaml');
        static::assertFileExists($this->root . '/src/Security/Voter/ImportChannelAdminVoter.php');
        static::assertFalse(ChannelAdminExtension::copyFiles($this->app));
    }

    public function testItWiresTheChannelScopeOntoTheAdminUser(): void
    {
        static::assertTrue(ChannelAdminExtension::wireAdminUserEntity($this->app));

        $adminUser = (string) file_get_contents($this->root . '/src/Entity/User/AdminUser.php');
        static::assertStringContainsString('implements ImportChannelAdminAwareInterface', $adminUser);
        static::assertStringContainsString('use ImportChannelAdminAwareTrait;', $adminUser);
        static::assertFalse(ChannelAdminExtension::wireAdminUserEntity($this->app));
    }

    public function testEachShopGetsAnAdminWhosePasswordIsKept(): void
    {
        $extension = new ChannelAdminExtension();
        $target = ['channel' => 'ATELIER', 'subdomain' => 'atelier', 'shared' => false];
        $admin = static fn(array $fixtures): array => $fixtures['admin_user']['sylius_fixtures']['suites']['import']['fixtures']['import_admin_user']['options']['custom'][0];

        $first = $admin($extension->buildFixtures('atelier', $target));

        static::assertSame('ATELIER', $first['channel']);
        static::assertSame('atelier', $first['username']);
        static::assertSame('atelier@import.local', $first['email']);
        static::assertNotSame('', $first['password']);
        static::assertSame($first['password'], load_project_config('atelier')['admin_password'] ?? null);
        static::assertSame($first['password'], $admin($extension->buildFixtures('atelier', $target))['password']);
    }

    public function testTheDefaultChannelKeepsItsOwnAdmins(): void
    {
        static::assertSame([], (new ChannelAdminExtension())->buildFixtures('main', ['channel' => IMPORT_DEFAULT_CHANNEL_CODE, 'subdomain' => null, 'shared' => true]));
    }

    public function testItGivesTheAdminPasswordOfEachShop(): void
    {
        ImportExtensions::register(ChannelAdminExtension::NAME, new ChannelAdminExtension());
        write_project_config('atelier', ['slug' => 'atelier', 'mode' => 'existing', 'admin_password' => 'secret-1234']);

        $shops = array_column(list_import_shops(), null, 'slug');

        static::assertSame('secret-1234', $shops['atelier']['adminPassword']);
        static::assertNull($shops['main']['adminPassword']);
    }
}
