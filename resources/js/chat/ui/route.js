/**
 * Oasis chat UI – parse the URL hash into a screen. Pure function; testable in node.
 *
 * "" | "#"        → { name: 'list' }
 * "#neu"          → { name: 'new' }
 * "#neu/mira"     → { name: 'new', username: 'mira' } (from a "Schreiben" button)
 * "#c/12"         → { name: 'conversation', id: '12' }
 * "#c/12/info"    → { name: 'details', id: '12' }
 * anything else   → { name: 'list' }
 *
 * @param {string} hash `location.hash`
 * @returns {{name: 'list'|'new'|'conversation'|'details', id?: string, username?: string}}
 */
export function parseRoute(hash) {
    const path = hash.replace(/^#\/?/, '');
    if (path === 'neu') {
        return { name: 'new' };
    }
    const preset = /^neu\/([a-z0-9_]{3,30})$/.exec(path);
    if (preset) {
        return { name: 'new', username: preset[1] };
    }
    const match = /^c\/([1-9]\d{0,15})(\/info)?$/.exec(path);
    if (match) {
        return { name: match[2] ? 'details' : 'conversation', id: match[1] };
    }
    return { name: 'list' };
}
