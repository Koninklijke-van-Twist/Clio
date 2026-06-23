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
  buildDriveChildrenUrl,
  buildDriveUploadUrl,
  buildProjectUploadPathSegments,
  buildSharePointMetadataPayload,
  encodeSharePointPath,
  extractProjectNumber,
  findProjectFolder,
  getCorrespondenceFolderPath,
  getProjectsFolderPath,
  handleProjectSharePointUpload,
  parseProjectFolderName,
  resolveSharePointDriveId,
  uploadEmlToProjectFolder,
} from './project.js';

test('extractProjectNumber matches PRJ and 15 project numbers', () => {
  assert.equal(extractProjectNumber('Update voor PRJ123456 offerte'), 'PRJ123456');
  assert.equal(extractProjectNumber('Project PRJ123456789 document'), 'PRJ123456789');
  assert.equal(extractProjectNumber('Legacy project 151234 info'), '151234');
  assert.equal(extractProjectNumber('Geen projectnummer'), null);
  assert.equal(extractProjectNumber('PRJ12345 te kort'), null);
  assert.equal(extractProjectNumber('15123 te kort'), null);
  assert.equal(extractProjectNumber('1512345 te lang'), null);
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

test('buildProjectUploadPathSegments includes correspondence subfolder', () => {
  assert.deepEqual(
    buildProjectUploadPathSegments({
      driveId: 'drive-1',
      projectsFolder: '',
    }, {
      folderName: '153703_Demo',
      description: 'Demo',
    }, '0001-mail.eml'),
    ['', '153703_Demo', '016_CORRESPONDENCE', '0001-mail.eml'],
  );
});

test('buildSharePointMetadataPayload maps job number and description', () => {
  assert.deepEqual(buildSharePointMetadataPayload({}, '153703', 'Demo Project'), {
    'Job No.': '153703',
    'KVT Sales Quote Description': 'Demo Project',
  });
});

test('getCorrespondenceFolderPath defaults to 016_CORRESPONDENCE', () => {
  assert.equal(getCorrespondenceFolderPath({}), '016_CORRESPONDENCE');
});

test('getProjectsFolderPath uses drive root when driveId is configured', () => {
  assert.equal(getProjectsFolderPath({ driveId: 'drive-1' }), '');
  assert.equal(getProjectsFolderPath({ driveId: 'drive-1', projectsFolder: '' }), '');
  assert.equal(getProjectsFolderPath({ driveId: 'drive-1', projectsFolder: 'Projects' }), 'Projects');
  assert.equal(getProjectsFolderPath({}), 'Projects');
});

test('buildDriveChildrenUrl targets drive root when projectsFolder is empty', () => {
  assert.equal(
    buildDriveChildrenUrl('https://graph.test/v1.0', 'drive-1', ''),
    'https://graph.test/v1.0/drives/drive-1/root/children?$select=name,folder',
  );
  assert.equal(
    buildDriveChildrenUrl('https://graph.test/v1.0', 'drive-1', 'Projects'),
    'https://graph.test/v1.0/drives/drive-1/root:/Projects:/children?$select=name,folder',
  );
});

test('findProjectFolder can list project folders from Projects library root', async () => {
  const folder = await findProjectFolder({
    driveId: 'drive-projects',
    projectsFolder: '',
    graphBaseUrl: 'https://graph.test/v1.0',
  }, 'token-1', '153703', async (url) => {
    assert.equal(
      url,
      'https://graph.test/v1.0/drives/drive-projects/root/children?$select=name,folder',
    );

    return Response.json({
      value: [{ name: '153703_Demo Project', folder: {} }],
    });
  });

  assert.deepEqual(folder, {
    folderName: '153703_Demo Project',
    description: 'Demo Project',
  });
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

    if (String(url).includes('/root:/Projects/PRJ123456_Demo/016_CORRESPONDENCE/0001-test.eml:/content') && options.method === 'PUT') {
      return Response.json({ id: 'item-1' }, { status: 201 });
    }

    if (String(url).includes('/items/item-1/listItem/fields') && options.method === 'PATCH') {
      return new Response(null, { status: 200 });
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
  assert.equal(calls.some((call) => call.method === 'PATCH'), true);
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

test('resolveSharePointDriveId prefers explicit driveId', async () => {
  const driveId = await resolveSharePointDriveId({
    driveId: 'drive-explicit',
    siteHostname: 'example.test',
    sitePath: '/sites/Demo',
  }, 'token-1', async () => {
    throw new Error('fetch should not be called when driveId is set');
  });

  assert.equal(driveId, 'drive-explicit');
});

test('resolveSharePointDriveId can resolve drive via siteId', async () => {
  const driveId = await resolveSharePointDriveId({
    siteId: 'kvtnl.sharepoint.com,site-guid-here',
    graphBaseUrl: 'https://graph.test/v1.0',
  }, 'token-1', async (url) => {
    assert.equal(url, 'https://graph.test/v1.0/sites/kvtnl.sharepoint.com%2Csite-guid-here/drive');
    return Response.json({ id: 'drive-from-site-id' });
  });

  assert.equal(driveId, 'drive-from-site-id');
});

test('uploadEmlToProjectFolder uploads to correspondence folder and sets metadata', async () => {
  const calls = [];
  const path = await uploadEmlToProjectFolder({
    driveId: 'drive-1',
    projectsFolder: '',
    graphBaseUrl: 'https://graph.test/v1.0',
  }, 'token-1', {
    folderName: '151234_Legacy',
    description: 'Legacy',
  }, '151234', '0001-mail.eml', Buffer.from('eml'), async (url, options = {}) => {
    calls.push({ url, method: options.method ?? 'GET', body: options.body });

    if (options.method === 'PUT') {
      return Response.json({ id: 'item-42' }, { status: 201 });
    }

    if (options.method === 'PATCH') {
      return new Response(null, { status: 200 });
    }

    return new Response('unexpected', { status: 500 });
  });

  assert.equal(
    calls[0].url,
    'https://graph.test/v1.0/drives/drive-1/root:/151234_Legacy/016_CORRESPONDENCE/0001-mail.eml:/content',
  );
  assert.equal(
    calls[1].url,
    'https://graph.test/v1.0/drives/drive-1/items/item-42/listItem/fields',
  );
  assert.deepEqual(JSON.parse(calls[1].body), {
    'Job No.': '151234',
    'KVT Sales Quote Description': 'Legacy',
  });
  assert.equal(path, '151234_Legacy/016_CORRESPONDENCE/0001-mail.eml');
});

test('buildDriveUploadUrl supports nested projects folder', () => {
  assert.equal(
    buildDriveUploadUrl('https://graph.test/v1.0', 'drive-1', 'Projects', 'PRJ123456_Demo', '016_CORRESPONDENCE', 'mail.eml'),
    'https://graph.test/v1.0/drives/drive-1/root:/Projects/PRJ123456_Demo/016_CORRESPONDENCE/mail.eml:/content',
  );
});
