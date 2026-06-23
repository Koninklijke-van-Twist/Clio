import assert from 'node:assert/strict';
import test from 'node:test';
import {
  appendIctDiagnostics,
  buildIctDiagnosticLines,
  isIctUser,
  sendArchiveNotifications,
} from './mail-notify.js';

test('isIctUser matches configured ICT email addresses', () => {
  const ictUsers = ['tfalken@kvt.nl', 'milanscheenloop@kvt.nl'];

  assert.equal(isIctUser('tfalken@kvt.nl', ictUsers), true);
  assert.equal(isIctUser('TFALKEN@kvt.nl', ictUsers), true);
  assert.equal(isIctUser('other@kvt.nl', ictUsers), false);
});

test('buildIctDiagnosticLines includes sharepoint and processing errors', () => {
  const lines = buildIctDiagnosticLines({
    graphMessage: { id: 'msg-1', subject: '153703' },
    archiveResult: { folderName: '153703~chain', emlFile: '0001-mail.eml' },
    projectResult: {
      handled: true,
      projectNumber: '153703',
      uploaded: false,
      reason: 'upload_failed',
      error: 'SharePoint upload failed (403): accessDenied',
    },
    processingErrors: ['Extra fout'],
  });

  assert.match(lines.join('\n'), /msg-1/);
  assert.match(lines.join('\n'), /upload_failed/);
  assert.match(lines.join('\n'), /403/);
  assert.match(lines.join('\n'), /Extra fout/);
});

test('sendArchiveNotifications appends ICT diagnostics for configured users', async () => {
  let sentBody = '';
  let contentType = '';
  const result = await sendArchiveNotifications({
    clioWebUrl: 'https://sleutels.kvt.nl/clio',
    ictUsers: ['tfalken@kvt.nl'],
    graph: {
      mailbox: 'clio@example.test',
      graphBaseUrl: 'https://graph.test/v1.0',
    },
  }, 'token-1', {
    id: 'msg-1',
    subject: '153703',
    from: { emailAddress: { address: 'tfalken@kvt.nl', name: 'Test' } },
  }, {
    folderName: '153703~chain',
    emlFile: '0001-mail.eml',
  }, {
    handled: true,
    projectNumber: '153703',
    uploaded: false,
    reason: 'folder_not_found',
  }, {}, async (_url, options) => {
    const payload = JSON.parse(options.body).message.body;
    sentBody = payload.content;
    contentType = payload.contentType;
    return new Response(null, { status: 202 });
  });

  assert.equal(result.ictDiagnostics, true);
  assert.equal(contentType, 'HTML');
  assert.match(sentBody, /ICT diagnose/);
  assert.match(sentBody, /SharePoint reden: folder_not_found/);
  assert.match(sentBody, /Bekijk op Clio/);
  assert.doesNotMatch(sentBody, /Technische details:/);
});

test('sendArchiveNotifications keeps normal body for non-ICT users', async () => {
  let sentBody = '';
  let contentType = '';
  await sendArchiveNotifications({
    clioWebUrl: 'https://sleutels.kvt.nl/clio',
    ictUsers: ['tfalken@kvt.nl'],
    graph: {
      mailbox: 'clio@example.test',
      graphBaseUrl: 'https://graph.test/v1.0',
    },
  }, 'token-1', {
    subject: '153703',
    from: { emailAddress: { address: 'other@kvt.nl', name: 'Other' } },
  }, {
    folderName: '153703~chain',
    emlFile: '0001-mail.eml',
  }, {
    handled: true,
    projectNumber: '153703',
    uploaded: false,
    reason: 'upload_failed',
    error: 'SharePoint upload failed (403): accessDenied',
  }, {}, async (_url, options) => {
    const payload = JSON.parse(options.body).message.body;
    sentBody = payload.content;
    contentType = payload.contentType;
    return new Response(null, { status: 202 });
  });

  assert.equal(contentType, 'HTML');
  assert.match(sentBody, /Bekijk op Clio/);
  assert.doesNotMatch(sentBody, /ICT diagnose/);
  assert.doesNotMatch(sentBody, /403/);
});

test('appendIctDiagnostics leaves body unchanged without lines', () => {
  assert.equal(appendIctDiagnostics('Hallo', []), 'Hallo');
});
