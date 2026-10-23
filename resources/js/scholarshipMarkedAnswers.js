import { Modal } from 'bootstrap';

export function initMarkedAnswers({root,get,api,run}){
    const dialog=get('#answerMarkedModal');if(!dialog)return;
    const stage=get('[data-answer-marked-stage]'),overlay=get('[data-answer-marked-overlay]'),image=get('[data-answer-marked-image]');
    const labels={correct:'Correct',incorrect:'Incorrect',unanswered:'Unanswered',multiple:'Multiple marks',unclear:'Unclear mark'};
    let zoom=1;
    const setZoom=value=>{zoom=Math.max(1,Math.min(4,value));stage.style.width=`${zoom*100}%`;get('[data-answer-marked-zoom-label]').textContent=`${Math.round(zoom*100)}%`;};
    dialog.addEventListener('click',event=>{const button=event.target.closest('[data-answer-marked-zoom]');if(button)setZoom(button.dataset.answerMarkedZoom==='reset'?1:zoom+Number(button.dataset.answerMarkedZoom));});
    get('[data-answer-marked-toggle]').addEventListener('change',event=>overlay.hidden=!event.target.checked);
    root.addEventListener('click',event=>{
        const button=event.target.closest('[data-answer-marked]');if(!button)return;
        run(async()=>{
            const scan=await api(`/scans/${button.dataset.answerMarked}/marked`);
            get('[data-answer-marked-heading]').textContent=`${scan.name} · ${scan.code} · ${scan.subject} · Grade ${scan.grade} · Paper ${scan.paper}`;
            get('[data-answer-marked-note]').textContent=`${scan.status==='accepted'?'Verified responses':'Detected answers — awaiting verification'} · Key revision ${scan.key_revision}${scan.latest_revision!==scan.key_revision?` (latest: ${scan.latest_revision}; this view keeps revision ${scan.key_revision})`:''}. Borders show the answers used for this scan. Staff corrections are noted in the table.`;
            get('[data-answer-marked-summary]').textContent=`${scan.totals.correct} correct · ${scan.totals.incorrect} incorrect · ${scan.totals.unanswered} unanswered · ${scan.totals.multiple} multiple · ${scan.totals.unclear} unclear · ${scan.status==='accepted'?'Score':'Provisional score'}: ${scan.total} / ${scan.maximum}`;
            const body=get('[data-answer-marked-rows]');body.replaceChildren();overlay.replaceChildren();overlay.setAttribute('viewBox',`0 0 ${scan.width} ${scan.height}`);
            for(const row of scan.rows){
                const tr=document.createElement('tr');tr.dataset.answerMarkedStatus=row.status;
                for(const value of [`Q${row.number}`,row.answer??'Blank',row.correct,labels[row.status],`${row.earned} / ${row.points}`]){const td=document.createElement('td');td.textContent=value;tr.append(td);}
                tr.children[1].classList.add(row.status==='correct'?'answer-marked-correct':row.status==='incorrect'?'answer-marked-incorrect':'answer-marked-warning');
                if(row.corrected){const note=document.createElement('small');note.className='d-block text-secondary';note.textContent=`Verified correction; detected: ${row.detected??'Blank'}${row.flag?` (${row.flag})`:''}`;tr.children[1].append(note);}
                body.append(tr);
                for(const border of row.borders){
                    const circle=document.createElementNS('http://www.w3.org/2000/svg','circle');
                    for(const [name,value]of Object.entries({cx:border.x,cy:border.y,r:border.radius+border.offset,'class':`answer-marked-${border.state}`,'data-question':row.number,'data-choice':border.label}))circle.setAttribute(name,value);
                    const title=document.createElementNS('http://www.w3.org/2000/svg','title');title.textContent=`Q${row.number}: ${border.label} — ${border.state==='correct'?'correct answer':border.state==='incorrect'?'incorrect response':'ambiguous mark'}`;circle.append(title);overlay.append(circle);
                }
            }
            setZoom(1);overlay.hidden=false;get('[data-answer-marked-toggle]').checked=true;image.src=scan.image;
            image.hidden=false;get('[data-answer-marked-image-error]').hidden=true;
            get('[data-answer-marked-viewport]').scrollTo(0,0);Modal.getOrCreateInstance(dialog).show();
        });
    });
    image.addEventListener('error',()=>{image.hidden=true;overlay.hidden=true;get('[data-answer-marked-image-error]').hidden=false;});
    dialog.addEventListener('hidden.bs.modal',()=>{image.removeAttribute('src');image.hidden=true;overlay.replaceChildren();});
}
