import fs from 'node:fs';
import path from 'node:path';

const version = process.argv[2];
if (!version) {
  console.error('Usage: node tools/set-version.mjs <version>');
  process.exit(1);
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function writeJson(file, data) {
  fs.writeFileSync(file, JSON.stringify(data, null, 2) + '\n', 'utf8');
}

const root = path.resolve(process.cwd());
const composerPath = path.join(root, 'composer.json');
const packagePath = path.join(root, 'package.json');

if (fs.existsSync(composerPath)) {
  const composer = readJson(composerPath);
  composer.version = version;
  writeJson(composerPath, composer);
}

if (fs.existsSync(packagePath)) {
  const pkg = readJson(packagePath);
  pkg.version = version;
  writeJson(packagePath, pkg);
}
