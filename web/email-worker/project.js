/**
 * Consts
 */

const DEFAULT_GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';

/**
 * Public methods
 */

export function extractProjectNumber(subject) {
  const text = String(subject ?? '');

  const prjMatch = text.match(/\b(PRJ\d{6,9})\b/i);
  if (prjMatch) {
    return prjMatch[1].toUpperCase();
  }

  const legacyMatch = text.match(/\b(15\d{6})\b/);
  if (legacyMatch) {
    return legacyMatch[1];
  }

  return null;
}

export function parseProjectFolderName(folderName, projectNumber) {
  const prefix = `${projectNumber}_`;
  if (!String(folderName).startsWith(prefix)) {
    return null;
  }

  const description = String(folderName).slice(prefix.length).trim();
  if (description === '') {
    return null;
  }

  return {
    folderName: String(folderName),
    description,
  };
}

export function encodeSharePointPath(...segments) {
  return segments
    .flatMap((segment) => String(segment).split('/'))
    .filter(Boolean)
    .map((segment) => encodeURIComponent(segment))
    .join('/');
}

export async function resolveSharePointDriveId(sharepointConfig, accessToken, fetchImpl = fetch) {
  if (String(sharepointConfig?.driveId ?? '').trim() !== '') {
    return String(sharepointConfig.driveId).trim();
  }

  const hostname = String(sharepointConfig?.siteHostname ?? '').trim();
  const sitePath = String(sharepointConfig?.sitePath ?? '').trim();
  if (hostname === '' || sitePath === '') {
    throw new Error('SharePoint configuratie mist siteHostname/sitePath of driveId.');
  }

  const graphBaseUrl = String(sharepointConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const url = `${graphBaseUrl}/sites/${encodeURIComponent(hostname)}:${sitePath}:/drive`;
  const response = await fetchImpl(url, {
    headers: {
      Authorization: `Bearer ${accessToken}`,
      Accept: 'application/json',
    },
  });
  const json = await response.json().catch(() => ({}));

  if (!response.ok || typeof json.id !== 'string' || json.id === '') {
    throw new Error(`SharePoint drive lookup failed (${response.status}): ${JSON.stringify(json)}`);
  }

  return json.id;
}

export async function findProjectFolder(sharepointConfig, accessToken, projectNumber, fetchImpl = fetch) {
  const driveId = await resolveSharePointDriveId(sharepointConfig, accessToken, fetchImpl);
  const projectsFolder = String(sharepointConfig?.projectsFolder ?? 'Projects').trim() || 'Projects';
  const graphBaseUrl = String(sharepointConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const folderPath = encodeSharePointPath(projectsFolder);
  const url = `${graphBaseUrl}/drives/${encodeURIComponent(driveId)}/root:/${folderPath}:/children?$select=name,folder`;
  const response = await fetchImpl(url, {
    headers: {
      Authorization: `Bearer ${accessToken}`,
      Accept: 'application/json',
    },
  });
  const json = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(`SharePoint folder listing failed (${response.status}): ${JSON.stringify(json)}`);
  }

  const prefix = `${projectNumber}_`.toLowerCase();
  const matches = (Array.isArray(json.value) ? json.value : [])
    .filter((item) => item?.folder && typeof item.name === 'string')
    .map((item) => parseProjectFolderName(item.name, projectNumber))
    .filter(Boolean)
    .sort((left, right) => left.folderName.localeCompare(right.folderName, 'nl'));

  if (matches.length === 0) {
    return null;
  }

  return matches[0];
}

export async function uploadEmlToProjectFolder(sharepointConfig, accessToken, projectFolder, emlFileName, emlContent, fetchImpl = fetch) {
  const driveId = await resolveSharePointDriveId(sharepointConfig, accessToken, fetchImpl);
  const projectsFolder = String(sharepointConfig?.projectsFolder ?? 'Projects').trim() || 'Projects';
  const graphBaseUrl = String(sharepointConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const uploadPath = encodeSharePointPath(projectsFolder, projectFolder.folderName, emlFileName);
  const url = `${graphBaseUrl}/drives/${encodeURIComponent(driveId)}/root:/${uploadPath}:/content`;
  const response = await fetchImpl(url, {
    method: 'PUT',
    headers: {
      Authorization: `Bearer ${accessToken}`,
      'Content-Type': 'message/rfc822',
    },
    body: emlContent,
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(`SharePoint upload failed (${response.status}): ${errorText}`);
  }

  return uploadPath;
}

export async function handleProjectSharePointUpload(config, accessToken, subject, emlFileName, emlContent, fetchImpl = fetch) {
  const sharepointConfig = config.sharepoint ?? {};
  if (sharepointConfig.enabled === false) {
    return { handled: false };
  }

  const projectNumber = extractProjectNumber(subject);
  if (projectNumber === null) {
    return { handled: false };
  }

  const projectFolder = await findProjectFolder(sharepointConfig, accessToken, projectNumber, fetchImpl);
  if (projectFolder === null) {
    return {
      handled: true,
      projectNumber,
      uploaded: false,
      reason: 'folder_not_found',
    };
  }

  try {
    await uploadEmlToProjectFolder(
      sharepointConfig,
      accessToken,
      projectFolder,
      emlFileName,
      emlContent,
      fetchImpl,
    );

    return {
      handled: true,
      projectNumber,
      uploaded: true,
      projectFolder,
    };
  } catch (error) {
    return {
      handled: true,
      projectNumber,
      uploaded: false,
      reason: 'upload_failed',
      error: error instanceof Error ? error.message : String(error),
      projectFolder,
    };
  }
}
