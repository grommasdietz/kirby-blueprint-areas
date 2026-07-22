import { execFileSync } from "node:child_process";
import { copyFileSync, mkdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import {
  restorePlaygroundContent,
  snapshotPlaygroundContent,
} from "./playground-state.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default async function globalSetup() {
  const root = path.resolve(__dirname, "..", "..");

  // A previous interrupted or pre-v4.9 run may have left Kirby-serialized
  // empty fields with trailing spaces in the tracked site fixtures. Restore
  // the committed canonical fixtures before taking this run's snapshot so the
  // test lifecycle is self-healing and the final Git whitespace check is stable.
  restorePlaygroundContent(root);

  const fixtureRoot = path.join(__dirname, "fixtures");
  const contentRoot = path.join(root, "playground", "content");
  mkdirSync(contentRoot, { recursive: true });

  for (const language of ["de", "en"]) {
    copyFileSync(
      path.join(fixtureRoot, `site.${language}.txt`),
      path.join(contentRoot, `site.${language}.txt`),
    );
  }

  snapshotPlaygroundContent(root);

  try {
    execFileSync("php", [path.join(root, "tools", "reset-playground.php")], {
      cwd: root,
      stdio: "inherit",
      env: { ...process.env },
    });

    for (const [email, role, password] of [
      [
        process.env.KIRBY_USER_EMAIL ?? "admin@kirby-blueprint-areas.test",
        "admin",
        process.env.KIRBY_USER_PASSWORD ?? "playwright",
      ],
      ["editor@kirby-blueprint-areas.test", "editor", "playwright"],
      ["readonly@kirby-blueprint-areas.test", "readonly", "playwright"],
    ]) {
      execFileSync(
        "php",
        [
          path.join(root, "tools", "create-test-user.php"),
          `--email=${email}`,
          `--password=${password}`,
          `--role=${role}`,
        ],
        {
          cwd: root,
          stdio: "inherit",
          env: { ...process.env },
        },
      );
    }
  } catch (error) {
    restorePlaygroundContent(root);
    throw error;
  }
}
