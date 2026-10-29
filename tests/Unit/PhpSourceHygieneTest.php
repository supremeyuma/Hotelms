<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards against PHP files that emit output before their opening tag.
 *
 * A single byte before `<?php` in a file that is included during boot — the
 * route files are included on every request — sends headers before Laravel can
 * add them. `Set-Cookie` is then discarded silently, which breaks login (no
 * session cookie, no XSRF token) with a perfectly normal-looking 200 response.
 * `routes/admin.php` had exactly that: two leading blank lines.
 */
class PhpSourceHygieneTest extends TestCase
{
    public function test_php_files_start_with_an_opening_tag(): void
    {
        $offenders = [];

        foreach ($this->sourceDirectories() as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if ($contents === false || str_starts_with($contents, '<?php')) {
                    continue;
                }

                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These PHP files emit output before <?php, which sends headers early:\n  "
            . implode("\n  ", $offenders)
        );
    }

    /**
     * @return list<string>
     */
    private function sourceDirectories(): array
    {
        $root = dirname(__DIR__, 2);

        return array_map(
            static fn (string $path): string => $root.DIRECTORY_SEPARATOR.$path,
            ['app', 'bootstrap', 'config', 'database', 'routes']
        );
    }
}
