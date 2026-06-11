#!/usr/bin/env node

import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { planArchiveRawEmail } from './archive.js';
import {
  getGraphAccessToken,
  getGraphMessageMime,
  listGraphMessagesWithOptions,
  loadConfig,
} from './worker.js';

/**
 * Consts
 */

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Public methods
 */

export async function runDryRun() {
  const config = await loadConfig();
  const limit = getLimitFromArgs(process.argv);
  const archiveRoot = path.resolve(__dirname, config.archiveRoot ?? '../data/emails');
  const token = await getGraphAccessToken(config.graph);
  const messages = await listGraphMessagesWithOptions(config.graph, token, {
    pageSize: limit,
    orderDirection: 'desc',
    onlyUnread: false,
  });

  console.log(`Dry-run: latest ${limit} message(s) from ${config.graph.mailbox}/${config.graph.mailFolder ?? 'Inbox'}`);
  console.log(`Archive root: ${archiveRoot}`);
  console.log('No files will be written and no mailbox messages will be changed.');
  console.log('');

  if (messages.length === 0) {
    console.log('No messages found.');
    return;
  }

  for (const [index, message] of messages.entries()) {
    const rawEmail = await getGraphMessageMime(config.graph, token, message.id);
    const plan = await planArchiveRawEmail(rawEmail, archiveRoot);

    console.log(`${index + 1}. ${plan.subject}`);
    console.log(`   received: ${message.receivedDateTime ?? plan.date ?? ''}`);
    console.log(`   chain-id: ${plan.chainId}`);
    console.log(`   target: ${path.join(plan.folderPath, plan.emlFile)}`);
    console.log(`   text:   ${path.join(plan.folderPath, plan.txtFile)}`);
    if (plan.from !== '') {
      console.log(`   from:   ${plan.from}`);
    }
    console.log('');
  }
}

/**
 * Private Methods
 */

function getLimitFromArgs(argv) {
  const rawLimit = argv.find((value) => value.startsWith('--limit='))?.slice('--limit='.length) ?? '3';
  const limit = Number.parseInt(rawLimit, 10);

  return Number.isFinite(limit) ? Math.min(Math.max(limit, 1), 20) : 3;
}

/**
 * Page load
 */

if (process.argv[1] === __filename) {
  runDryRun().catch((error) => {
    console.error(`[${new Date().toISOString()}] Email dry-run failed:`, error);
    process.exitCode = 1;
  });
}
