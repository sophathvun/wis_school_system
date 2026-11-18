import test from 'node:test';
import assert from 'node:assert/strict';
import {setupWmiNavigation} from '../../resources/js/helpers/wmiNavigation.js';

function fixture(matches) {
    const classes=new Set(), attributes={}, handlers={},icon={className:''};
    const toggle={setAttribute:(name,value)=>attributes[name]=value,querySelector:()=>icon,addEventListener:(event,fn)=>handlers[event]=fn};
    const workspace={querySelector:()=>toggle,classList:{toggle:(name,on)=>on?classes.add(name):classes.delete(name)}};
    const media={matches,addEventListener:(event,fn)=>handlers.breakpoint=fn};
    setupWmiNavigation(workspace,media);
    return {classes,attributes,handlers,icon,media};
}
test('computer starts expanded and the collapse control keeps icon and ARIA in sync',()=>{
    const ui=fixture(true);
    assert.equal(ui.attributes['aria-expanded'],'true');assert.ok(ui.classes.has('is-expanded'));
    ui.handlers.click();assert.equal(ui.attributes['aria-expanded'],'false');assert.ok(ui.classes.has('is-collapsed'));
    assert.match(ui.icon.className,/expand$/);
    ui.handlers.click();assert.equal(ui.attributes['aria-expanded'],'true');
});
test('phone starts collapsed and can expand without navigating away',()=>{
    const ui=fixture(false);assert.equal(ui.attributes['aria-expanded'],'false');
    assert.ok(ui.classes.has('is-collapsed'));ui.handlers.click();assert.ok(ui.classes.has('is-expanded'));
});
test('changing device width resets navigation to the correct device default',()=>{
    const ui=fixture(true);ui.media.matches=false;ui.handlers.breakpoint();
    assert.equal(ui.attributes['aria-expanded'],'false');
    ui.media.matches=true;ui.handlers.breakpoint();assert.equal(ui.attributes['aria-expanded'],'true');
});
