import test from 'node:test';
import assert from 'node:assert/strict';
import { perspectiveTransform, validCorners, findSheetCorners, sheetMarkers } from '../../resources/js/helpers/answerSheetAlignment.js';

test('perspective mapping preserves a photographed page with skewed corners',()=>{
    const photograph=[[83,120],[880,75],[930,1260],[45,1320]];
    const transform=perspectiveTransform(sheetMarkers,photograph);
    sheetMarkers.forEach((point,i)=>transform(...point).forEach((v,j)=>assert.ok(Math.abs(v-photograph[i][j])<1e-6)));
    const inverse=perspectiveTransform(photograph,sheetMarkers),mapped=transform(500,700);
    inverse(...mapped).forEach((v,j)=>assert.ok(Math.abs(v-[500,700][j])<1e-6));
});
test('rejects overlapping reversed and tiny corner selections',()=>{
    assert.equal(validCorners(sheetMarkers,1000,1414),true);
    assert.equal(validCorners([...sheetMarkers].reverse(),1000,1414),false);
    assert.equal(validCorners([[0,0],[0,0],[100,100],[0,100]],1000,1414),false);
    assert.equal(validCorners([[0,0],[10,0],[10,10],[0,10]],1000,1414),false);
    assert.throws(()=>perspectiveTransform([[0,0],[0,0],[1,1],[0,1]],sheetMarkers));
});
test('finds four filled squares rather than printed text components',()=>{
    const width=500,height=707,pixels=new Uint8ClampedArray(width*height*4).fill(255);
    const square=(x,y,w,h)=>{for(let row=y;row<y+h;row++)for(let col=x;col<x+w;col++){const i=(row*width+col)*4;pixels[i]=pixels[i+1]=pixels[i+2]=0;}};
    for(const [x,y]of [[17,17],[482,17],[482,689],[17,689]])square(x-5,y-5,10,10);
    square(100,80,70,3);square(150,100,2,14);
    const corners=findSheetCorners(pixels,width,height);
    assert.equal(corners.length,4);
    assert.deepEqual(corners,[[16.5,16.5],[481.5,16.5],[481.5,688.5],[16.5,688.5]]);
});
