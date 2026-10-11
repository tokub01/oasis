/**
 * Oasis chat UI – parse the URL hash into a screen. Pure function; testable in node.
 *
 * "" | "#"        → { name: 'list' }
 * "#neu"          → { name: 'new' }
 * "#c/12"         → { name: 'conversation', id: '12' }
 * "#c/12/info"    → { name: 'details', id: '12' }
 * anything else   → { name: 'list' }
 *
 * @param {string} hash `location.hash`
 * @returns {{name: 'list'|'new'|'conversation'|'details', id?: string}}
 */
export function parseRoute(hash) {
    const path = hash.replace(/^#\/?/, '');
    if (path === 'neu') {
        return { name: 'new' };
    }
    const match = /^c\/([1-9]\d{0,15})(\/info)?$/.exec(path);
    if (match) {
        return { name: match[2] ? 'details' : 'conversation', id: match[1] };
    }
    return { name: 'list' };
}
