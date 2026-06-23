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

  const legacyMatch = text.match(/\b(15\d{4})\b/);
  if (legacyMatch) {
    return legacyMatch[1];
  }

  return null;
}

export function parseProjectFolderName(folderName, projectNumber) {
  const folder = String(folderName);
  const normalizedFolder = folder.toLowerCase();
  const normalizedPrefix = `${String(projectNumber).toLowerCase()}_`;
  if (!normalizedFolder.startsWith(normalizedPrefix)) {
    return null;
  }

  const description = folder.slice(String(projectNumber).length + 1).trim();
  if (description === '') {
    return null;
  }

  return {
    folderName: folder,
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

export function getProjectsFolderPath(sharepointConfig) {
  if (Object.prototype.hasOwnProperty.call(sharepointConfig ?? {}, 'projectsFolder')) {
    return String(sharepointConfig.projectsFolder ?? '').trim();
  }

  return String(sharepointConfig?.driveId ?? '').trim() !== '' ? '' : 'Projects';
}

export function buildDriveChildrenUrl(graphBaseUrl, driveId, projectsFolder) {
  const base = `${graphBaseUrl}/drives/${encodeURIComponent(driveId)}`;
  const folderPath = encodeSharePointPath(projectsFolder);
  if (folderPath === '') {
    return `${base}/root/children?$select=name,folder`;
  }

  return `${base}/root:/${folderPath}:/children?$select=name,folder`;
}

export function buildDriveUploadUrl(graphBaseUrl, driveId, ...pathSegments) {
  const uploadPath = encodeSharePointPath(...pathSegments);
  return `${graphBaseUrl}/drives/${encodeURIComponent(driveId)}/root:/${uploadPath}:/content`;
}

export async function resolveSharePointDriveId(sharepointConfig, accessToken, fetchImpl = fetch) {
  if (String(sharepointConfig?.driveId ?? '').trim() !== '') {
    return String(sharepointConfig.driveId).trim();
  }

  const graphBaseUrl = String(sharepointConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const siteId = String(sharepointConfig?.siteId ?? '').trim();
  if (siteId !== '') {
    const url = `${graphBaseUrl}/sites/${encodeURIComponent(siteId)}/drive`;
    return fetchSharePointDriveId(url, accessToken, fetchImpl);
  }

  const hostname = String(sharepointConfig?.siteHostname ?? '').trim();
  const sitePath = String(sharepointConfig?.sitePath ?? '').trim();
  if (hostname === '' || sitePath === '') {
    throw new Error('SharePoint configuratie mist driveId, siteId of siteHostname/sitePath.');
  }

  const url = `${graphBaseUrl}/sites/${encodeURIComponent(hostname)}:${sitePath}:/drive`;
  return fetchSharePointDriveId(url, accessToken, fetchImpl);
}

async function fetchSharePointDriveId(url, accessToken, fetchImpl) {
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
  const projectsFolder = getProjectsFolderPath(sharepointConfig);
  const graphBaseUrl = String(sharepointConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const url = buildDriveChildrenUrl(graphBaseUrl, driveId, projectsFolder);
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
  const projectsFolder = getProjectsFolderPath(sharepointConfig);
  const graphBaseUrl = String(sharepointConfig.graphBaseUrl ?? DEFAULT_GRAPH_BASE_URL).replace(/\/+$/, '');
  const url = buildDriveUploadUrl(
    graphBaseUrl,
    driveId,
    projectsFolder,
    projectFolder.folderName,
    emlFileName,
  );
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

  return encodeSharePointPath(projectsFolder, projectFolder.folderName, emlFileName);
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
