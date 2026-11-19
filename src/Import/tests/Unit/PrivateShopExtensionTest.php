<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Tests\Unit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Import\Extension\ImportExtensions;
use OpenColibri\Starter\Import\Extension\PrivateShopExtension;
use OpenColibri\Starter\Import\ImportContext;
use OpenColibri\Starter\Import\Tests\CastorContainerTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function OpenColibri\Starter\Import\ensure_import_scaffold;
use function OpenColibri\Starter\Import\import_scaffold_marker_path;
use function OpenColibri\Starter\Import\list_import_shops;
use function OpenColibri\Starter\Import\write_project_config;

use const OpenColibri\Starter\Import\IMPORT_DEFAULT_CHANNEL_CODE;

final class PrivateShopExtensionTest extends TestCase
{
    use CastorContainerTrait;

    private const SECURITY = <<<'YAML'
        security:
            access_control:
                - { path: "%sylius.security.shop_regex%/login", role: PUBLIC_ACCESS }
                - { path: "%sylius.security.shop_regex%/account", role: ROLE_USER }
                - { path: "%sylius.security.api_shop_route%/customers/token", role: PUBLIC_ACCESS }
                - { path: "%sylius.security.api_shop_regex%/.*", role: PUBLIC_ACCESS }

        YAML;

    private const CHANNEL = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Entity\Channel;

        use Doctrine\ORM\Mapping as ORM;
        use Sylius\Component\Core\Model\Channel as BaseChannel;

        #[ORM\Entity]
        #[ORM\Table(name: 'sylius_channel')]
        class Channel extends BaseChannel
        {
        }

        PHP;

    private string $root;
    private string $previousCwd;
    private App $app;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->setUpCastorContainer();
        $this->filesystem = new Filesystem();
        $this->root = sys_get_temp_dir() . '/castor-import-private-shop-' . uniqid('', true);
        $this->app = new App('app', $this->root);
        $this->filesystem->mkdir($this->root . '/.castor');
        $this->previousCwd = getcwd() ?: $this->root;
        chdir($this->root);
        ImportContext::setCurrent(new ImportContext($this->app, 'app'));

        $this->filesystem->dumpFile(import_scaffold_marker_path($this->app), "<?php\n\nreturn [];\n");
        $this->filesystem->dumpFile($this->root . '/config/packages/security.yaml', self::SECURITY);
        $this->filesystem->dumpFile($this->root . '/src/Entity/Channel/Channel.php', self::CHANNEL);
        // A private shop, a public one, one on the default channel.
        foreach (['secret' => ['private' => true], 'open' => [], 'main' => ['shop' => ['channel' => IMPORT_DEFAULT_CHANNEL_CODE]]] as $slug => $config) {
            $this->filesystem->dumpFile($this->root . '/.castor/import/var/' . $slug . '/products.yaml', 'products: []');
            write_project_config($slug, ['slug' => $slug, 'mode' => 'existing'] + $config);
        }
    }

    protected function tearDown(): void
    {
        ImportExtensions::unregister(PrivateShopExtension::NAME);
        chdir($this->previousCwd);
        $this->filesystem->remove($this->root);
    }

    public function testNothingReachesTheAppUntilAProjectEnablesIt(): void
    {
        ensure_import_scaffold($this->app, 'app');

        static::assertFileDoesNotExist($this->root . '/src/Security/Voter/ImportPrivateShopVoter.php');
        static::assertFileDoesNotExist($this->root . '/migrations/Version20261027120000.php');
        static::assertSame(self::SECURITY, file_get_contents($this->root . '/config/packages/security.yaml'));
        static::assertSame(self::CHANNEL, file_get_contents($this->root . '/src/Entity/Channel/Channel.php'));
        static::assertArrayNotHasKey('private', list_import_shops()[0]);
    }

    public function testItCopiesItsFilesIntoTheApp(): void
    {
        static::assertTrue(PrivateShopExtension::copyFiles($this->app));
        static::assertFileEquals(PrivateShopExtension::resourcesDir() . '/src/Security/Voter/ImportPrivateShopVoter.php', $this->root . '/src/Security/Voter/ImportPrivateShopVoter.php');
        static::assertFileExists($this->root . '/templates/shop/private_shop/login.html.twig');
        // Nothing changed: no migration to run.
        static::assertFalse(PrivateShopExtension::copyFiles($this->app));
    }

    public function testItWiresThePrivateShopFieldOntoTheChannel(): void
    {
        static::assertTrue(PrivateShopExtension::wireChannelEntity($this->app));

        $channel = (string) file_get_contents($this->root . '/src/Entity/Channel/Channel.php');
        static::assertStringContainsString('implements ImportPrivateShopAwareInterface', $channel);
        static::assertStringContainsString('use ImportPrivateShopAwareTrait;', $channel);
        static::assertFalse(PrivateShopExtension::wireChannelEntity($this->app));
    }

    public function testItProtectsTheStorefrontAndTheShopApiOnce(): void
    {
        PrivateShopExtension::protectStorefront($this->app);
        PrivateShopExtension::protectStorefront($this->app);

        $security = (string) file_get_contents($this->root . '/config/packages/security.yaml');
        static::assertSame(2, substr_count($security, "is_granted('import_private_shop_access')"));
        // After the account rule, which keeps its access.
        static::assertLessThan(strpos($security, 'allow_if'), strpos($security, '/account"'));
    }

    public function testItSaysWhenTheAccessControlRulesAreMissing(): void
    {
        $this->filesystem->dumpFile($this->root . '/config/packages/security.yaml', "security:\n    access_control: []\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('to protect the private shops');

        PrivateShopExtension::protectStorefront($this->app);
    }

    public function testItKeepsAPrivateShopForItsDemoCustomer(): void
    {
        $extension = new PrivateShopExtension();
        $custom = static fn(array $fixtures): array => $fixtures['private_shop']['sylius_fixtures']['suites']['import']['fixtures']['import_private_shop']['options']['custom'];

        static::assertSame([['channel' => 'SECRET', 'shop_user_email' => 'secret@shop.local']], $custom($extension->buildFixtures('secret', ['channel' => 'SECRET', 'subdomain' => 'secret', 'shared' => false])));
        // A public shop is reset to public on each load.
        static::assertSame([['channel' => 'OPEN', 'shop_user_email' => null]], $custom($extension->buildFixtures('open', ['channel' => 'OPEN', 'subdomain' => 'open', 'shared' => false])));
        static::assertSame([], $extension->buildFixtures('main', ['channel' => IMPORT_DEFAULT_CHANNEL_CODE, 'subdomain' => null, 'shared' => true]));
    }

    public function testItGivesTheVisibilityOfEachShop(): void
    {
        ImportExtensions::register(PrivateShopExtension::NAME, new PrivateShopExtension());

        $shops = array_column(list_import_shops(), null, 'slug');

        static::assertTrue($shops['secret']['private']);
        static::assertTrue($shops['secret']['canBePrivate']);
        static::assertFalse($shops['open']['private']);
        static::assertFalse($shops['main']['canBePrivate']);
    }

    public function testItRefusesToChangeAnUnknownShopOrTheDefaultChannel(): void
    {
        try {
            PrivateShopExtension::set('unknown', true);
            static::fail('No error for an unknown shop.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Unknown shop "unknown".', $exception->getMessage());
        }

        $this->expectExceptionMessage('The shop "main" is imported on the default channel, which stays public.');

        PrivateShopExtension::set('main', true);
    }
}
