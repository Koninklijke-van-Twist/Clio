#!/usr/bin/env node

import { loadConfig, getGraphAccessToken } from './worker.js';
import {
  extractProjectNumber,
  findProjectFolder,
  getProjectsFolderPath,
  listDriveFolderChildren,
  loadDriveListColumns,
  resolveSharePointDriveId,
} from './project.js';

const projectNumber = String(process.argv[2] ?? '').trim();

if (projectNumber === '') {
  console.error('Gebruik: node sharepoint-diagnose.js <projectnummer>');
  process.exit(1);
}

const config = await loadConfig();
const token = await getGraphAccessToken(config.graph);
const sharepoint = config.sharepoint ?? {};
const graphBaseUrl = String(config.graph.graphBaseUrl ?? 'https://graph.microsoft.com/v1.0').replace(/\/+$/, '');
const driveId = await resolveSharePointDriveId(sharepoint, token);
const projectsFolder = getProjectsFolderPath(sharepoint);

console.log(`Projectnummer: ${projectNumber}`);
console.log(`Drive ID: ${driveId}`);
console.log(`Projects folder prefix: ${projectsFolder === '' ? '(drive root)' : projectsFolder}`);

const children = await listDriveFolderChildren(graphBaseUrl, driveId, projectsFolder, token);
const matches = children
  .filter((item) => item?.folder && typeof item.name === 'string')
  .map((item) => item.name)
  .filter((name) => name.toLowerCase().startsWith(`${projectNumber.toLowerCase()}_`));

console.log(`Gevonden projectmappen (${matches.length}):`);
for (const name of matches.slice(0, 20)) {
  console.log(`- ${name}`);
}

const folder = await findProjectFolder(sharepoint, token, projectNumber);
console.log(folder ? `Geselecteerde map: ${folder.folderName} (${folder.description})` : 'Geen projectmap geselecteerd.');

const columns = await loadDriveListColumns(graphBaseUrl, driveId, token);
const metadataFields = sharepoint.metadataFields ?? {};
const wanted = [
  metadataFields.jobNo ?? 'Job No.',
  metadataFields.description ?? 'KVT Sales Quote Description',
];

console.log('Metadata kolommen:');
for (const displayName of wanted) {
  const column = columns.find((entry) => String(entry.displayName ?? '') === displayName);
  console.log(column
    ? `- ${displayName} -> ${column.name}`
    : `- ${displayName} -> NIET GEVONDEN`);
}

if (!extractProjectNumber(projectNumber) && !extractProjectNumber(`x ${projectNumber} x`)) {
  console.log('Let op: dit nummer matcht niet het verwachte patroon in een onderwerpregel.');
}
