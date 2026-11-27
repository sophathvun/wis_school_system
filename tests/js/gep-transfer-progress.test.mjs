import test from 'node:test';
import assert from 'node:assert/strict';
import {readGepTransferProgress} from '../../resources/js/helpers/gep-transfer-progress.js';
const response = text => new Response(text, {headers:{'Content-Type':'application/x-ndjson'}});
test('reports real counts and requires a committed completion message', async () => {
    const seen=[];
    const complete=await readGepTransferProgress(response('{"event":"progress","processed":1,"total":2,"percent":50}\n{"event":"complete","result":{"applied":true,"rows":2}}\n'),message=>seen.push(message));
    assert.equal(seen[0].percent,50);assert.equal(complete.result.applied,true);
    await assert.rejects(readGepTransferProgress(response('{"event":"progress","percent":100}\n'),()=>{}),/before confirmation/);
});
test('does not show success on validation failure',async()=>{
    await assert.rejects(readGepTransferProgress(response('{"event":"error","message":"Invalid row. No records saved."}\n'),()=>{}),/Invalid row/);
});
