import {
  buildArchiveOnlyNotification,
  buildClioEmailThreadUrl,
  buildClioNotificationEmail,
  buildProjectUploadFailedNotification,
  buildProjectUploadSuccessNotification,
} from './mail-template.js';

/**
 * Consts
 */

const DEFAULT_GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';

/**
 * Public methods
 */

export function getMessageSender(graphMessage) {
  const address = String(graphMessage?.from?.emailAddress?.address ?? '').trim();
  const name = String(graphMessage?.from?.emailAddress?.name ?? '').trim();

  return {
    email: address,
    name,
  };
}

export function isIctUser(senderEmail, ictUsers) {
  const normalized = String(senderEmail ?? '').trim().toLowerCase();
  if (normalized === '') {
    return false;
  }

  return (Array.isArray(ictUsers) ? ictUsers : [])
    .map((email) => String(email).trim().toLowerCase())
    .filter(Boolean)
    .includes(normalized);
}

export function buildIctDiagnosticLines({ graphMessage, archiveResult, projectResult, processingErrors = [], clioUrl = '' }) {
  const lines = [];

  if (graphMessage?.id) {
    lines.push(`Graph message id: ${graphMessage.id}`);
  }
  if (graphMessage?.subject) {
    lines.push(`Onderwerp: ${graphMessage.subject}`);
  }
  if (archiveResult?.folderName && archiveResult?.emlFile) {
    lines.push(`Clio archief: ${archiveResult.folderName}/${archiveResult.emlFile}`);
  }
  if (clioUrl) {
    lines.push(`Clio URL: ${clioUrl}`);
  }

  if (projectResult?.handled === true) {
    lines.push(`Projectnummer: ${projectResult.projectNumber ?? '-'}`);
    lines.push(`SharePoint afgehandeld: ja`);
    lines.push(`SharePoint geupload: ${projectResult.uploaded === true ? 'ja' : 'nee'}`);
    if (projectResult.reason) {
      lines.push(`SharePoint reden: ${projectResult.reason}`);
    }
    if (projectResult.error) {
      lines.push(`SharePoint fout: ${projectResult.error}`);
    }
    if (projectResult.uploadPath) {
      lines.push(`SharePoint pad: ${projectResult.uploadPath}`);
    }
    if (projectResult.fileUrl) {
      lines.push(`SharePoint URL: ${projectResult.fileUrl}`);
    }
    if (projectResult.metadataUpdated !== undefined) {
      lines.push(`Metadata bijgewerkt: ${projectResult.metadataUpdated ? 'ja' : 'nee'}`);
    }
    if (projectResult.metadataError) {
      lines.push(`Metadata fout: ${projectResult.metadataError}`);
    }
    if (projectResult.projectFolder?.folderName) {
      lines.push(`Projectmap: ${projectResult.projectFolder.folderName}`);
    }
    if (projectResult.projectFolder?.description) {
      lines.push(`Projectomschrijving: ${projectResult.projectFolder.description}`);
    }
  } else {
    lines.push('SharePoint afgehandeld: nee');
  }

  for (const error of processingErrors) {
    if (String(error).trim() !== '') {
      lines.push(`Verwerkingsfout: ${String(error).trim()}`);
    }
  }

  return lines;
}

export function appendIctDiagnostics(body, diagnosticLines) {
  if (!Array.isArray(diagnosticLines) || diagnosticLines.length === 0) {
    return body;
  }

  return [
    body,
    '',
    '--- ICT diagnose ---',
    ...diagnosticLines,
  ].join('\n');
}

export function buildReplySubject(originalSubject) {
  const subject = String(originalSubject ?? '').trim();
  if (subject === '') {
    return 'Bevestiging Clio emailarchief';
  }

  return /^re:/i.test(subject) ? subject : `Re: ${subject}`;
}

export function buildArchiveOnlyBody(clioUrl = '') {
  return buildArchiveOnlyNotification(clioUrl).plainText;
}

export function buildProjectUploadFailedBody(projectNumber, reason = 'folder_not_found', clioUrl = '') {
  return buildProjectUploadFailedNotification(projectNumber, reason, clioUrl).plainText;
}

export function buildProjectUploadSuccessBody(projectNumber, description, sharePointUrl = '', clioUrl = '') {
  return buildProjectUploadSuccessNotification(projectNumber, description, sharePointUrl, clioUrl).plainText;
}

