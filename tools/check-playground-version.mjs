#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const packageName = "grommasdietz/kirby-blueprint-areas";

function readJson(relativePath) {
  const file = path.join(root, relativePath);

  try {
    return JSON.parse(fs.readFileSync(file, "utf8"));
  } catch (error) {
    console.error(`Unable to read ${relativePath}: ${error.message}`);
    process.exit(1);
  }
}

const composer = readJson("composer.json");
const playground = readJson("playground/composer.json");
const lock = readJson("playground/composer.lock");
const expectedVersion = composer.version;
const requirement = playground.require?.[packageName];
const lockedPackage = lock.packages?.find((entry) => entry.name === packageName);

if (typeof expectedVersion !== "string" || expectedVersion.length === 0) {
  console.error("composer.json must define the release version.");
  process.exit(1);
}

if (requirement !== "*@dev") {
  console.error(
    `playground/composer.json must require ${packageName} as *@dev; received ${String(requirement)}.`,
  );
  process.exit(1);
}

if (!lockedPackage) {
  console.error(`${packageName} is missing from playground/composer.lock.`);
  process.exit(1);
}

if (lockedPackage.dist?.type !== "path" || lockedPackage.dist?.url !== "..") {
  console.error(`${packageName} must resolve from the local ../ path repository.`);
  process.exit(1);
}

if (lockedPackage.version !== expectedVersion) {
  console.error(
    `Playground lock contains ${packageName} ${lockedPackage.version}; expected ${expectedVersion}. ` +
      `Run composer update -d playground ${packageName} --with-dependencies.`,
  );
  process.exit(1);
}

console.log(`Playground path package is synchronized at ${expectedVersion}.`);
