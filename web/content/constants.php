<?php

/**
 * Constants
 */

const APP_NAME = 'Transcript Hub';
const MAX_UPLOAD_SIZE_BYTES = 52428800; // 50 MB
const ACCEPTED_UPLOAD_EXTENSIONS = ['txt', 'docx'];

const SHAREPOINT_GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
const SHAREPOINT_DEFAULT_TOKEN_SCOPE = 'https://graph.microsoft.com/.default';
const SHAREPOINT_DEFAULT_UPLOAD_FOLDER = 'Transcripten';
const SHAREPOINT_DEFAULT_TRANSCRIPT_STATUS_FIELD = 'TranscriptStatus';
const SHAREPOINT_STATUS_UNPROCESSED = 'Onverwerkt Transcript';
const SHAREPOINT_STATUS_MEETING_SUMMARY = 'Meeting Samenvatting';
