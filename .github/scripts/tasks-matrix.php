<?php

declare(strict_types=1);

/*
 * Prints the Castor tasks the CI runs on the test application, as a JSON list for a
 * GitHub Actions matrix: [{"name":"Volt theme","value":"castor sylius:theme:setup volt"}, ...]
 *
 *   php .github/scripts/tasks-matrix.php --all            every task
 *   git diff --name-only A B | php .github/scripts/tasks-matrix.php
 *                                                          only the tasks the changed files affect
 *
 * Themes are discovered from src/Themes/resources/. A changed file the rules below do not
 * know runs every task, so a new folder is tested until a rule says otherwise.
 * Why each task was selected is printed on stderr.
 *
 * Standalone on purpose (no Castor, no Composer install needed).
 */

$root = dirname(__DIR__, 2);

/** Tasks that do not come from a theme, with the paths (prefixes) that affect them. */
$plugin = static fn (string $name, string $value, array $paths): array => [
    'name' => $name,
    'value' => $value,
    'group' => 'plugins',
    'paths' => $paths,
];

$tasks = [
    $plugin('ALTCHA Plugin', 'castor sylius:add altcha && castor sylius:remove altcha', [
        'src/Plugins/src/Installer/AltchaInstaller.php',
        'src/Plugins/src/Remover/AltchaRemover.php',
        'src/Plugins/resources/altcha/',
    ]),
    $plugin('Google Recaptcha Plugin', 'castor sylius:add recaptcha && castor sylius:remove recaptcha', [
        'src/Plugins/src/Installer/RecaptchaInstaller.php',
        'src/Plugins/src/Remover/RecaptchaRemover.php',
        'src/Plugins/resources/recaptcha/',
    ]),
    $plugin('Sylius API', 'castor sylius:remove api', [
        'src/Plugins/src/Remover/ApiRemover.php',
    ]),
    $plugin('Symfony BugSnag Plugin', 'castor sylius:add bugsnag && castor sylius:remove bugsnag', [
        'src/Plugins/src/Installer/BugSnagInstaller.php',
        'src/Plugins/src/Remover/BugSnagRemover.php',
    ]),
    $plugin('Sylius CMS Plugin', 'castor sylius:add cms && castor sylius:remove cms', [
        'src/Plugins/src/Installer/CmsInstaller.php',
        'src/Plugins/src/Remover/CmsRemover.php',
    ]),
    $plugin('Synolia GDPR Plugin', 'castor sylius:add gdpr || true', [
        'src/Plugins/src/Installer/GdprInstaller.php',
        'src/Plugins/src/Remover/GdprRemover.php',
    ]),
    $plugin('Sylius Invoicing Plugin', 'castor sylius:add invoicing && castor sylius:remove invoicing', [
        'src/Plugins/src/Installer/InvoicingInstaller.php',
        'src/Plugins/src/Remover/InvoicingRemover.php',
    ]),
    $plugin('Sylius Refund Plugin', 'castor sylius:add refund', [
        'src/Plugins/src/Installer/RefundInstaller.php',
    ]),
    $plugin('Sylius Product Bundle Plugin', 'castor sylius:add product_bundle', [
        'src/Plugins/src/Installer/ProductBundleInstaller.php',
    ]),
    $plugin('Sylius Wishlist Plugin', 'castor sylius:add wishlist && castor sylius:remove wishlist', [
        'src/Plugins/src/Installer/WishlistInstaller.php',
        'src/Plugins/src/Remover/WishlistRemover.php',
    ]),
    $plugin('Custom plugin installers', 'castor sylius:plugin:add test_installer_with_function test_installer_with_class', [
        '.castor/app/TestInstaller.php',
    ]),
    $plugin('Custom plugin removers', 'castor sylius:plugin:remove test_remover_with_function test_remover_with_class', [
        '.castor/app/TestRemover.php',
    ]),
    [
        'name' => 'Remove Menu items',
        'value' => 'castor sylius:menu:remove official_support sylius.ui.administration',
        'group' => 'menu',
        'paths' => [],
    ],
    // Loaded by the Core package (SyliusService): only a global change runs it.
    [
        'name' => 'Run Fixtures',
        'value' => 'castor sylius:fixtures -n',
        'group' => 'fixtures',
        'paths' => [],
    ],
];

foreach (glob($root . '/src/Themes/resources/*', \GLOB_ONLYDIR) ?: [] as $dir) {
    $theme = basename($dir);
    $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $theme)));
    $tasks[] = [
        'name' => ucwords(str_replace('_', ' ', $theme)) . ' theme',
        'value' => 'castor sylius:theme:setup ' . $theme,
        'group' => 'themes',
        'paths' => [
            'src/Themes/resources/' . $theme . '/',
            'src/Themes/src/Installer/' . $class . 'Installer.php',
            'src/Themes/src/Remover/' . $class . 'Remover.php',
        ],
    ];
}

/** Paths that run a whole group of tasks (shared code of a package). */
$groups = [
    'src/Themes/' => 'themes',
    'src/Plugins/' => 'plugins',
    'src/Menu/' => 'menu',
];

/** Paths no task depends on (covered by the other jobs, or not shipped to the app). */
$ignored = [
    'docs/',
    'tests/',
    'scripts/',
    'mockups/',
    '.github/workflows/docs.yml',
    '.github/workflows/split.yml',
    '.github/scripts/monorepo-matrix.php',
    '.phpstan/',
    'phpstan.dist.neon',
    'phpunit.xml.dist',
    '.php-cs-fixer.dist.php',
    '.gitignore',
    '.gitattributes',
    'LICENSE',
    // Packages no task runs: their unit tests and PHPStan cover them.
    'src/Datasets/',
    'src/Import/',
    'src/Upsun/',
    // The sales kit admin bundle is never installed in the test application.
    'src/SalesKit/',
    // Theme thumbnails, for theme pickers: never installed in the app.
    'src/Themes/previews/',
    'src/Themes/src/ThemePreview.php',
];

$startsWith = static function (string $file, array $prefixes): bool {
    foreach ($prefixes as $prefix) {
        if (str_starts_with($file, $prefix)) {
            return true;
        }
    }

    return false;
};

$selected = [];
$all = in_array('--all', $argv, true);

if (!$all) {
    $files = array_filter(array_map('trim', explode("\n", (string) stream_get_contents(\STDIN))));

    foreach ($files as $file) {
        // Package tests and Markdown files never reach the app.
        if (str_ends_with($file, '.md') || preg_match('#^src/[^/]+/tests/#', $file) || $startsWith($file, $ignored)) {
            fwrite(\STDERR, "{$file}: no task\n");

            continue;
        }

        $matched = array_filter($tasks, static fn (array $task): bool => $startsWith($file, $task['paths']));

        if ([] === $matched) {
            foreach ($groups as $prefix => $group) {
                if (str_starts_with($file, $prefix)) {
                    $matched = array_filter($tasks, static fn (array $task): bool => $task['group'] === $group);

                    break;
                }
            }
        }

        if ([] === $matched) {
            fwrite(\STDERR, "{$file}: every task\n");
            $all = true;

            break;
        }

        fwrite(\STDERR, \sprintf("%s: %s\n", $file, implode(', ', array_column($matched, 'name'))));
        foreach ($matched as $task) {
            $selected[$task['name']] = true;
        }
    }
}

$matrix = array_values(array_map(
    static fn (array $task): array => ['name' => $task['name'], 'value' => $task['value']],
    array_filter($tasks, static fn (array $task): bool => $all || isset($selected[$task['name']])),
));

echo json_encode($matrix, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), "\n";
