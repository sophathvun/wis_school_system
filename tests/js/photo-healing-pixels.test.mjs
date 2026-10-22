import test from 'node:test';
import assert from 'node:assert/strict';
import { healPixels, circularHealingMask, findHealingDonor, blurPixels } from '../../resources/js/helpers/photoHealingPixels.js';

const plain = (width, height, rgb=[170,140,120]) => {
    const pixels=new Uint8ClampedArray(width*height*4);
    for(let i=0;i<pixels.length;i+=4)pixels.set([...rgb,255],i);
    return pixels;
};
test('spot healing removes a dark mark with clean nearby texture and leaves outside pixels intact',()=>{
    const width=100,height=100,pixels=plain(width,height),i=(50*width+50)*4;
    pixels.set([20,20,20,255],i);const before=new Uint8ClampedArray(pixels);
    const mask=circularHealingMask(50,50,6,.7),offset=findHealingDonor(before,width,height,50,50,6);
    assert.ok(offset);assert.ok(healPixels(pixels,before,width,height,mask,offset,1));
    assert.deepEqual([...pixels.slice(i,i+4)],[170,140,120,255]);
    assert.deepEqual([...pixels.slice(0,4)],[170,140,120,255]);
});
test('healing strength blends gently and never modifies alpha or samples outside image boundaries',()=>{
    const pixels=plain(20,20),i=(10*20+10)*4;pixels.set([20,20,20,255],i);
    const before=new Uint8ClampedArray(pixels);
    healPixels(pixels,before,20,20,circularHealingMask(10,10,3,.8),{x:5,y:0},.5);
    assert.ok(pixels[i]>20&&pixels[i]<170);assert.equal(pixels[i+3],255);
    const alpha=plain(20,20);alpha[i+3]=90;const saved=[...alpha];
    healPixels(alpha,new Uint8ClampedArray(alpha),20,20,circularHealingMask(-2,-2,8),{x:-5,y:-5},1);
    assert.deepEqual([...alpha],saved);
});
test('patch healing corrects bounded color differences while preserving sampled texture',()=>{
    const pixels=plain(30,20),donor=new Uint8ClampedArray(pixels);
    for(let y=4;y<12;y++)for(let x=4;x<12;x++)pixels[(y*30+x)*4]=140;
    donor[(8*30+20)*4]=200;
    const mask={x:4,y:4,width:8,height:8,data:new Float32Array(64).fill(.15)};
    mask.data[4*8+4]=1;
    assert.ok(healPixels(pixels,donor,30,20,mask,{x:12,y:0},1));
    assert.ok(pixels[(8*30+8)*4]>140,'Texture from chosen source is retained');
    assert.equal(pixels[(8*30+8)*4+3],255);
});
test('automatic donor selection reports unavailable sources for an oversized brush',()=>{
    assert.equal(findHealingDonor(plain(20,20),20,20,10,10,15),null);
});

test('blur blends only inside the brush with adjustable strength and preserves alpha',()=>{
    const pixels=plain(20,20),blurred=plain(20,20,[100,110,120]),i=(10*20+10)*4;
    const before=new Uint8ClampedArray(pixels),mask=circularHealingMask(10,10,4,.7);
    assert.ok(blurPixels(pixels,blurred,20,20,mask,.5));
    assert.deepEqual([...pixels.slice(i,i+4)],[135,125,120,255]);
    assert.deepEqual([...pixels.slice(0,4)],[...before.slice(0,4)]);
    pixels[i+3]=90;const transparent=[...pixels.slice(i,i+4)];
    blurPixels(pixels,blurred,20,20,mask,1);
    assert.deepEqual([...pixels.slice(i,i+4)],transparent);
});

test('blur ignores transparent blurred edges and zero strength',()=>{
    const pixels=plain(10,10),blurred=plain(10,10,[0,0,0]),before=[...pixels],mask=circularHealingMask(5,5,4);
    blurPixels(pixels,blurred,10,10,mask,0);assert.deepEqual([...pixels],before);
    for(let i=3;i<blurred.length;i+=4)blurred[i]=80;
    blurPixels(pixels,blurred,10,10,mask,1);assert.deepEqual([...pixels],before);
});
