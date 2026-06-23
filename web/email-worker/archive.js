import fs from 'node:fs/promises';
import path from 'node:path';
import { simpleParser } from 'mailparser';

/**
 * Consts
 */

export const FOLDER_SEPARATOR = '~';

/**
 * Public methods
 */

export function normalizeMessageId(value) {
  const id = String(value ?? '').trim().match(/<([^>]+)>/)?.[1] ?? String(value ?? '').trim();
  return id.toLowerCase().replace(/^<|>$/g, '').trim();
}

export function extractMessageIds(value) {
  return String(value ?? '')
    .match(/<[^>]+>|[^\s,]+@[^\s,]+/g)
    ?.map(normalizeMessageId)
    .filter(Boolean) ?? [];
}

export function determineChainId(headers) {
  const references = extractMessageIds(headers.references);
  if (references.length > 0) {
    return references[0];
  }

  const inReplyTo = extractMessageIds(headers.inReplyTo);
  if (inReplyTo.length > 0) {
    return inReplyTo[0];
  }

  const messageId = normalizeMessageId(headers.messageId);
  return messageId !== '' ? messageId : `missing-${Date.now()}`;
}

export function sanitizeChainId(chainId) {
  const safe = String(chainId ?? '')
    .toLowerCase()
    .replace(/[<>]/g, '')
    .replaceAll(FOLDER_SEPARATOR, '-')
    .replace(/[^a-z0-9@._+\-=]/g, '_')
    .replace(/^[._-]+|[._-]+$/g, '');

  return safe !== '' ? safe : 'unknown-chain';
}

