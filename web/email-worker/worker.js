#!/usr/bin/env node

import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { archiveRawEmail } from './archive.js';
import { sendArchiveNotifications } from './mail-notify.js';
import { loadIctUsers } from './ict-users.js';
import { handleProjectSharePointUpload, extractProjectNumber } from './project.js';

/**
 * Consts
 */

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const CONFIG_PATH = path.join(__dirname, 'config.json');
const DEFAULT_AUTHORITY_HOST = 'https://login.microsoftonline.com';
const DEFAULT_GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';

/**
 * Public methods
 */

export async function loadConfig(configPath = CONFIG_PATH) {
  const content = await fs.readFile(configPath, 'utf8');
  const config = JSON.parse(content);
  assertGraphConfig(config);

  return config;
}

export function assertGraphConfig(config) {
  const graph = config.graph ?? {};
  const missing = ['tenantId', 'clientId', 'clientSecret', 'mailbox']
    .filter((key) => String(graph[key] ?? '').trim() === '');

  if (missing.length > 0) {
    throw new Error(`Graph configuratie mist: ${missing.join(', ')} in web/email-worker/config.json.`);
  }
}

export async function getGraphAccessToken(graphConfig, fetchImpl = fetch) {
  const authorityHost = graphConfig.authorityHost ?? DEFAULT_AUTHORITY_HOST;
  const tokenUrl = `${authorityHost.replace(/\/+$/, '')}/${encodeURIComponent(graphConfig.tenantId)}/oauth2/v2.0/token`;
  const body = new URLSearchParams({
    client_id: graphConfig.clientId,
    client_secret: graphConfig.clientSecret,
    scope: 'https://graph.microsoft.com/.default',
    grant_type: 'client_credentials',
  });

  const response = await fetchImpl(tokenUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Accept: 'application/json',
    },
    body,
  });

  const json = await response.json().catch(() => ({}));
  if (!response.ok || typeof json.access_token !== 'string') {
    throw new Error(`Graph token request failed (${response.status}): ${JSON.stringify(json)}`);
  }

  return json.access_token;
}

export function buildMessagesUrl(graphConfig) {
  return buildGraphMessagesUrl(graphConfig, {
    pageSize: graphConfig.pageSize ?? 25,
    orderDirection: 'asc',
    onlyUnread: graphConfig.onlyUnread === true,
  });
}

