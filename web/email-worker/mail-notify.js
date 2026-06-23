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

export function buildReplySubject(originalSubject) {
  const subject = String(originalSubject ?? '').trim();
  if (subject === '') {
    return 'Bevestiging Clio emailarchief';
  }

  return /^re:/i.test(subject) ? subject : `Re: ${subject}`;
}

export function buildArchiveOnlyBody() {
  return [
    'Uw e-mail is succesvol gearchiveerd in Clio.',
    '',
    'Met vriendelijke groet,',
    'Clio',
  ].join('\n');
}

export function buildProjectUploadFailedBody(projectNumber, reason = 'folder_not_found') {
  const intro = reason === 'folder_not_found'
    ? `Uw e-mail met projectnummer ${projectNumber} kon niet automatisch in SharePoint worden geplaatst, omdat er geen bijbehorende projectmap is gevonden.`
    : `Uw e-mail met projectnummer ${projectNumber} kon niet automatisch in SharePoint worden geplaatst.`;

  return [
    intro,
    'De e-mail is wel gearchiveerd in Clio.',
    '',
    'Met vriendelijke groet,',
    'Clio',
  ].join('\n');
}

export function buildProjectUploadSuccessBody(projectNumber, description) {
  return [
    `Uw e-mail is geplaatst in SharePoint onder projectnummer ${projectNumber} (${description}) en gearchiveerd in Clio.`,
    '',
    'Met vriendelijke groet,',
    'Clio',
  ].join('\n');
}

export async function sendMailToSender(graphConfig, accessToken, { toEmail, toName, subject, body }, fetchImpl = fetch) {
  const email = String(toEmail ?? '').trim();
  if (email === '') {
    throw new Error('Afzenderadres ontbreekt; notificatie niet verstuurd.');
  }

  const graphBaseUrl = String(graphConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const mailbox = encodeURIComponent(graphConfig.mailbox);
  const url = `${graphBaseUrl}/users/${mailbox}/sendMail`;
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
        body: {
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

export async function sendArchiveNotifications(graphConfig, accessToken, graphMessage, archiveResult, projectResult, fetchImpl = fetch) {
  const notifications = graphConfig.notifications ?? {};
  if (notifications.enabled === false) {
    return { sent: false, reason: 'disabled' };
  }

  const sender = getMessageSender(graphMessage);
  if (sender.email === '') {
    return { sent: false, reason: 'missing_sender' };
  }

  const replySubject = buildReplySubject(graphMessage?.subject ?? archiveResult?.subject ?? '');
  let body = '';

  if (projectResult?.handled === true) {
    if (projectResult.uploaded === true && projectResult.projectFolder) {
      body = buildProjectUploadSuccessBody(
        projectResult.projectNumber,
        projectResult.projectFolder.description,
      );
    } else {
      body = buildProjectUploadFailedBody(projectResult.projectNumber, projectResult.reason);
    }
  } else {
    body = buildArchiveOnlyBody();
  }

  await sendMailToSender(graphConfig, accessToken, {
    toEmail: sender.email,
    toName: sender.name,
    subject: replySubject,
    body,
  }, fetchImpl);

  return {
    sent: true,
    recipient: sender.email,
    type: projectResult?.handled === true
      ? (projectResult.uploaded === true ? 'project_upload_success' : 'project_upload_failed')
      : 'archive_only',
  };
}
