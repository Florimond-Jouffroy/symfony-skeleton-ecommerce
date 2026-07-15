/**
 * Fonctions de formatage de dates et labels partagées entre les composants.
 *
 * Convention back-end :
 *  - DateTransformer renvoie les dates au format 'dd-mm-yyyy' (parseApiDate, formatDisplayDate)
 *  - Les endpoints ISO renvoient 'yyyy-mm-dd' (parseIsoDate, formatIsoDate)
 */

/**
 * Convertit une date au format API 'dd-mm-yyyy' en 'dd/mm/yyyy' pour l'affichage.
 */
export function formatDisplayDate(str) {
    if (!str) return '';
    const [d, m, y] = str.split('-');
    return `${d}/${m}/${y}`;
}

/**
 * Construit un label de campagne à partir de l'objet campagne.
 */
export function formatCampagneLabel(campagne) {
    return `CA du ${formatDisplayDate(campagne.boardDate)}`;
}

/**
 * Parse une date au format API 'dd-mm-yyyy' en objet Date.
 */
export function parseApiDate(str) {
    if (!str) return undefined;
    const [d, m, y] = str.split('-');
    return new Date(Number(y), Number(m) - 1, Number(d));
}

/**
 * Parse une date ISO 'yyyy-mm-dd' en objet Date.
 */
export function parseIsoDate(str) {
    if (!str) return undefined;
    const [y, m, d] = str.split('-');
    if (!y || !m || !d) return undefined;
    const date = new Date(Number(y), Number(m) - 1, Number(d));
    return Number.isNaN(date.getTime()) ? undefined : date;
}

/**
 * Formate un objet Date en chaîne ISO 'yyyy-mm-dd'.
 */
export function formatIsoDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * Ajoute un nombre de jours à une date.
 */
export function addDays(date, days) {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
}

/**
 * Vérifie si une date est incluse dans un intervalle { from, to }.
 */
export function isDateInRange(date, range) {
    const t = date.getTime();
    return t >= range.from.getTime() && t <= range.to.getTime();
}
