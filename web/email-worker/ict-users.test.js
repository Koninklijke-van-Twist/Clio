import assert from 'node:assert/strict';
import test from 'node:test';
import { clearIctUsersCache, loadIctUsers } from './ict-users.js';

test.afterEach(() => {
  clearIctUsersCache();
});

test('loadIctUsers reads normalized emails from mock-auth.php', async () => {
  const users = await loadIctUsers({ reload: true, useMockAuth: true });
  assert.ok(Array.isArray(users));
  assert.ok(users.includes('tfalken@kvt.nl'));
  assert.ok(users.every((email) => email === email.toLowerCase()));
});