export function resolveClioWebUrl(workerConfig) {
  const graphConfig = workerConfig.graph ?? workerConfig;
  return String(
    workerConfig.clioWebUrl
    ?? graphConfig.clioWebUrl
    ?? workerConfig.notifications?.clioWebUrl
    ?? graphConfig.notifications?.clioWebUrl
    ?? '',
  ).trim();
}

export function buildNotificationMessage(projectResult, archiveResult, options = {}) {
  const diagnosticLines = Array.isArray(options.diagnosticLines) ? options.diagnosticLines : [];
  const clioUrl = buildClioEmailThreadUrl(options.clioWebUrl, archiveResult?.folderName);
  let paragraphs = [];
  let sharePointUrl = '';

  if (projectResult?.handled === true) {
    if (projectResult.uploaded === true && projectResult.projectFolder) {
      paragraphs = [
        `Uw e-mail is geplaatst in SharePoint onder projectnummer ${projectResult.projectNumber} (${projectResult.projectFolder.description}) en gearchiveerd in Clio.`,
      ];
      sharePointUrl = String(projectResult.fileUrl ?? '').trim();
    } else {
      const intro = projectResult.reason === 'folder_not_found'
        ? `Uw e-mail met projectnummer ${projectResult.projectNumber} kon niet automatisch in SharePoint worden geplaatst, omdat er geen bijbehorende projectmap is gevonden.`
        : `Uw e-mail met projectnummer ${projectResult.projectNumber} kon niet automatisch in SharePoint worden geplaatst.`;
      paragraphs = [
        intro,
        'De e-mail is wel gearchiveerd in Clio.',
      ];
    }
  } else {
    paragraphs = ['Uw e-mail is succesvol gearchiveerd in Clio.'];
  }

  return buildClioNotificationEmail({
    paragraphs,
    clioUrl,
    sharePointUrl,
    diagnosticLines,
  });
}

export async function sendMailToSender(graphConfig, accessToken, { toEmail, toName, subject, body, htmlBody }, fetchImpl = fetch) {
  const email = String(toEmail ?? '').trim();
  if (email === '') {
    throw new Error('Afzenderadres ontbreekt; notificatie niet verstuurd.');
  }

  const graphBaseUrl = String(graphConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const mailbox = encodeURIComponent(graphConfig.mailbox);
  const url = `${graphBaseUrl}/users/${mailbox}/sendMail`;
  const html = String(htmlBody ?? '').trim();
  const response = await fetchImpl(url, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      message: {
        subject,
        body: html !== ''
          ? {
            contentType: 'HTML',
            content: html,
          }
          : {
            contentType: 'Text',
            content: body,
          },
        toRecipients: [
          {
            emailAddress: {
              address: email,
              name: toName || email,
            },
          },
        ],
      },
      saveToSentItems: true,
    }),
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(`Graph sendMail failed (${response.status}): ${errorText}`);
  }
}

export async function sendArchiveNotifications(workerConfig, accessToken, graphMessage, archiveResult, projectResult, options = {}, fetchImpl = fetch) {
  const graphConfig = workerConfig.graph ?? workerConfig;
  const notifications = graphConfig.notifications ?? {};
  if (notifications.enabled === false) {
    return { sent: false, reason: 'disabled' };
  }

  const sender = getMessageSender(graphMessage);
  if (sender.email === '') {
    return { sent: false, reason: 'missing_sender' };
  }

  const ictUsers = workerConfig.ictUsers ?? [];
  const includeIctDiagnostics = isIctUser(sender.email, ictUsers);
  const replySubject = buildReplySubject(graphMessage?.subject ?? archiveResult?.subject ?? '');
  const clioWebUrl = resolveClioWebUrl(workerConfig);
  const clioUrl = buildClioEmailThreadUrl(clioWebUrl, archiveResult?.folderName);
  const diagnosticLines = includeIctDiagnostics
    ? buildIctDiagnosticLines({
      graphMessage,
      archiveResult,
      projectResult,
      processingErrors: options.processingErrors ?? [],
      clioUrl,
    })
    : [];
  const notification = buildNotificationMessage(projectResult, archiveResult, {
    diagnosticLines,
    clioWebUrl,
  });

  await sendMailToSender(graphConfig, accessToken, {
    toEmail: sender.email,
    toName: sender.name,
    subject: replySubject,
    body: notification.plainText,
    htmlBody: notification.html,
  }, fetchImpl);

  return {
    sent: true,
    recipient: sender.email,
    ictDiagnostics: includeIctDiagnostics,
    type: projectResult?.handled === true
      ? (projectResult.uploaded === true ? 'project_upload_success' : 'project_upload_failed')
      : 'archive_only',
  };
}
