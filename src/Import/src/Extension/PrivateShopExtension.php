<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\PhpFile;
use OpenColibri\Starter\Core\Util\Database;
use OpenColibri\Starter\Core\Util\Runner;
use OpenColibri\Starter\Import\ImportContext;
use Symfony\Component\Finder\Finder;

use function Castor\fs;
use function OpenColibri\Starter\Import\import_log;
use function OpenColibri\Starter\Import\import_shop_target;
use function OpenColibri\Starter\Import\import_shop_user_email;
use function OpenColibri\Starter\Import\load_project_config;
use function OpenColibri\Starter\Import\project_has_generated_fixtures;
use function OpenColibri\Starter\Import\write_import_extension_fixture_files;
use function OpenColibri\Starter\Import\write_project_config;

/**
 * Private shops: only the demo customer of a shop imported on its own channel (its storefront login and password)
 * gets into its storefront and shop API. For demos shared with one customer.
 *
 * Opt-in: a project enables it with ImportExtensions::register('private_shop', new PrivateShopExtension()). Until
 * then, nothing of it reaches the app. Once enabled, its scaffold copies into the app a private shop field on the
 * channel (entity trait and migration), a voter and its access_control rules, a login page, and the
 * import_private_shop fixture and sylius:import:channel:private command that set it. A shop is made private with
 * set(), kept in its project.yaml ("private") and set again on each fixtures load. The default channel, which hosts
 * the shops imported on it, stays public.
 */
final class PrivateShopExtension implements ImportExtensionInterface
{
    public const NAME = 'private_shop';

    /** access_control rules of the private shops, each inserted after the rule its path matches. */
    public const ACCESS_CONTROL = [
        // Storefront: after the login, register, verify, admin and account rules, which keep their access. The shop
        // firewall also covers the web debug toolbar (dev only), which the login page loads.
        '%sylius.security.shop_regex%/account' => '        - { path: "^/_(wdt|profiler)/", role: PUBLIC_ACCESS }' . "\n"
            . '        - { path: "%sylius.security.shop_regex%", allow_if: "is_granted(\'import_private_shop_access\')" }',
        // Shop API: after the customer token rule, before the rule opening the rest of it.
        '%sylius.security.api_shop_route%/customers/token' => '        - { path: "%sylius.security.api_shop_regex%/.*", allow_if: "is_granted(\'import_private_shop_access\')" }',
    ];

    public static function resourcesDir(): string
    {
        return ImportContext::packageResourcesDir() . '/extensions/private_shop';
    }

    /**
     * @param array<string, mixed>|null $config the project.yaml of the shop
     */
    public static function isPrivate(?array $config): bool
    {
        return true === ($config['private'] ?? false);
    }

    /**
     * Makes a shop private (only its demo customer gets in) or public, right away, and keeps it in its project.yaml
     * and in its fixtures when they are generated.
     *
     * @throws \RuntimeException for an unknown shop, or a shop imported on the default channel
     */
    public static function set(string $project, bool $private): void
    {
        $config = load_project_config($project) ?? throw new \RuntimeException(\sprintf('Unknown shop "%s".', $project));
        $target = import_shop_target($project);

        if ($target['shared']) {
            throw new \RuntimeException(\sprintf('The shop "%s" is imported on the default channel, which stays public.', $project));
        }

        // First, so that a shop the app could not change is not kept private.
        Runner::run(ImportContext::current()->app(), \sprintf(
            'php bin/console sylius:import:channel:private --no-interaction %s%s',
            escapeshellarg($target['channel']),
            $private ? ' --shop-user-email=' . escapeshellarg(import_shop_user_email($project)) : '',
        ));

        $config['private'] = $private;
        write_project_config($project, $config);

        // The next fixtures load sets it again on the channel it recreates.
        if (project_has_generated_fixtures($project)) {
            write_import_extension_fixture_files($project, $target);
        }
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
     * sylius-standard always ships src/Entity/Channel/Channel.php, so the private shop field is wired onto it.
     *
     * @return bool whether the entity was changed
     */
    public static function wireChannelEntity(App $app): bool
    {
        $path = $app->directory() . '/src/Entity/Channel/Channel.php';

        if (!is_file($path) || str_contains((string) file_get_contents($path), 'ImportPrivateShopAwareTrait')) {
            return false;
        }

        (new PhpFile($path))
            ->addInterface('App\\Entity\\Channel\\ImportPrivateShopAwareInterface')
            ->addTrait('App\\Entity\\Channel\\ImportPrivateShopAwareTrait')
            ->save()
        ;
        import_log('Wired the private shop field onto src/Entity/Channel/Channel.php.');

        return true;
    }

    /**
     * The firewall config is one block (security.access_control cannot be split across files), so the rules are
     * inserted into the access_control of sylius-standard.
     */
    public static function protectStorefront(App $app): void
    {
        $path = $app->directory() . '/config/packages/security.yaml';
        $content = (string) file_get_contents($path);

        if (str_contains($content, 'import_private_shop_access')) {
            return;
        }

        foreach (self::ACCESS_CONTROL as $after => $rule) {
            $pattern = \sprintf('/^\s*- \{ path: "%s".*$/m', preg_quote($after, '/'));
            $content = preg_replace($pattern, '$0' . "\n" . $rule, $content, 1, $count) ?? $content;

            if (1 !== $count) {
                throw new \RuntimeException(\sprintf('Could not find the "%s" access_control rule in config/packages/security.yaml to protect the private shops.', $after));
            }
        }

        fs()->dumpFile($path, $content);
        import_log('Added the private shop access_control rules to config/packages/security.yaml.');
    }

    public function scaffold(App $app): void
    {
        $changed = self::copyFiles($app);
        $changed = self::wireChannelEntity($app) || $changed;
        self::protectStorefront($app);

        if ($changed) {
            Database::migrate($app);
        }
    }

    public function buildFixtures(string $projectSlug, array $target): array
    {
        // The default channel hosts the shops imported on it: it stays public.
        if ($target['shared']) {
            return [];
        }

        return [
            'private_shop' => [
                'sylius_fixtures' => [
                    'suites' => [
                        'import' => [
                            'fixtures' => [
                                'import_private_shop' => [
                                    'name' => 'import_private_shop',
                                    // After the channel fixture, which recreates the channel.
                                    'priority' => -100,
                                    'options' => [
                                        'custom' => [
                                            [
                                                'channel' => $target['channel'],
                                                'shop_user_email' => self::isPrivate(load_project_config($projectSlug)) ? import_shop_user_email($projectSlug) : null,
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
        return [
            'private' => self::isPrivate($config),
            // The default channel hosts the shops imported on it: it stays public.
            'canBePrivate' => !import_shop_target($projectSlug)['shared'],
        ];
    }
}
