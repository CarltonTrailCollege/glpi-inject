const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');

const root = path.resolve(__dirname, '..');
const templates = ['asset_targets.html.twig', 'model_management.html.twig'];
const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'glpiinject-js-'));
const generatedFiles = [];

try {
    for (const template of templates) {
        const sourcePath = path.join(root, 'templates', template);
        const source = fs.readFileSync(sourcePath, 'utf8');
        const scripts = [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)];

        scripts.forEach((match, index) => {
            const javascript = match[1]
                .replace(/{%[\s\S]*?%}/g, '')
                .replace(/{{[\s\S]*?}}/g, 'GLPI_TWIG_VALUE');
            const outputPath = path.join(temporaryDirectory, `${template}.${index}.js`);
            fs.writeFileSync(outputPath, javascript);
            generatedFiles.push(outputPath);
        });
    }

    if (generatedFiles.length === 0) {
        throw new Error('No inline JavaScript blocks were found in plugin templates.');
    }

    const eslintPath = path.join(root, 'node_modules', 'eslint', 'bin', 'eslint.js');
    const result = spawnSync(process.execPath, [
        eslintPath,
        '--config',
        path.join(root, '.eslintrc.cjs'),
        path.join(root, 'scripts', 'lint-inline-js.cjs'),
        ...generatedFiles,
    ], {
        cwd: root,
        encoding: 'utf8',
    });
    process.stdout.write(result.stdout || '');
    process.stderr.write(result.stderr || '');
    process.exitCode = result.status ?? 1;
} finally {
    fs.rmSync(temporaryDirectory, {recursive: true, force: true});
}
