/** Clase de la insignia segun el estado que manda la API. */
export function tono(estado?: string): string {
  const v = estado ?? '';
  if (/CAPTURADO|ACTIV|APROBAD|PAGAD|VERIFICAD|COMPLETAD|EXITOSA/.test(v)) return 'ok';
  if (/PENDIENTE|BORRADOR|GENERAD|SOLICITAD|PAUSAD|RETENID|SUSPENDID/.test(v)) return 'espera';
  if (/FALLIDO|CANCEL|RECHAZ|ANULAD|VENCID|RESCINDID|INACTIV/.test(v)) return 'mal';
  return 'neutro';
}
