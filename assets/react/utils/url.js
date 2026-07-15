/**
 * Remplace les placeholders dans un template d'URL.
 *
 * Accepte deux formes :
 *   resolveUrl('/api/tarifs/__ID__', { __ID__: 42 })
 *   resolveUrl('/api/tarifs/__ID__', 42)            // raccourci pour { __ID__: value }
 *
 * Les valeurs sont passées par encodeURIComponent.
 */
export function resolveUrl(template, replacements) {
    if (typeof replacements !== 'object' || replacements === null) {
        return template.replace('__ID__', encodeURIComponent(replacements));
    }

    let result = template;
    for (const [placeholder, value] of Object.entries(replacements)) {
        result = result.replace(placeholder, encodeURIComponent(value));
    }
    return result;
}
