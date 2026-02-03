import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const DEFAULT_EMAIL = "playwright@kirby-blueprint-areas.test";

function resolveRoot() {
  return path.resolve(__dirname, "..", "..");
}

export default async function globalTeardown() {
  const root = resolveRoot();
  const env = { ...process.env };
  const email = env.KIRBY_USER_EMAIL ?? DEFAULT_EMAIL;

  execSync(
    `php ${path.join(root, "tools", "create-test-user.php")} --delete --email="${email}"`,
    { cwd: root, stdio: "inherit", env },
  );
}
