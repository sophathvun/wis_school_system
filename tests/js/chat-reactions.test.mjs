import test from 'node:test';
import assert from 'node:assert/strict';
import { renderMessageReactions, reactionOptions } from '../../resources/js/helpers/chatReactions.js';

test('reactions show counts, selected state and safe participant names', () => {
    const markup = renderMessageReactions({ id: 12, can_react: true, reactions: [
        { emoji: reactionOptions[0].emoji, count: 2, reacted: true, users: [{ name: '<script>"Name"</script>' }] },
    ] });
    assert.match(markup, /data-reaction-message="12"/);
    assert.match(markup, /Heart: 2, your reaction\. Click to remove/);
    assert.match(markup, /&lt;script&gt;&quot;Name&quot;&lt;\/script&gt;/);
    assert.ok(!markup.includes('<script>'));
    assert.ok(!markup.includes('data-reaction-picker'));
    assert.ok(!markup.includes('>React<'));
});

test('messages without supported reactions have no inline controls', () => {
    const markup = renderMessageReactions({ id: 1, can_react: true, reactions: [{ emoji: '<img src=x>', count: 1 }] });
    assert.equal(markup, '');
    assert.ok(!markup.includes('<img'));
    assert.equal(renderMessageReactions({ id: 1, can_react: false }), '');
});
