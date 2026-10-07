import { test } from 'node:test';
import assert from 'node:assert/strict';
import shellQuote from 'shell-quote';

test('shell-quote rejects line terminators after a comment (GHSA-pqg4-j6r4-53mv)', () => {
    for (const terminator of ['\n', '\r', '\u2028', '\u2029']) {
        assert.throws(() => shellQuote.quote([{ comment: 'local test' }, `argument${terminator}another argument`]));
    }
    const argumentsWithQuotes = ['plain', 'two words', "a'b", '!literal'];
    assert.deepEqual(shellQuote.parse(shellQuote.quote(argumentsWithQuotes)), argumentsWithQuotes);
});
