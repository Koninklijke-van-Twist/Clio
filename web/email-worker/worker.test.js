import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import {
  assertGraphConfig,
  buildMessagesUrl,
  getGraphAccessToken,
  processMailbox,
} from './worker.js';

test('assertGraphConfig requires app-only Graph settings', () => {
  assert.throws(() => assertGraphConfig({ graph: { tenantId: 'tenant' } }), /clientId/);
});

test('getGraphAccessToken uses client credentials token endpoint', async () => {
  const requests = [];
  const token = await getGraphAccessToken({
    tenantId: 'tenant-id',
    clientId: 'client-id',
    clientSecret: 'secret',
    authorityHost: 'https://login.test',
  }, async (url, options) => {
    requests.push({ url, options });
    return Response.json({ access_token: 'token-1' });
  });

  assert.equal(token, 'token-1');
  assert.equal(requests[0].url, 'https://login.test/tenant-id/oauth2/v2.0/token');
  assert.equal(new URLSearchParams(requests[0].options.body).get('grant_type'), 'client_credentials');
});

test('buildMessagesUrl targets configured mailbox and folder', () => {
  const url = buildMessagesUrl({
    graphBaseUrl: 'https://graph.test/v1.0',
    mailbox: 'clio@example.test',
    mailFolder: 'Inbox/Clio',
    pageSize: 10,
    onlyUnread: true,
  });

  assert.equal(
    url,
    'https://graph.test/v1.0/users/clio%40example.test/mailFolders/Inbox/childFolders/Clio/messages?%24top=10&%24select=id%2Csubject%2CreceivedDateTime%2CisRead&%24orderby=receivedDateTime+asc&%24filter=isRead+eq+false',
  );
});

test('processMailbox archives MIME from Graph and deletes the message', async () => {
  const archiveRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'clio-graph-mail-'));
  const calls = [];
  const rawEmail = [
    'Message-ID: <root@example.test>',
    'From: Sanne Jansen <sanne@example.test>',
    'To: Clio <clio@example.test>',
    'Subject: Graph bericht',
    'Date: Thu, 11 Jun 2026 10:00:00 +0200',
    '',
    'Graph body',
  ].join('\r\n');

  try {
    await processMailbox({
      archiveRoot,
      graph: {
        tenantId: 'tenant-id',
        clientId: 'client-id',
        clientSecret: 'secret',
        mailbox: 'clio@example.test',
        mailFolder: 'Inbox',
        graphBaseUrl: 'https://graph.test/v1.0',
        authorityHost: 'https://login.test',
      },
    }, {
      fetchImpl: async (url, options = {}) => {
        calls.push({ url, method: options.method ?? 'GET' });

        if (String(url).includes('/oauth2/v2.0/token')) {
          return Response.json({ access_token: 'token-1' });
        }

        if (String(url).endsWith('/messages?%24top=25&%24select=id%2Csubject%2CreceivedDateTime%2CisRead&%24orderby=receivedDateTime+asc')) {
          return Response.json({ value: [{ id: 'message-1' }] });
        }

        if (String(url).endsWith('/messages/message-1/$value')) {
          return new Response(rawEmail);
        }

        if (String(url).endsWith('/messages/message-1') && options.method === 'DELETE') {
          return new Response(null, { status: 204 });
        }

        return new Response('unexpected request', { status: 500 });
      },
    });

    const metaPath = path.join(archiveRoot, 'Graph bericht~root@example.test', 'meta.json');
    const meta = JSON.parse(await fs.readFile(metaPath, 'utf8'));

    assert.equal(meta.emails.length, 1);
    assert.equal(meta.emails[0].subject, 'Graph bericht');
    assert.deepEqual(calls.map((call) => call.method), ['POST', 'GET', 'GET', 'DELETE']);
  } finally {
    await fs.rm(archiveRoot, { recursive: true, force: true });
  }
});
