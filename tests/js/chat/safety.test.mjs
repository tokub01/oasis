import test from 'node:test';
import assert from 'node:assert/strict';
import { containsCardNumber, containsIban, scanIncoming, scanOutgoing, toneHint } from '../../../resources/js/chat/client/safety.js';

const lists = { insult: ['arschloch', 'vollidiot'], sexual: ['nudes', 'ficken'], hate: [], threat: ['umbringen'], extremism: [] };
const tone = { always: ['digga', 'brudi'], address: ['alter', 'bro'] };

test('incoming: asks for secrets, solicitation and insults are recognised', () => {
    assert.equal(scanIncoming('Schick mir mal dein Passwort, dann helfe ich dir', lists).kind, 'phishing');
    assert.equal(scanIncoming('wie ist deine Adresse?', lists).kind, 'phishing');
    assert.equal(scanIncoming('Lust auf ein Taschengeld-Treffen?', lists).kind, 'solicitation');
    assert.equal(scanIncoming('schick nudes', lists).kind, 'solicitation');
    assert.equal(scanIncoming('Du Vollidiot', lists).kind, 'harassment');
    assert.equal(scanIncoming('Ich bring dich um, ich will dich umbringen', lists).kind, 'harassment');
});

test('incoming: ordinary messages stay unflagged', () => {
    for (const text of ['Hallo, wie geht es dir?', 'Treffen wir uns am Samstag im Stadtgarten?', 'Das Passwort für das WLAN im Café steht an der Tafel']) {
        assert.equal(scanIncoming(text, lists), null, text);
    }
});

test('card numbers need a valid Luhn check sum, IBANs a valid mod-97 check sum', () => {
    assert.equal(containsCardNumber('Karte: 4111 1111 1111 1111'), true);
    assert.equal(containsCardNumber('Bestellnummer 1234 5678 9012 3456'), false);
    assert.equal(containsIban('DE89 3704 0044 0532 0130 00'), true);
    assert.equal(containsIban('DE00 3704 0044 0532 0130 00'), false);
});

test('outgoing: warns before sensitive data and finds the tone hint', () => {
    assert.deepEqual(scanOutgoing('mein Passwort ist sonne123', tone).sensitive, ['ein Passwort oder einen Code']);
    assert.deepEqual(scanOutgoing('Ich wohne in der Lindenstraße 12, 10115 Berlin', tone).sensitive, ['eine Adresse']);
    assert.deepEqual(scanOutgoing('Bis morgen!', tone), { sensitive: [], tone: null });
    assert.equal(toneHint('Digga, was geht', tone), 'Digga');
    assert.equal(toneHint('Im Alter von 30', tone), null);
    assert.equal(toneHint('Alter, hör auf', tone), 'Alter');
});
