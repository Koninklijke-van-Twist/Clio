/**
 * Consts
 */

const CLIO_COLORS = {
  bg: '#f7f3eb',
  card: '#fffcf7',
  ink: '#222020',
  muted: '#675f55',
  accent: '#0f766e',
  accentStrong: '#0b4d48',
  border: '#d7cab6',
  diagnosticBg: '#fff4e5',
  diagnosticBorder: '#e8d8aa',
};

const FONT_SANS = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
const FONT_SERIF = "Georgia, 'Times New Roman', serif";

/**
 * Public methods
 */

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function buildClioNotificationEmail({ paragraphs = [], fileUrl = '', diagnosticLines = [] } = {}) {
  const normalizedParagraphs = paragraphs.map((paragraph) => String(paragraph).trim()).filter(Boolean);
  const plainParts = [...normalizedParagraphs];
  if (fileUrl) {
    plainParts.push('', `Bestand in SharePoint: ${fileUrl}`);
  }
  plainParts.push('', 'Met vriendelijke groet,', 'Clio');

  if (Array.isArray(diagnosticLines) && diagnosticLines.length > 0) {
    plainParts.push('', '--- ICT diagnose ---', ...diagnosticLines);
  }

  const paragraphHtml = normalizedParagraphs
    .map((paragraph) => (
      `<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:${CLIO_COLORS.ink};">${escapeHtml(paragraph)}</p>`
    ))
    .join('');

  const fileLinkHtml = fileUrl
    ? [
      `<p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:${CLIO_COLORS.ink};">`,
      `<a href="${escapeHtml(fileUrl)}" style="display:inline-block;padding:10px 18px;background-color:${CLIO_COLORS.accent};color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;font-size:14px;">Bekijk bestand in SharePoint</a>`,
      '</p>',
      `<p style="margin:0 0 14px;font-size:13px;line-height:1.5;color:${CLIO_COLORS.muted};word-break:break-all;">`,
      `<a href="${escapeHtml(fileUrl)}" style="color:${CLIO_COLORS.accentStrong};text-decoration:underline;">${escapeHtml(fileUrl)}</a>`,
      '</p>',
    ].join('')
    : '';

  const diagnosticHtml = Array.isArray(diagnosticLines) && diagnosticLines.length > 0
    ? [
      `<div style="margin-top:18px;padding:14px 16px;background-color:${CLIO_COLORS.diagnosticBg};border:1px solid ${CLIO_COLORS.diagnosticBorder};border-radius:12px;">`,
      `<p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:${CLIO_COLORS.muted};">ICT diagnose</p>`,
      `<pre style="margin:0;font-family:Consolas,'Courier New',monospace;font-size:12px;line-height:1.45;color:${CLIO_COLORS.ink};white-space:pre-wrap;word-break:break-word;">${escapeHtml(diagnosticLines.join('\n'))}</pre>`,
      '</div>',
    ].join('')
    : '';

  const html = [
    '<!DOCTYPE html>',
    '<html lang="nl">',
    '<head>',
    '<meta charset="utf-8">',
    '<meta name="viewport" content="width=device-width, initial-scale=1">',
    '<title>Clio</title>',
    '</head>',
    `<body style="margin:0;padding:0;background-color:${CLIO_COLORS.bg};">`,
    `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:${CLIO_COLORS.bg};">`,
    '<tr>',
    '<td align="center" style="padding:24px 12px;">',
    `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background-color:${CLIO_COLORS.card};border:1px solid ${CLIO_COLORS.border};border-radius:16px;">`,
    '<tr>',
    `<td style="padding:24px 28px;font-family:${FONT_SANS};color:${CLIO_COLORS.ink};">`,
    `<h1 style="margin:0 0 18px;font-family:${FONT_SERIF};font-size:24px;line-height:1.2;color:${CLIO_COLORS.accent};">Clio</h1>`,
    paragraphHtml,
    fileLinkHtml,
    `<p style="margin:18px 0 0;font-size:15px;line-height:1.55;color:${CLIO_COLORS.ink};">Met vriendelijke groet,<br><strong>Clio</strong></p>`,
    diagnosticHtml,
    '</td>',
    '</tr>',
    '</table>',
    '</td>',
    '</tr>',
    '</table>',
    '</body>',
    '</html>',
  ].join('');

  return {
    plainText: plainParts.join('\n'),
    html,
  };
}

export function buildArchiveOnlyNotification() {
  return buildClioNotificationEmail({
    paragraphs: ['Uw e-mail is succesvol gearchiveerd in Clio.'],
  });
}

export function buildProjectUploadFailedNotification(projectNumber, reason = 'folder_not_found') {
  const intro = reason === 'folder_not_found'
    ? `Uw e-mail met projectnummer ${projectNumber} kon niet automatisch in SharePoint worden geplaatst, omdat er geen bijbehorende projectmap is gevonden.`
    : `Uw e-mail met projectnummer ${projectNumber} kon niet automatisch in SharePoint worden geplaatst.`;

  return buildClioNotificationEmail({
    paragraphs: [
      intro,
      'De e-mail is wel gearchiveerd in Clio.',
    ],
  });
}

export function buildProjectUploadSuccessNotification(projectNumber, description, fileUrl = '') {
  return buildClioNotificationEmail({
    paragraphs: [
      `Uw e-mail is geplaatst in SharePoint onder projectnummer ${projectNumber} (${description}) en gearchiveerd in Clio.`,
    ],
    fileUrl: String(fileUrl ?? '').trim(),
  });
}