export function buildGraphMessagesUrl(graphConfig, options = {}) {
  const graphBaseUrl = String(graphConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const mailbox = encodeURIComponent(graphConfig.mailbox);
  const folder = encodeMailFolderPath(graphConfig.mailFolder ?? 'Inbox');
  const orderDirection = options.orderDirection === 'desc' ? 'desc' : 'asc';
  const params = new URLSearchParams({
    '$top': String(Math.min(Math.max(Number(options.pageSize ?? 25), 1), 1000)),
    '$select': 'id,subject,receivedDateTime,isRead,from',
    '$orderby': `receivedDateTime ${orderDirection}`,
  });

  if (options.onlyUnread === true) {
    params.set('$filter', 'isRead eq false');
  }

  return `${graphBaseUrl}/users/${mailbox}/mailFolders/${folder}/messages?${params.toString()}`;
}

export async function processMailbox(config, options = {}) {
  const fetchImpl = options.fetchImpl ?? fetch;
  const archiveRoot = path.resolve(__dirname, config.archiveRoot ?? '../data/emails');
  const graph = config.graph;
  const token = await getGraphAccessToken(graph, fetchImpl);
  const messages = await listGraphMessages(graph, token, fetchImpl);
  const ictUsers = Array.isArray(config.ictUsers)
    ? config.ictUsers
    : await loadIctUsers();

  for (const message of messages) {
    const senderEmail = getMessageSenderEmail(message);
    if (!isSenderDomainAllowed(senderEmail, graph.allowedSenderDomains ?? [])) {
      console.log(`Deleting blocked Graph message ${message.id} from ${senderEmail || 'unknown sender'}`);
      await deleteGraphMessage(graph, token, message.id, fetchImpl);
      continue;
    }

    const rawEmail = await getGraphMessageMime(graph, token, message.id, fetchImpl);
    const result = await archiveRawEmail(rawEmail, archiveRoot);
    console.log(`Archived Graph message ${message.id} in ${result.folderName}/${result.emlFile}`);

    let projectResult = { handled: false };
    const subjectForProject = String(message.subject ?? result.subject ?? '');
    const detectedProjectNumber = extractProjectNumber(subjectForProject);

    if (detectedProjectNumber !== null) {
      console.log(`Detected project number ${detectedProjectNumber} in subject "${subjectForProject}"`);
    }

    let processingErrors = [];

    try {
      projectResult = await handleProjectSharePointUpload(
        config,
        token,
        subjectForProject,
        result.emlFile,
        rawEmail,
        fetchImpl,
      );

      if (projectResult.handled === true) {
        if (projectResult.uploaded === true) {
          console.log(`Uploaded ${result.emlFile} to SharePoint project ${projectResult.projectNumber} (${projectResult.projectFolder.description})`);
        } else {
          const details = projectResult.error ? ` (${projectResult.error})` : '';
          console.log(`SharePoint upload skipped for project ${projectResult.projectNumber}: ${projectResult.reason ?? 'unknown'}${details}`);
        }
      } else if (detectedProjectNumber === null && (config.sharepoint?.enabled !== false)) {
        console.log(`No project number found in subject "${subjectForProject}"; SharePoint upload skipped`);
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      console.error(`SharePoint handling failed for message ${message.id}:`, error);
      processingErrors.push(errorMessage);
      if (extractProjectNumber(subjectForProject) !== null) {
        projectResult = {
          handled: true,
          projectNumber: extractProjectNumber(subjectForProject),
          uploaded: false,
          reason: 'sharepoint_error',
          error: errorMessage,
        };
      }
    }

    try {
      const notificationResult = await sendArchiveNotifications(
        { ...config, ictUsers },
        token,
        message,
        result,
        projectResult,
        { processingErrors },
        fetchImpl,
      );

      if (notificationResult.sent === true) {
        console.log(`Sent ${notificationResult.type} notification to ${notificationResult.recipient}`);
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      console.error(`Notification failed for message ${message.id}:`, error);
      processingErrors.push(`Notificatie versturen mislukt: ${errorMessage}`);
    }

    if (graph.deleteAfterArchive !== false) {
      await deleteGraphMessage(graph, token, message.id, fetchImpl);
    }
  }
}

export async function listGraphMessages(graphConfig, accessToken, fetchImpl = fetch) {
  return listGraphMessagesWithOptions(graphConfig, accessToken, {}, fetchImpl);
}

export async function listGraphMessagesWithOptions(graphConfig, accessToken, options = {}, fetchImpl = fetch) {
  const response = await graphRequest(buildGraphMessagesUrl(graphConfig, {
    pageSize: options.pageSize ?? graphConfig.pageSize ?? 25,
    orderDirection: options.orderDirection ?? 'asc',
    onlyUnread: options.onlyUnread ?? graphConfig.onlyUnread === true,
  }), accessToken, fetchImpl);
  const messages = response.value;

  if (!Array.isArray(messages)) {
    throw new Error('Graph messages response bevat geen value-array.');
  }

  return messages.filter((message) => typeof message.id === 'string' && message.id !== '');
}

export function getMessageSenderEmail(message) {
  return String(message?.from?.emailAddress?.address ?? '').trim().toLowerCase();
}

export function getEmailDomain(email) {
  const atPosition = String(email).lastIndexOf('@');
  if (atPosition === -1) {
    return '';
  }

  return String(email).slice(atPosition + 1).trim().toLowerCase();
}

export function isSenderDomainAllowed(senderEmail, allowedDomains) {
  if (!Array.isArray(allowedDomains) || allowedDomains.length === 0) {
    return true;
  }

  const senderDomain = getEmailDomain(senderEmail);
  if (senderDomain === '') {
    return false;
  }

  const normalizedAllowedDomains = allowedDomains
    .map((domain) => String(domain).trim().toLowerCase())
    .filter(Boolean);

  return normalizedAllowedDomains.includes(senderDomain);
}

export async function getGraphMessageMime(graphConfig, accessToken, messageId, fetchImpl = fetch) {
  const graphBaseUrl = String(graphConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const mailbox = encodeURIComponent(graphConfig.mailbox);
  const url = `${graphBaseUrl}/users/${mailbox}/messages/${encodeURIComponent(messageId)}/$value`;
  const response = await fetchImpl(url, {
    headers: {
      Authorization: `Bearer ${accessToken}`,
      Accept: 'message/rfc822',
    },
  });

  if (!response.ok) {
    throw new Error(`Graph MIME request failed (${response.status}): ${await response.text()}`);
  }

  return Buffer.from(await response.arrayBuffer());
}

export async function deleteGraphMessage(graphConfig, accessToken, messageId, fetchImpl = fetch) {
  const graphBaseUrl = String(graphConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const mailbox = encodeURIComponent(graphConfig.mailbox);
  const url = `${graphBaseUrl}/users/${mailbox}/messages/${encodeURIComponent(messageId)}`;
  const response = await fetchImpl(url, {
    method: 'DELETE',
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

  if (!response.ok && response.status !== 404) {
    throw new Error(`Graph delete request failed (${response.status}): ${await response.text()}`);
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
 * Private Methods
 */

function encodeMailFolderPath(folderPath) {
  return String(folderPath)
    .split('/')
    .map((part) => encodeURIComponent(part))
    .join('/childFolders/');
}

async function graphRequest(url, accessToken, fetchImpl) {
  const response = await fetchImpl(url, {
    headers: {
      Authorization: `Bearer ${accessToken}`,
      Accept: 'application/json',
    },
  });
  const json = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(`Graph request failed (${response.status}): ${JSON.stringify(json)}`);
  }

  return json;
}

/**
 * Page load
 */

if (process.argv[1] === __filename) {
  runWorker();
}
