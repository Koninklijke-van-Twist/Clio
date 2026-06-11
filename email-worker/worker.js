#!/usr/bin/env node

import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ImapFlow } from 'imapflow';
import { archiveRawEmail } from './archive.js';

/**
 * Consts
 */

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const CONFIG_PATH = path.join(__dirname, 'config.json');

/**
 * Public methods
 */

export async function loadConfig(configPath = CONFIG_PATH) {
  const content = await fs.readFile(configPath, 'utf8');
  const config = JSON.parse(content);

  if (!config.imap?.host || !config.imap?.auth?.user || !config.imap?.auth?.pass) {
    throw new Error('IMAP configuratie ontbreekt in email-worker/config.json.');
  }

  return config;
}

export async function processMailbox(config) {
  const archiveRoot = path.resolve(__dirname, config.archiveRoot ?? '../web/data/emails');
  const client = new ImapFlow({
    host: config.imap.host,
    port: config.imap.port ?? 993,
    secure: config.imap.secure ?? true,
    auth: config.imap.auth,
    logger: false,
  });

  await client.connect();

  try {
    const mailbox = config.imap.mailbox ?? 'INBOX';
    const lock = await client.getMailboxLock(mailbox);

    try {
      const uids = await client.search({ seen: false }, { uid: true });
      for (const uid of uids) {
        const message = await client.fetchOne(uid, { source: true }, { uid: true });
        if (!message?.source) {
          continue;
        }

        const result = await archiveRawEmail(message.source, archiveRoot);
        console.log(`Archived UID ${uid} in ${result.folderName}/${result.emlFile}`);
        await client.messageDelete(uid, { uid: true });
      }
    } finally {
      lock.release();
    }
  } finally {
    await client.logout();
  }
}

export async function runWorker() {
  const config = await loadConfig();
  const once = process.argv.includes('--once');

  async function tick() {
    try {
      await processMailbox(config);
    } catch (error) {
      console.error(`[${new Date().toISOString()}] Email worker failed:`, error);
      process.exitCode = 1;
    }
  }

  await tick();

  if (once) {
    return;
  }

  const intervalMinutes = Number(config.pollIntervalMinutes ?? 10);
  const intervalMs = Math.max(1, intervalMinutes) * 60 * 1000;
  setInterval(tick, intervalMs);
}

/**
 * Page load
 */

if (process.argv[1] === __filename) {
  runWorker();
}
