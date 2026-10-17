/**
 * Oasis chat – safety checks that run only in the member's browser (docs/oasis-chat-client.md, "Schutz im Chat").
 * Chats are end-to-end encrypted, so the server never sees the text: incoming messages are checked after decryption
 * to offer "block and delete" or a report, outgoing drafts to warn before passwords, card numbers, bank details or
 * an address leave the device, and to give the same tone hint as the Tropfen composer. Nothing here sends anything;
 * a report happens only when the member chooses it (consent-based reporting).
 *
 * Word lists come from the server (resources/moderation/wortliste.php and umgangston.php, rendered into the chat
 * page), so PHP and JS share one source.
 */

/** Requests for secrets, money data or an address ("schick mir dein Passwort", "wie ist deine Adresse"). */
const ASKS_FOR_SECRETS = /\b(schick|schicke|gib|sag|nenn|verrat|schreib|brauch\w*|wie\s+(?:ist|lautet))\b[^.!?\n]{0,40}\b(dein\w*|ihr\w*)\s+(passwort|kennwort|pin|tan|code|zugangsdaten|login\w*|kreditkarte\w*|kartennummer|karten-?daten|iban|bankdaten|kontonummer|adresse|anschrift|wohnort|handynummer)\b/i;

/** Offering or asking for sex, nudes or paid meetings, also coded. */
const SOLICITATION = /\b(taschengeld|tg[- ]?treffen|sugar\s?(daddy|baby|mommy|mami)|escort\w*|sex\s?date|sexdate|one\s?night\s?stand|nudes?|nacktbilder|nacktfotos|nacktvideos?|gegen\s+(bezahlung|geld|cash)|was\s+(kostest|nimmst)\s+du|bezahl\w*\s+dich|zeig\s+(mal\s+)?(dich|was)\s+nackt)\b/i;

/** Disclosing a secret ("mein Passwort ist …", "PIN: 1234"). */
const DISCLOSES_SECRET = /\b(mein\w*\s+)?(passwort|kennwort|password|pin|tan|zugangscode|bestätigungscode)\s*(ist|lautet|:|=)\s*\S+/i;

/** A street with a house number, or a German postcode followed by a place. */
const ADDRESS = /\b[A-ZÄÖÜ][a-zäöüß-]+(stra(ß|ss)e|str\.|weg|allee|platz|gasse|ring|damm|ufer|chaussee)\s+\d{1,4}\s?[a-z]?\b|\b\d{5}\s+[A-ZÄÖÜ][a-zäöüß]{2,}/;

const normalise = (text) => text.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss');
const words = (text) => normalise(text).split(/[^a-z]+/).filter(Boolean);

/** Card numbers: 13–19 digits passing the Luhn check, so order or phone numbers rarely match. */
export function containsCardNumber(text) {
    for (const match of text.matchAll(/(?:\d[ -]?){13,19}/g)) {
        const digits = match[0].replace(/\D/g, '');
        if (digits.length < 13 || digits.length > 19) {
            continue;
        }
        let sum = 0;
        for (let i = 0; i < digits.length; i++) {
            let digit = Number(digits[digits.length - 1 - i]);
            if (i % 2 === 1) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }
            sum += digit;
        }
        if (sum % 10 === 0) {
            return true;
        }
    }
    return false;
}

/** IBANs with a valid check sum (ISO 13616, mod 97). */
export function containsIban(text) {
    for (const match of text.toUpperCase().matchAll(/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]){11,30}\b/g)) {
        const iban = match[0].replace(/\s/g, '');
        const rearranged = (iban.slice(4) + iban.slice(0, 4)).replace(/[A-Z]/g, (letter) => String(letter.charCodeAt(0) - 55));
        let rest = 0;
        for (const digit of rearranged) {
            rest = (rest * 10 + Number(digit)) % 97;
        }
        if (rest === 1) {
            return true;
        }
    }
    return false;
}

/**
 * Check a decrypted message from someone else.
 *
 * @param {string} text
 * @param {{insult?: string[], hate?: string[], threat?: string[], sexual?: string[], extremism?: string[]}} lists
 * @returns {null | {kind: 'phishing'|'solicitation'|'harassment', title: string}}
 */
export function scanIncoming(text, lists = {}) {
    if (ASKS_FOR_SECRETS.test(text)) {
        return { kind: 'phishing', title: 'Achtung: Hier fragt jemand nach sensiblen Daten.' };
    }
    if (SOLICITATION.test(text) || hits(text, lists.sexual)) {
        return { kind: 'solicitation', title: 'Möglicherweise sexuelle Belästigung erkannt.' };
    }
    if (hits(text, lists.insult) || hits(text, lists.hate) || hits(text, lists.threat) || hits(text, lists.extremism)) {
        return { kind: 'harassment', title: 'Möglicherweise Belästigung erkannt.' };
    }
    return null;
}

/**
 * Check the member's own draft before it is sent.
 *
 * @returns {{sensitive: string[], tone: string|null}} what would leave the device, and a slang address for the hint
 */
export function scanOutgoing(text, tone = { always: [], address: [] }) {
    const sensitive = [];
    if (containsCardNumber(text)) {
        sensitive.push('eine Kartennummer');
    }
    if (containsIban(text)) {
        sensitive.push('eine IBAN');
    }
    if (DISCLOSES_SECRET.test(text)) {
        sensitive.push('ein Passwort oder einen Code');
    }
    if (ADDRESS.test(text)) {
        sensitive.push('eine Adresse');
    }
    return { sensitive, tone: toneHint(text, tone) };
}

/** Same rule as App\Support\Moderation\ToneHint: always-slang words anywhere, ordinary words only as an address. */
export function toneHint(text, tone = { always: [], address: [] }) {
    const plain = text.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/Ä/g, 'Ae').replace(/Ö/g, 'Oe').replace(/Ü/g, 'Ue');
    const escape = (word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    if (tone.always?.length) {
        const match = plain.match(new RegExp(`(?<![\\p{L}])(${tone.always.map(escape).join('|')})(?![\\p{L}])`, 'iu'));
        if (match) {
            return match[1];
        }
    }
    if (tone.address?.length) {
        const list = tone.address.map(escape).join('|');
        const match = plain.match(new RegExp(`(?:^|[.!?]\\s+)(${list})\\s*[,!?]`, 'iu'))
            || plain.match(new RegExp(`,\\s*(${list})\\s*[.!?]*\\s*$`, 'iu'));
        if (match) {
            return match[1];
        }
    }
    return null;
}

function hits(text, list) {
    if (!list?.length) {
        return false;
    }
    const found = new Set(words(text));
    return list.some((word) => found.has(word));
}
