<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Yaml;
use OpenColibri\Starter\Import\ImportContext;
use Symfony\Component\Finder\Finder;

use function Castor\fs;
use function OpenColibri\Starter\Import\generate_import_password;
use function OpenColibri\Starter\Import\import_code_prefix;
use function OpenColibri\Starter\Import\import_log;
use function OpenColibri\Starter\Import\import_project_display_name;
use function OpenColibri\Starter\Import\load_project_config;
use function OpenColibri\Starter\Import\write_project_config;

/**
 * One Sylius admin per shop imported on its own channel, restricted to that channel: it only sees and edits the
 * catalog of its shop. For demos where each customer gets the admin of their shop.
 *
 * Opt-in: a project enables it with ImportExtensions::register('channel_admin', new ChannelAdminExtension()). Until
 * then, nothing of it reaches the app: no schema change, no patched entity, no replaced Sylius service. Once enabled,
 * its scaffold adds the channel scope on the admin user (trait and migration, wired onto AdminUser), the voter,
 * access and menu listeners and product form extension that enforce it, the product, variant and taxon repositories
 * filtering by channel (config/sylius/import_channel_admin.yaml replaces the Sylius ones), the reset listener removing
 * the admins of a reset channel and the import_admin_user fixture. Each shop gets the admin "<slug>", whose password is
 * generated once and kept in its project.yaml ("admin_password"). The default channel keeps its own admins.
 */
final class ChannelAdminExtension implements ImportExtensionInterface
{
    public const NAME = 'channel_admin';

    public static function resourcesDir(): string
    {
        return ImportContext::packageResourcesDir() . '/extensions/channel_admin';
    }

    /**
     * The password of the admin of a shop, generated on the first fixtures generation and kept afterwards.
     */
    public static function password(string $projectSlug): string
    {
        $config = load_project_config($projectSlug) ?? [];
        $password = trim((string) ($config['admin_password'] ?? ''));

        if ('' === $password) {
            $password = generate_import_password();
            $config['admin_password'] = $password;
            write_project_config($projectSlug, $config);
        }

        return $password;
    }

    /**
     * @return bool whether a file was added or changed
     */
    public static function copyFiles(App $app): bool
    {
        $changed = false;

        foreach ((new Finder())->files()->in(self::resourcesDir()) as $file) {
            $target = $app->directory() . '/' . $file->getRelativePathname();

            // Owned by the extension: kept up to date.
            if (!is_file($target) || file_get_contents($target) !== $file->getContents()) {
                fs()->copy($file->getPathname(), $target, true);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * sylius-standard always ships src/Entity/User/AdminUser.php, so the channel scope is wired onto it.
     *
     * @return bool whether the entity was changed
     */
    public static function wireAdminUserEntity(App $app): bool
    {
        $path = $app->directory() . '/src/Entity/User/AdminUser.php';

        if (!is_file($path) || str_contains((string) file_get_contents($path), 'ImportChannelAdminAwareTrait')) {
            return false;
        }

        (new PhpFile($path))
            ->addInterface('App\\Entity\\User\\ImportChannelAdminAwareInterface')
            ->addTrait('App\\Entity\\User\\ImportChannelAdminAwareTrait')
            ->save()
        ;
        import_log('Wired the import channel admin onto src/Entity/User/AdminUser.php.');

        return true;
    }

    public function scaffold(App $app): void
    {
        $changed = self::copyFiles($app);
        $changed = self::wireAdminUserEntity($app) || $changed;

        Yaml::addImport($app, 'config/packages/_sylius.yaml', '../sylius/import_channel_admin.yaml');

        if ($changed) {
            Database::migrate($app);
        }
    }

    public function buildFixtures(string $projectSlug, array $target): array
    {
        // The default channel already has its own admins.
        if ($target['shared']) {
            return [];
        }

        return [
            'admin_user' => [
                'sylius_fixtures' => [
                    'suites' => [
                        'import' => [
                            'fixtures' => [
                                'import_admin_user' => [
                                    'name' => 'import_admin_user',
                                    'options' => [
                                        'custom' => [
                                            [
                                                'channel' => $target['channel'],
                                                'username' => $projectSlug,
                                                'password' => self::password($projectSlug),
                                                'first_name' => import_project_display_name($projectSlug),
                                                'email' => $projectSlug . '@import.local',
                                                'import_code_prefix' => import_code_prefix($projectSlug),
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function describeShop(string $projectSlug, ?array $config): array
    {
        $password = trim((string) ($config['admin_password'] ?? ''));

        return ['adminPassword' => '' !== $password ? $password : null];
    }
}
