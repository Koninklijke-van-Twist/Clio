import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import {
  archiveRawEmail,
  determineChainId,
  findThreadFolderByChainId,
  planArchiveRawEmail,
  sanitizeChainId,
  sanitizeSubject,
} from './archive.js';

test('determineChainId prefers first References header message id', () => {
  const chainId = determineChainId({
    references: '<root@example.test> <reply@example.test>',
    inReplyTo: '<ignored@example.test>',
    messageId: '<current@example.test>',
  });

  assert.equal(chainId, 'root@example.test');
});

test('sanitizeSubject replaces tilde so it remains a folder separator', () => {
  assert.equal(sanitizeSubject(' Vraag ~ offerte '), 'Vraag - offerte');
  assert.equal(sanitizeChainId('<Root/ID@example.test>'), 'root_id@example.test');
});

test('archiveRawEmail appends to existing folder matched by chain id', async () => {
  const archiveRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'clio-mail-'));

  try {
    const rawFirst = [
      'Message-ID: <root@example.test>',
      'From: Sanne Jansen <sanne@example.test>',
      'To: Clio <clio@example.test>',
      'Subject: Vraag ~ offerte',
      'Date: Thu, 11 Jun 2026 10:00:00 +0200',
      '',
      'Eerste bericht',
    ].join('\r\n');
    const rawSecond = [
      'Message-ID: <reply@example.test>',
      'References: <root@example.test>',
      'From: Clio <clio@example.test>',
      'To: Sanne Jansen <sanne@example.test>',
      'Subject: Re: Vraag ~ offerte',
      'Date: Thu, 11 Jun 2026 10:05:00 +0200',
      '',
      'Tweede bericht',
    ].join('\r\n');

    const first = await archiveRawEmail(Buffer.from(rawFirst), archiveRoot);
    const second = await archiveRawEmail(Buffer.from(rawSecond), archiveRoot);
    const matched = await findThreadFolderByChainId(archiveRoot, 'root@example.test');
    const meta = JSON.parse(await fs.readFile(path.join(archiveRoot, first.folderName, 'meta.json'), 'utf8'));

    assert.equal(first.folderName, 'Vraag - offerte~root@example.test');
    assert.equal(second.folderName, first.folderName);
    assert.equal(matched, first.folderName);
    assert.equal(meta.emails.length, 2);
    assert.equal(meta.emails[0].html_file, '0001-vraag-offerte.html');
    assert.equal(meta.contacts.find((contact) => contact.email === 'sanne@example.test').name, 'Sanne Jansen');
  } finally {
    await fs.rm(archiveRoot, { recursive: true, force: true });
  }
});

test('planArchiveRawEmail calculates target without writing files', async () => {
  const archiveRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'clio-mail-plan-'));

  try {
    const raw = [
      'Message-ID: <root@example.test>',
      'From: Sanne Jansen <sanne@example.test>',
      'To: Clio <clio@example.test>',
      'Subject: Plan test',
      '',
      'Body',
    ].join('\r\n');

    const plan = await planArchiveRawEmail(Buffer.from(raw), archiveRoot);
    const entries = await fs.readdir(archiveRoot);

    assert.equal(plan.folderName, 'Plan test~root@example.test');
    assert.equal(plan.emlFile, '0001-plan-test.eml');
    assert.deepEqual(entries, []);
  } finally {
    await fs.rm(archiveRoot, { recursive: true, force: true });
  }
});

test('archiveRawEmail writes html body next to eml and text', async () => {
  const archiveRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'clio-mail-html-'));

  try {
    const raw = [
      'Message-ID: <html@example.test>',
      'From: Sanne Jansen <sanne@example.test>',
      'To: Clio <clio@example.test>',
      'Subject: HTML test',
      'Content-Type: text/html; charset=utf-8',
      '',
      '<p>HTML body</p>',
    ].join('\r\n');

    const result = await archiveRawEmail(Buffer.from(raw), archiveRoot);
    const htmlPath = path.join(archiveRoot, result.folderName, result.htmlFile);
    const metaPath = path.join(archiveRoot, result.folderName, 'meta.json');
    const meta = JSON.parse(await fs.readFile(metaPath, 'utf8'));

    assert.equal(result.htmlFile, '0001-html-test.html');
    assert.equal(await fs.readFile(htmlPath, 'utf8'), '<p>HTML body</p>');
    assert.equal(meta.emails[0].html_file, '0001-html-test.html');
  } finally {
    await fs.rm(archiveRoot, { recursive: true, force: true });
  }
});
