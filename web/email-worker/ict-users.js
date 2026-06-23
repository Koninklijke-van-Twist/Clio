import { execFile } from 'node:child_process';
import path from 'node:path';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const execFileAsync = promisify(execFile);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ICT_USERS_SCRIPT = path.join(__dirname, 'ict-users.php');

let cachedIctUsers = null;

export async function loadIctUsers(options = {}) {
  if (cachedIctUsers !== null && options.reload !== true) {
    return cachedIctUsers;
  }

  const phpBinary = String(options.phpBinary ?? 'php').trim() || 'php';

  try {
    const env = { ...process.env };
    if (options.useMockAuth === true) {
      env.CLIO_USE_MOCK_AUTH = '1';
    }

    const { stdout } = await execFileAsync(phpBinary, [ICT_USERS_SCRIPT], {
      cwd: __dirname,
      maxBuffer: 1024 * 1024,
      env,
    });
    const parsed = JSON.parse(String(stdout).trim());
    cachedIctUsers = Array.isArray(parsed)
      ? parsed.map((email) => String(email).trim().toLowerCase()).filter(Boolean)
      : [];
  } catch (error) {
    console.error('Kon ICT-gebruikers niet laden uit auth.php:', error);
    cachedIctUsers = [];
  }

  return cachedIctUsers;
}

export function clearIctUsersCache() {
  cachedIctUsers = null;
}
