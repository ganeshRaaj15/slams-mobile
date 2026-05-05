import { Directory, File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';

import { getApiAccessToken } from '../api/client';
import { resolveBackendUrl } from '../config/env';

async function downloadProtectedFile(url: string, filename: string, mimeType: string, directoryName: string) {
  const resolvedUrl = resolveBackendUrl(url);
  if (!resolvedUrl) {
    throw new Error('This document is not available anymore.');
  }

  const token = getApiAccessToken();
  if (!token) {
    throw new Error('Your session expired. Please sign in again before opening the document.');
  }

  const outputDirectory = new Directory(Paths.cache, directoryName);
  outputDirectory.create({ idempotent: true, intermediates: true });

  let downloadedFile;
  try {
    downloadedFile = await File.downloadFileAsync(resolvedUrl, outputDirectory, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: mimeType,
      },
      idempotent: true,
    });
  } catch {
    throw new Error('The file could not be downloaded on this device. Check the server connection and try again.');
  }

  return {
    filename,
    uri: downloadedFile.uri,
  };
}

export async function shareProtectedFile(url: string, filename: string, mimeType: string, directoryName = 'slams-downloads') {
  const downloadedFile = await downloadProtectedFile(url, filename, mimeType, directoryName);

  const canShare = await Sharing.isAvailableAsync();
  if (!canShare) {
    throw new Error('This device cannot hand the downloaded file to another app.');
  }

  await Sharing.shareAsync(downloadedFile.uri, {
    dialogTitle: filename || 'Open downloaded file',
    mimeType,
    UTI: mimeType === 'application/pdf' ? 'com.adobe.pdf' : undefined,
  });
}

export async function openProtectedPdf(url: string, filename: string) {
  await shareProtectedFile(url, filename, 'application/pdf', 'slams-documents');
}
