const fs = require('node:fs');
const path = require('node:path');
const {loadConfig} = require('./config.cjs');

const siteDir = path.resolve(__dirname, '..');

/**
 * Connect canonical branch trees to Docusaurus without copying Markdown.
 * Only the owned, disposable .generated directory is replaced. Junctions on
 * Windows and directory symlinks on Linux preserve the package as source of truth.
 * @param {object} config Validated registry.
 * @param {object} sources Version-to-checkout overrides for local development.
 * @param {string} [root] Site root; tests use an isolated temporary directory.
 * @returns {void}
 */
function prepare(config, sources = {}, root = siteDir) {
    const generated = path.join(root, '.generated');
    const connections = [];
    for (const line of config.lines) {
        const checkout = path.resolve(root, sources[line.version] || `.sources/${line.version}`);
        const docs = path.join(checkout, 'docs');
        if (docs.startsWith(`${generated}${path.sep}`)) {
            throw new Error('Canonical documentation must not live in .generated.');
        }
        const metadata = JSON.parse(fs.readFileSync(path.join(docs, 'docs.json'), 'utf8'));
        if (metadata.package !== 'seiger/scommerce') {
            throw new Error(`Unexpected documentation package in ${docs}`);
        }
        const primary = path.join(docs, config.defaultLocale);
        if (!fs.existsSync(path.join(primary, 'README.md'))) {
            throw new Error(`Missing ${line.version}/${config.defaultLocale}/README.md`);
        }
        connections.push([primary, path.join(generated, 'content', line.id)]);
        for (const locale of Object.keys(config.locales)) {
            if (locale === config.defaultLocale) continue;
            const translated = path.join(docs, locale);
            if (fs.existsSync(translated)) {
                connections.push([translated, path.join(generated, 'i18n', locale,
                    `docusaurus-plugin-content-docs-${line.id}`, 'current')]);
            }
        }
    }
    // Node removes junctions themselves, never the linked canonical directories.
    fs.rmSync(generated, {recursive: true, force: true});
    for (const [source, target] of connections) {
        fs.mkdirSync(path.dirname(target), {recursive: true});
        fs.symlinkSync(source, target, process.platform === 'win32' ? 'junction' : 'dir');
    }
    const translations = path.join(root, 'i18n');
    if (fs.existsSync(translations)) {
        fs.cpSync(translations, path.join(generated, 'i18n'), {recursive: true});
    }
    fs.writeFileSync(path.join(generated, 'registry.json'), JSON.stringify(config, null, 2));
}

if (require.main === module) {
    const sources = {};
    for (const argument of process.argv.slice(2)) {
        const match = argument.match(/^([^=]+)=(.+)$/);
        if (!match) throw new Error('Use version=checkout overrides, e.g. 1.x=../..');
        sources[match[1]] = match[2];
    }
    prepare(loadConfig(), sources);
    process.stdout.write('Connected canonical branch documentation.\n');
}

module.exports = {prepare};
