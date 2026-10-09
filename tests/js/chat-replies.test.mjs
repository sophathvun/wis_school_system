import test from 'node:test';
import assert from 'node:assert/strict';
import { renderReplyQuote, renderMessageMenu, replyPreview } from '../../resources/js/helpers/chatReplies.js';

test('quotes escape names and text and point to the original message', () => {
    const html = renderReplyQuote({ reply_to: { id: 15, user_name: '<img src=x>', message: '"Hello" <script>', message_type: 'text' } });
    assert.match(html, /data-jump-reply="15"/);
    assert.match(html, /&lt;img src=x&gt;/);
    assert.match(html, /&quot;Hello&quot; &lt;script&gt;/);
    assert.ok(!html.includes('<img'));
    assert.equal(renderReplyQuote({}), '');
});

test('unavailable replies do not expose text or clickable references', () => {
    const html = renderReplyQuote({ reply_to: { id: 1, unavailable: true, message: 'Secret', user_name: 'Private' } });
    assert.match(html, /Message unavailable/);
    assert.ok(!html.includes('Secret') && !html.includes('Private') && !html.includes('data-jump-reply'));
});

test('message popup only offers allowed actions and marks the selected reaction', () => {
    const menu = renderMessageMenu({ can_react: true, can_reply: true, can_delete: true, reactions: [{ emoji: '\u2764\uFE0F', reacted: true }] });
    assert.match(menu, /React to message/);
    assert.match(menu, /aria-label="Heart" aria-pressed="true"/);
    assert.match(menu, /data-menu-reply/);
    assert.match(menu, /data-menu-delete/);
    assert.ok(!renderMessageMenu({ can_reply: true }).includes('data-menu-delete'));
    assert.ok(!renderMessageMenu({ can_delete: true }).includes('data-menu-reaction'));
    assert.equal(renderMessageMenu({}), '');
});

test('previews are compact and identify photos files and voice messages', () => {
    assert.equal(replyPreview({ message_type: 'voice', message: 'Voice message' }), 'Voice message');
    assert.equal(replyPreview({ message_type: 'image', message: 'Please\n review' }), 'Photo: Please review');
    assert.equal(replyPreview({ message_type: 'file', media_name: 'document.pdf' }), 'File: document.pdf');
    assert.equal(replyPreview({ message: 'x'.repeat(500) }).length, 180);
});
