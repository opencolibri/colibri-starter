<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Import\Extension;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Theme\ThemeName;
use OpenColibri\Starter\Import\ImportContext;
use Symfony\Component\Finder\Finder;

use function Castor\fs;
use function Castor\run;
use function OpenColibri\Starter\Import\find_import_dataset;
use function OpenColibri\Starter\Import\import_shop_target;
use function OpenColibri\Starter\Import\load_import_dataset_config;
use function OpenColibri\Starter\Import\load_project_config;
use function OpenColibri\Starter\Import\project_has_generated_fixtures;
use function OpenColibri\Starter\Import\write_import_extension_fixture_files;
use function OpenColibri\Starter\Import\write_project_config;

/**
 * One storefront theme per imported shop, for several shops imported on their own channel in one Sylius app.
 *
 * Opt-in: a project enables it with ImportExtensions::register('shop_theme', new ShopThemeExtension()). Until then,
 * nothing of it reaches the app. Once enabled:
 *  - the theme of a shop is kept in its project.yaml ("theme"), the theme of its dataset until one is picked;
 *  - the import_channel_theme fixture (copied into the app) sets it on the channel each time the fixtures recreate it;
 *  - sylius:import:list gives the "theme" of each shop.
 *
 * The themes are the ones of opencolibri/starter-themes installed with the theme-bundle target: pick one with set(),
 * which applies it right away through "castor sylius:theme:channel".
 */
final class ShopThemeExtension implements ImportExtensionInterface
{
    public const NAME = 'shop_theme';

    public static function resourcesDir(): string
    {
        return ImportContext::packageResourcesDir() . '/extensions/shop_theme';
    }

    /**
     * The theme of a shop: the one picked for it, else the one of its dataset. Null when neither has one.
     *
     * @param array<string, mixed>|null $config the project.yaml of the shop
     */
    public static function theme(?array $config): ?string
    {
        $theme = trim((string) ($config['theme'] ?? ''));

        if ('' === $theme && '' !== trim((string) ($config['dataset'] ?? ''))) {
            $dataset = find_import_dataset(trim((string) $config['dataset']));
            $theme = null === $dataset ? '' : trim((string) (load_import_dataset_config($dataset['path'])['theme'] ?? ''));
        }

        return '' !== $theme ? $theme : null;
    }

    /**
     * Picks the theme of a shop ("default" for the Sylius look): applies it to its channel, keeps it in its
     * project.yaml, and in its fixtures when they are generated.
     *
     * @throws \RuntimeException for an unknown shop, or when the theme cannot be applied (not installed...)
     */
    public static function set(string $project, string $theme): void
    {
        $config = load_project_config($project) ?? throw new \RuntimeException(\sprintf('Unknown shop "%s".', $project));
        $target = import_shop_target($project);

        // First, so that a theme sylius:theme:channel refuses is not kept.
        run([getenv('CASTOR_BINARY') ?: 'castor', 'sylius:theme:channel', '--no-interaction', $target['channel'], $theme]);

        $config['theme'] = $theme;
        write_project_config($project, $config);

        // The next fixtures load sets it again on the channel it recreates.
        if (project_has_generated_fixtures($project)) {
            write_import_extension_fixture_files($project, $target);
        }
    }

    public function scaffold(App $app): void
    {
        foreach ((new Finder())->files()->in(self::resourcesDir()) as $file) {
            $target = $app->directory() . '/' . $file->getRelativePathname();

            // Owned by the extension: kept up to date.
            if (!is_file($target) || file_get_contents($target) !== $file->getContents()) {
                fs()->copy($file->getPathname(), $target, true);
            }
        }
    }

    public function buildFixtures(string $projectSlug, array $target): array
    {
        $theme = self::theme(load_project_config($projectSlug));

        if (null === $theme) {
            return [];
        }

        return [
            'channel_theme' => [
                'sylius_fixtures' => [
                    'suites' => [
                        'import' => [
                            'fixtures' => [
                                'import_channel_theme' => [
                                    'name' => 'import_channel_theme',
                                    // After the channel fixture, which recreates the channel.
                                    'priority' => -100,
                                    'options' => [
                                        'custom' => [
                                            ['channel' => $target['channel'], 'theme_name' => ThemeName::of($theme)],
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
        return ['theme' => self::theme($config) ?? ThemeName::DEFAULT];
    }
}
