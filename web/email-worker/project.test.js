import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildArchiveOnlyBody,
  buildProjectUploadFailedBody,
  buildProjectUploadSuccessBody,
  buildReplySubject,
  getMessageSender,
} from './mail-notify.js';
import {
  encodeSharePointPath,
  extractProjectNumber,
  findProjectFolder,
  handleProjectSharePointUpload,
  parseProjectFolderName,
  uploadEmlToProjectFolder,
} from './project.js';

test('extractProjectNumber matches PRJ and 15 project numbers', () => {
  assert.equal(extractProjectNumber('Update voor PRJ123456 offerte'), 'PRJ123456');
  assert.equal(extractProjectNumber('Project PRJ123456789 document'), 'PRJ123456789');
  assert.equal(extractProjectNumber('Legacy project 15123456 info'), '15123456');
  assert.equal(extractProjectNumber('Geen projectnummer'), null);
  assert.equal(extractProjectNumber('PRJ12345 te kort'), null);
  assert.equal(extractProjectNumber('1512345 te kort'), null);
});

test('parseProjectFolderName extracts description from folder name', () => {
  assert.deepEqual(parseProjectFolderName('PRJ123456_KVT Demo', 'PRJ123456'), {
    folderName: 'PRJ123456_KVT Demo',
    description: 'KVT Demo',
  });
  assert.equal(parseProjectFolderName('PRJ123456', 'PRJ123456'), null);
});

test('encodeSharePointPath encodes each segment', () => {
  assert.equal(encodeSharePointPath('Projects', 'PRJ123456_Demo', '0001-test.eml'), 'Projects/PRJ123456_Demo/0001-test.eml');
});

test('findProjectFolder returns first matching project folder', async () => {
  const folder = await findProjectFolder({
    driveId: 'drive-1',
    projectsFolder: 'Projects',
    graphBaseUrl: 'https://graph.test/v1.0',
  }, 'token-1', 'PRJ123456', async (url) => {
    assert.equal(
      url,
      'https://graph.test/v1.0/drives/drive-1/root:/Projects:/children?$select=name,folder',
    );

    return Response.json({
      value: [
        { name: 'PRJ999999_Other', folder: {} },
        { name: 'PRJ123456_Demo Project', folder: {} },
        { name: 'PRJ123456_Second', folder: {} },
        { name: 'readme.txt', file: {} },
      ],
    });
  });

  assert.deepEqual(folder, {
    folderName: 'PRJ123456_Demo Project',
    description: 'Demo Project',
  });
});

test('handleProjectSharePointUpload uploads only eml when project folder exists', async () => {
  const calls = [];
  const eml = Buffer.from('raw eml content');

  const result = await handleProjectSharePointUpload({
    sharepoint: {
      driveId: 'drive-1',
      projectsFolder: 'Projects',
      graphBaseUrl: 'https://graph.test/v1.0',
    },
  }, 'token-1', 'Document PRJ123456 upload', '0001-test.eml', eml, async (url, options = {}) => {
    calls.push({ url, method: options.method ?? 'GET' });

    if (String(url).includes('/children?')) {
      return Response.json({
        value: [{ name: 'PRJ123456_Demo', folder: {} }],
      });
    }

    if (String(url).includes('/root:/Projects/PRJ123456_Demo/0001-test.eml:/content') && options.method === 'PUT') {
      return new Response(null, { status: 201 });
    }

    return new Response('unexpected', { status: 500 });
  });

  assert.deepEqual(result, {
    handled: true,
    projectNumber: 'PRJ123456',
    uploaded: true,
    projectFolder: {
      folderName: 'PRJ123456_Demo',
      description: 'Demo',
    },
  });
  assert.equal(calls.some((call) => call.method === 'PUT'), true);
});

test('handleProjectSharePointUpload reports missing folder without upload', async () => {
  const result = await handleProjectSharePointUpload({
    sharepoint: {
      driveId: 'drive-1',
      projectsFolder: 'Projects',
      graphBaseUrl: 'https://graph.test/v1.0',
    },
  }, 'token-1', 'Document PRJ123456 upload', '0001-test.eml', Buffer.from('raw'), async (url) => {
    if (String(url).includes('/children?')) {
      return Response.json({ value: [] });
    }

    return new Response('unexpected', { status: 500 });
  });

  assert.deepEqual(result, {
    handled: true,
    projectNumber: 'PRJ123456',
    uploaded: false,
    reason: 'folder_not_found',
  });
});

test('notification bodies cover archive and project outcomes', () => {
  assert.match(buildArchiveOnlyBody(), /gearchiveerd in Clio/);
  assert.match(buildProjectUploadFailedBody('PRJ123456'), /PRJ123456/);
  assert.match(buildProjectUploadSuccessBody('PRJ123456', 'Demo'), /PRJ123456 \(Demo\)/);
  assert.equal(buildReplySubject('Offerte'), 'Re: Offerte');
  assert.equal(buildReplySubject('Re: Offerte'), 'Re: Offerte');
});

test('getMessageSender reads Graph from address', () => {
  assert.deepEqual(getMessageSender({
    from: {
      emailAddress: {
        address: 'sanne@example.test',
        name: 'Sanne',
      },
    },
  }), {
    email: 'sanne@example.test',
    name: 'Sanne',
  });
});

test('uploadEmlToProjectFolder puts file in project folder', async () => {
  let uploadUrl = '';
  await uploadEmlToProjectFolder({
    driveId: 'drive-1',
    projectsFolder: 'Projects',
    graphBaseUrl: 'https://graph.test/v1.0',
  }, 'token-1', {
    folderName: '15123456_Legacy',
    description: 'Legacy',
  }, '0001-mail.eml', Buffer.from('eml'), async (url, options) => {
    uploadUrl = url;
    assert.equal(options.method, 'PUT');
    return new Response(null, { status: 201 });
  });

  assert.equal(
    uploadUrl,
    'https://graph.test/v1.0/drives/drive-1/root:/Projects/15123456_Legacy/0001-mail.eml:/content',
  );
});
