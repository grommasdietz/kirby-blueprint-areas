#!/usr/bin/env node
import { execFileSync } from "node:child_process";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const requested = process.argv.slice(2);
const targets = requested.length > 0 ? requested : ["index.js", "index.css", "assets"];

function collect(target, files = []) {
  const absolute = path.resolve(root, target);
  if (!fs.existsSync(absolute)) return files;

  const stat = fs.lstatSync(absolute);
  if (stat.isSymbolicLink()) return files;
  if (stat.isFile()) {
    files.push(path.relative(root, absolute));
    return files;
  }

  for (const entry of fs.readdirSync(absolute, { withFileTypes: true })) {
    collect(path.join(target, entry.name), files);
  }

  return files;
}

function targetFiles() {
  return [...new Set(targets.flatMap((target) => collect(target)))].sort();
}

function digestBuffer(content) {
  return crypto.createHash("sha256").update(content).digest("hex");
}

function snapshot(files) {
  return new Map(
    files.map((file) => {
      const absolute = path.join(root, file);
      const stat = fs.statSync(absolute);
      return [
        file,
        {
          content: fs.readFileSync(absolute),
          mode: stat.mode,
        },
      ];
    }),
  );
}

function compare(before, beforeFiles, afterFiles) {
  const changed = [];

  for (const file of new Set([...beforeFiles, ...afterFiles])) {
    if (!before.has(file)) {
      changed.push(`${file} (new)`);
      continue;
    }
    if (!afterFiles.includes(file)) {
      changed.push(`${file} (removed)`);
      continue;
    }

    const current = fs.readFileSync(path.join(root, file));
    if (digestBuffer(before.get(file).content) !== digestBuffer(current)) {
      changed.push(file);
    }
  }

  return changed;
}

function restore(before, afterFiles) {
  for (const file of afterFiles) {
    if (!before.has(file)) {
      fs.rmSync(path.join(root, file), { force: true });
    }
  }

  for (const [file, entry] of before) {
    const absolute = path.join(root, file);
    fs.mkdirSync(path.dirname(absolute), { recursive: true });
    fs.writeFileSync(absolute, entry.content);
    fs.chmodSync(absolute, entry.mode);
  }
}

const beforeFiles = targetFiles();
if (beforeFiles.length === 0) {
  console.error("No generated assets found. Adjust build:check targets in package.json.");
  process.exit(1);
}

const before = snapshot(beforeFiles);
let afterFiles = beforeFiles;
let changed = [];
let buildError = null;

try {
  execFileSync("pnpm", ["run", "build"], { cwd: root, stdio: "inherit" });
  afterFiles = targetFiles();
  changed = compare(before, beforeFiles, afterFiles);
} catch (error) {
  buildError = error;
  afterFiles = targetFiles();
} finally {
  restore(before, afterFiles);
}

if (buildError) {
  console.error("Generated asset build failed.");
  process.exit(Number.isInteger(buildError.status) ? buildError.status : 1);
}

if (changed.length > 0) {
  console.error("Generated assets are stale:");
  for (const file of changed) console.error(` - ${file}`);
  console.error("Run `pnpm run build`, commit the regenerated outputs, and rerun the check.");
  process.exit(1);
}

console.log(
  `Generated assets are current (${afterFiles.length} file${afterFiles.length === 1 ? "" : "s"}).`,
);
