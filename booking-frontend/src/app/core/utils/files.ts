/**
 * Scrive la dimensione di un file a parole, in italiano: "850 byte", "120 KB", "1,5 MB".
 * Usa 1024 come base, come fanno i sistemi operativi per i file.
 */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} byte`;

  const kb = bytes / 1024;
  if (kb < 1024) return `${Math.round(kb)} KB`;

  const mb = kb / 1024;
  return `${mb.toFixed(1).replace('.', ',')} MB`;
}
