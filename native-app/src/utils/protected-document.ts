import { Directory, File, Paths } from 'expo-file-system';
import * as FileSystemLegacy from 'expo-file-system/legacy';
import * as IntentLauncher from 'expo-intent-launcher';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

import { getApiAccessToken } from '../api/client';
import { resolveBackendUrlAsync } from '../api/runtime-base-url';

async function downloadProtectedFile(url: string, filename: string, mimeType: string, directoryName: string) {
  const resolvedUrl = await resolveBackendUrlAsync(url);
  if (!resolvedUrl) {
    throw new Error('This document is not available anymore.');
  }

  const token = getApiAccessToken();
  if (!token) {
    throw new Error('Your session expired. Please sign in again before opening the document.');
  }

  const outputDirectory = new Directory(Paths.cache, directoryName);
  outputDirectory.create({ idempotent: true, intermediates: true });
  const outputFile = new File(outputDirectory, filename);

  let downloadedFile;
  try {
    downloadedFile = await File.downloadFileAsync(resolvedUrl, outputFile, {
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

async function shareDownloadedFile(fileUri: string, filename: string, mimeType: string) {
  const canShare = await Sharing.isAvailableAsync();
  if (!canShare) {
    throw new Error('This device cannot hand the downloaded file to another app.');
  }

  await Sharing.shareAsync(fileUri, {
    dialogTitle: filename || 'Open downloaded file',
    mimeType,
    UTI: mimeType === 'application/pdf' ? 'com.adobe.pdf' : undefined,
  });
}

export async function shareProtectedFile(url: string, filename: string, mimeType: string, directoryName = 'slams-downloads') {
  const downloadedFile = await downloadProtectedFile(url, filename, mimeType, directoryName);
  await shareDownloadedFile(downloadedFile.uri, filename, mimeType);
}

async function openProtectedFileInAndroidViewer(fileUri: string, mimeType: string) {
  const contentUri = await FileSystemLegacy.getContentUriAsync(fileUri);

  await IntentLauncher.startActivityAsync('android.intent.action.VIEW', {
    data: contentUri,
    flags: 1,
    type: mimeType,
  });
}

export async function openProtectedFile(url: string, filename: string, mimeType: string, directoryName = 'slams-downloads') {
  const downloadedFile = await downloadProtectedFile(url, filename, mimeType, directoryName);

  if (Platform.OS === 'android') {
    try {
      await openProtectedFileInAndroidViewer(downloadedFile.uri, mimeType);
      return;
    } catch {
      if (await Sharing.isAvailableAsync()) {
        await shareDownloadedFile(downloadedFile.uri, filename, mimeType);
        return;
      }

      throw new Error('No document viewer is available on this device for this file.');
    }
  }

  await shareDownloadedFile(downloadedFile.uri, filename, mimeType);
}

export async function openProtectedPdf(url: string, filename: string) {
  await openProtectedFile(url, filename, 'application/pdf', 'slams-documents');
}
