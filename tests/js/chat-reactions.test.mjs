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
    assert.match(markup, /Choose a reaction/);
    for (const option of reactionOptions) assert.ok(markup.includes(`aria-label="${option.label}"`));
});

test('old messages with no reactions have a picker and unsupported reactions are not rendered', () => {
    const markup = renderMessageReactions({ id: 1, can_react: true, reactions: [{ emoji: '<img src=x>', count: 1 }] });
    assert.match(markup, /React to message/);
    assert.match(markup, /hidden/);
    assert.ok(!markup.includes('<img'));
    assert.equal(renderMessageReactions({ id: 1, can_react: false }), '');
});