export function sanitizeSubject(subject) {
  const safe = String(subject ?? '')
    .replaceAll(FOLDER_SEPARATOR, '-')
    .replace(/\s+/g, ' ')
    .replace(/[<>:"/\\|?*\u0000-\u001f]/g, '')
    .trim()
    .replace(/^\.+|\.+$/g, '');

  return (safe !== '' ? safe : 'Geen onderwerp').slice(0, 120);
}

export function sanitizeFilenamePart(value) {
  const safe = sanitizeSubject(value)
    .toLowerCase()
    .replace(/[^a-z0-9._ -]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '');

  return (safe !== '' ? safe : 'email').slice(0, 80);
}

export async function findThreadFolderByChainId(archiveRoot, chainId) {
  await fs.mkdir(archiveRoot, { recursive: true });
  const safeChainId = sanitizeChainId(chainId);
  const entries = await fs.readdir(archiveRoot, { withFileTypes: true });

  return entries.find((entry) => {
    if (!entry.isDirectory()) {
      return false;
    }

    const separatorIndex = entry.name.lastIndexOf(FOLDER_SEPARATOR);
    if (separatorIndex === -1) {
      return false;
    }

    return sanitizeChainId(entry.name.slice(separatorIndex + 1)) === safeChainId;
  })?.name ?? null;
}

export async function archiveRawEmail(rawEmail, archiveRoot) {
  const parsed = await simpleParser(rawEmail);
  const plan = await planArchiveRawEmail(rawEmail, archiveRoot, parsed);
  const folderName = plan.folderName;
  const folderPath = plan.folderPath;
  await fs.mkdir(folderPath, { recursive: true });

  const meta = await loadMeta(folderPath);
  const sequence = plan.sequence;
  const emlFile = plan.emlFile;
  const txtFile = plan.txtFile;
  const htmlFile = plan.htmlFile;

  await fs.writeFile(path.join(folderPath, emlFile), rawEmail);
  await fs.writeFile(path.join(folderPath, txtFile), parsed.text ?? '', 'utf8');
  await fs.writeFile(path.join(folderPath, htmlFile), normalizeHtmlBody(parsed.html), 'utf8');

  const contacts = collectContacts(parsed);
  mergeContacts(meta, contacts);
  meta.chain_id = plan.chainId;
  meta.subject = meta.subject ?? plan.subject;
  meta.updated_at = new Date().toISOString();
  meta.emails.push({
    sequence,
    subject: plan.subject,
    message_id: normalizeMessageId(parsed.messageId),
    date: parsed.date instanceof Date ? parsed.date.toISOString() : '',
    from: formatFirstAddress(parsed.from),
    to: formatAddressList(parsed.to),
    cc: formatAddressList(parsed.cc),
    bcc: formatAddressList(parsed.bcc),
    eml_file: emlFile,
    text_file: txtFile,
    html_file: htmlFile,
  });

  await saveMeta(folderPath, meta);

  return {
    folderName,
    emlFile,
    txtFile,
    htmlFile,
    chainId: plan.chainId,
    subject: plan.subject,
    from: plan.from,
  };
}

export async function planArchiveRawEmail(rawEmail, archiveRoot, parsedEmail = null) {
  const parsed = parsedEmail ?? await simpleParser(rawEmail);
  const headers = {
    references: parsed.references,
    inReplyTo: parsed.inReplyTo,
    messageId: parsed.messageId,
  };
  const chainId = sanitizeChainId(determineChainId(headers));
  const subject = sanitizeSubject(parsed.subject);
  const existingFolder = await findThreadFolderByChainId(archiveRoot, chainId);
  const folderName = existingFolder ?? `${subject}${FOLDER_SEPARATOR}${chainId}`;
  const folderPath = path.join(archiveRoot, folderName);
  const sequence = existingFolder === null ? 1 : await getNextSequence(folderPath);
  const filenamePart = sanitizeFilenamePart(subject);
  const baseName = `${String(sequence).padStart(4, '0')}-${filenamePart}`;

  return {
    chainId,
    subject,
    folderName,
    folderPath,
    sequence,
    emlFile: `${baseName}.eml`,
    txtFile: `${baseName}.txt`,
    htmlFile: `${baseName}.html`,
    from: formatFirstAddress(parsed.from),
    to: formatAddressList(parsed.to),
    date: parsed.date instanceof Date ? parsed.date.toISOString() : '',
  };
}

/**
 * Private Methods
 */

async function loadMeta(folderPath) {
  try {
    const content = await fs.readFile(path.join(folderPath, 'meta.json'), 'utf8');
    const meta = JSON.parse(content);
    return {
      ...meta,
      contacts: Array.isArray(meta.contacts) ? meta.contacts : [],
      emails: Array.isArray(meta.emails) ? meta.emails : [],
    };
  } catch (error) {
    if (error.code !== 'ENOENT') {
      throw error;
    }

    return {
      contacts: [],
      emails: [],
    };
  }
}

async function saveMeta(folderPath, meta) {
  await fs.writeFile(path.join(folderPath, 'meta.json'), `${JSON.stringify(meta, null, 2)}\n`, 'utf8');
}

async function getNextSequence(folderPath) {
  const entries = await fs.readdir(folderPath);
  const numbers = entries
    .map((entry) => entry.match(/^(\d{4})-/)?.[1])
    .filter(Boolean)
    .map((value) => Number.parseInt(value, 10));

  return numbers.length > 0 ? Math.max(...numbers) + 1 : 1;
}

function collectContacts(parsed) {
  return [
    ...addressesFrom(parsed.from),
    ...addressesFrom(parsed.to),
    ...addressesFrom(parsed.cc),
    ...addressesFrom(parsed.bcc),
    ...addressesFrom(parsed.replyTo),
  ];
}

function addressesFrom(addressObject) {
  return addressObject?.value?.map((item) => ({
    email: String(item.address ?? '').toLowerCase(),
    name: String(item.name ?? '').trim(),
  })).filter((item) => item.email !== '') ?? [];
}

function mergeContacts(meta, contacts) {
  const indexed = new Map(meta.contacts.map((contact) => [contact.email, {
    email: contact.email,
    name: contact.name ?? '',
    names: Array.isArray(contact.names) ? contact.names : [],
  }]));

  for (const contact of contacts) {
    const current = indexed.get(contact.email) ?? {
      email: contact.email,
      name: '',
      names: [],
    };

    if (contact.name !== '' && !current.names.includes(contact.name)) {
      current.names.push(contact.name);
    }
    if (current.name === '' && contact.name !== '') {
      current.name = contact.name;
    }

    indexed.set(contact.email, current);
  }

  meta.contacts = [...indexed.values()].sort((left, right) => left.email.localeCompare(right.email));
}

function formatFirstAddress(addressObject) {
  return formatAddressList(addressObject)[0] ?? '';
}

function formatAddressList(addressObject) {
  return addressObject?.value?.map((item) => {
    const email = String(item.address ?? '').toLowerCase();
    const name = String(item.name ?? '').trim();
    return name !== '' ? `${name} <${email}>` : email;
  }).filter(Boolean) ?? [];
}

function normalizeHtmlBody(html) {
  if (typeof html === 'string') {
    return html;
  }

  if (html === false || html === null || html === undefined) {
    return '';
  }

  return String(html);
}
