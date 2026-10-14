import test from 'node:test';
import assert from 'node:assert/strict';
import { imageEditorSize, imageEditorPoint, editedImageName } from '../../resources/js/helpers/chatImageEditor.js';

test('large pictures keep their proportions with bounded canvas memory and small pictures are not enlarged', () => {
    assert.deepEqual(imageEditorSize(4000, 3000), { width: 2048, height: 1536 });
    assert.deepEqual(imageEditorSize(3000, 4000), { width: 1536, height: 2048 });
    assert.deepEqual(imageEditorSize(320, 240), { width: 320, height: 240 });
});

test('touch coordinates map accurately to picture pixels and clamp captured gestures outside the picture', () => {
    const rect = { left: 20, top: 80, width: 200, height: 300 };
    assert.deepEqual(imageEditorPoint({ clientX: 120, clientY: 230 }, rect, 800, 1200), { x: 400, y: 600 });
    assert.deepEqual(imageEditorPoint({ clientX: -50, clientY: 600 }, rect, 800, 1200), { x: 0, y: 1200 });
});

test('edited attachments use a safe PNG name while retaining the source basename', () => {
    assert.equal(editedImageName('Student picture.JPG'), 'Student picture-edited.png');
    assert.equal(editedImageName('folder/picture.webp'), 'picture-edited.png');
    assert.equal(editedImageName('C:\\photos\\picture.png'), 'picture-edited.png');
    assert.equal(editedImageName(''), 'image-edited.png');
    assert.equal(editedImageName('photo?.gif'), 'photo-edited.png');
});
