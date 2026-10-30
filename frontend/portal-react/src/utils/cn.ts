/** Une clases CSS ignorando los valores vacios (`false`, `undefined`, `null`). */
export function cn(...clases: (string | false | null | undefined)[]): string {
  return clases.filter(Boolean).join(' ')
}
