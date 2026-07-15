/**
 * Wrapper fetch pour les appels API du projet.
 *
 * - Parse automatique du JSON en réponse (null sur 204 ou si Content-Type non-JSON)
 * - Sérialisation JSON automatique du body (sauf FormData)
 * - Construction des query params via un objet
 * - Erreurs HTTP levées via ApiError (status + body parsé si dispo)
 *
 * Utilisation :
 *   import { api, ApiError } from '../utils/api';
 *
 *   const data = await api.get('/api/tarifs/', { page: 1, pageSize: 20 });
 *   const created = await api.post('/api/tarifs/', payload);
 *   await api.delete(`/api/tarifs/${id}`);
 *
 *   try { ... } catch (e) {
 *     if (e instanceof ApiError && e.status === 422) { ... }
 *   }
 */

export class ApiError extends Error {
    constructor(status, message, data = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

function buildUrl(url, params) {
    if (!params) return url;

    const searchParams = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null || value === '') continue;
        searchParams.set(key, String(value));
    }
    const query = searchParams.toString();
    if (!query) return url;
    return url.includes('?') ? `${url}&${query}` : `${url}?${query}`;
}

async function parseBody(response) {
    if (response.status === 204) return null;
    const contentType = response.headers.get('Content-Type') || '';
    if (!contentType.includes('application/json')) return null;
    try {
        return await response.json();
    } catch {
        return null;
    }
}

async function request(url, options = {}) {
    const { params, body, headers = {}, ...rest } = options;
    const finalUrl = buildUrl(url, params);

    const finalHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        ...headers,
    };

    let finalBody = body;
    if (body !== undefined && body !== null && !(body instanceof FormData)) {
        finalBody = JSON.stringify(body);
        if (!finalHeaders['Content-Type']) {
            finalHeaders['Content-Type'] = 'application/json';
        }
    }

    const response = await fetch(finalUrl, {
        ...rest,
        headers: finalHeaders,
        body: finalBody,
    });

    if (!response.ok) {
        const data = await parseBody(response);
        const message =
            (data && (data.message || data.title || data.detail))
            || `HTTP ${response.status} ${response.statusText || ''}`.trim();
        throw new ApiError(response.status, message, data);
    }

    return parseBody(response);
}

export const api = {
    get:    (url, params) => request(url, { method: 'GET', params }),
    post:   (url, body)   => request(url, { method: 'POST',   body }),
    put:    (url, body)   => request(url, { method: 'PUT',    body }),
    patch:  (url, body)   => request(url, { method: 'PATCH',  body }),
    delete: (url)         => request(url, { method: 'DELETE' }),
};

/**
 * Retourne un message utilisateur lisible à partir d'une erreur levée par le wrapper.
 *
 * - ApiError 401 → "Session expirée..."
 * - ApiError 403 → "Accès refusé"
 * - ApiError 404 → "Ressource introuvable"
 * - ApiError 422 + body.violations → messages joints
 * - ApiError 5xx → "Erreur serveur"
 * - ApiError avec body.message / .title / .detail → utilisé
 * - TypeError (fetch a échoué, offline) → "Problème de connexion au serveur"
 * - fallback fourni par l'appelant sinon
 */
export function getErrorMessage(error, fallback = 'Une erreur est survenue.') {
    if (error instanceof ApiError) {
        switch (error.status) {
            case 401:
                return 'Votre session a expiré, veuillez vous reconnecter.';
            case 403:
                return "Vous n'avez pas les droits pour effectuer cette action.";
            case 404:
                return 'Ressource introuvable.';
            case 422: {
                const violations = error.data?.violations;
                if (Array.isArray(violations) && violations.length > 0) {
                    return violations
                        .map((v) => v?.title ?? v?.message ?? '')
                        .filter(Boolean)
                        .join(' • ');
                }
                break;
            }
            default:
                if (error.status >= 500) {
                    return 'Erreur serveur, veuillez réessayer plus tard.';
                }
        }
        return error.message || fallback;
    }

    if (error instanceof TypeError) {
        return 'Problème de connexion au serveur.';
    }

    return error?.message || fallback;
}

/**
 * Extrait une liste de messages de validation (typiquement pour un formulaire).
 *
 * Supporte plusieurs formats de réponse back :
 * - tableau brut : ['msg1', 'msg2']
 * - objet RFC 7807 : { violations: [{ propertyPath, title }, ...] }
 * - objet clé→message : { champ: 'msg', champ2: ['msg1', 'msg2'] }
 *
 * @returns {string[]}
 */
export function extractValidationErrors(error) {
    if (!(error instanceof ApiError) || !error.data) return [];

    const data = error.data;
    if (Array.isArray(data)) {
        return data.filter((v) => typeof v === 'string');
    }
    if (Array.isArray(data.violations)) {
        return data.violations
            .map((v) => v?.title ?? v?.message ?? '')
            .filter(Boolean);
    }
    if (typeof data === 'object') {
        const messages = [];
        for (const value of Object.values(data)) {
            if (typeof value === 'string') messages.push(value);
            else if (Array.isArray(value)) messages.push(...value.filter((v) => typeof v === 'string'));
        }
        return messages;
    }
    return [];
}
