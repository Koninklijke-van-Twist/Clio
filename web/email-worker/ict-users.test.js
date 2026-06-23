import assert from 'node:assert/strict';
import test from 'node:test';
import { clearIctUsersCache, loadIctUsers } from './ict-users.js';

test.afterEach(() => {
  clearIctUsersCache();
});

test('loadIctUsers reads normalized emails from auth.php', async () => {
  const users = await loadIctUsers({ reload: true });
  assert.ok(Array.isArray(users));
  assert.ok(users.includes('tfalken@kvt.nl'));
  assert.ok(users.every((email) => email === email.toLowerCase()));
});
