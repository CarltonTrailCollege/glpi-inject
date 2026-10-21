<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$setup = file_get_contents($root . '/setup.php');
if ($setup === false || preg_match("/define\\('PLUGIN_GLPIINJECT_VERSION', '([^']+)'\\)/", $setup, $matches) !== 1) {
    fwrite(STDERR, "Unable to read plugin version from setup.php.\n");
    exit(1);
}

$version = $matches[1];
$outputDirectory = $argv[1] ?? ($root . '/dist');
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0755, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDirectory}\n");
    exit(1);
}

$archivePath = rtrim($outputDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "glpiinject-{$version}.tar.gz";
$tarPath = substr($archivePath, 0, -3);
foreach ([$archivePath, $tarPath] as $path) {
    if (is_file($path) && !unlink($path)) {
        fwrite(STDERR, "Unable to replace existing archive: {$path}\n");
        exit(1);
    }
}

$files = ['setup.php', 'hook.php', 'README.md', 'CHANGELOG.md', 'LICENSE', 'glpiinject.xml', 'glpiinject.svg'];
foreach (['ajax', 'front', 'src', 'templates', 'docs/screenshots'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }
}

sort($files, SORT_STRING);

try {
    $archive = new PharData($tarPath);
    foreach ($files as $file) {
        $archive->addFile($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file), 'glpiinject/' . $file);
    }
    unset($archive);
    $tar = new PharData($tarPath);
    $tar->compress(Phar::GZ);
    unset($tar);
    if (!unlink($tarPath)) {
        throw new RuntimeException('Unable to remove intermediate tar archive.');
    }
} catch (Throwable $exception) {
    @unlink($tarPath);
    @unlink($archivePath);
    fwrite(STDERR, "Unable to create release archive: {$exception->getMessage()}\n");
    exit(1);
}

echo $archivePath . PHP_EOL;