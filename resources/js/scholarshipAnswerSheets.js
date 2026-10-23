import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import { initScholarshipFilterCard } from './scholarshipFilterCard.js';
import { initStudentAnswerUploads } from './scholarshipAnswerUpload.js';
import { initTeacherKeys } from './scholarshipTeacherKeys.js';
import { initAnswerSheetListFullscreen } from './helpers/answerSheetListFullscreen.js';

const root=document.querySelector('[data-answer-sheets]');
if(root){
    const data=JSON.parse(document.getElementById('answerSheetData').textContent),get=s=>document.querySelector(s);
    const csrf=document.querySelector('meta[name=csrf-token]')?.content;
    const selected=()=>data.papers.find(p=>p.id===Number(get('#answerPaper').value));
    let busy=false;
    const api=async(path,body)=>{
        const response=await fetch(data.base+path,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,...(body&&!(body instanceof FormData)?{'Content-Type':'application/json'}:{})},body:body?(body instanceof FormData?body:JSON.stringify(body)):undefined});
        const result=await response.json();if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'The action could not be completed.');return result;
    };
    const run=async action=>{if(busy)return;busy=true;root.setAttribute('aria-busy','true');try{await action();}catch(e){await Swal.fire({icon:'error',title:'Answer Sheets',text:e.message});}finally{busy=false;root.setAttribute('aria-busy','false');}};
    const success=async text=>Swal.fire({icon:'success',title:'Saved',text});
    const reload=paper=>{const url=new URL(location.href);if(paper){url.searchParams.set('paper_id',paper);url.searchParams.delete('center');url.searchParams.delete('room');}url.searchParams.delete('scan_page');location.assign(url);};
    const modal=id=>Modal.getOrCreateInstance(get(id));
    get('[data-answer-clear-scans]')?.addEventListener('click',()=>run(async()=>{
        const paper=selected();if(!paper)return;
        const query=new URLSearchParams();for(const name of ['center','room']){const value=new URL(location.href).searchParams.get(name);if(value!==null&&value!=='')query.set(name,value);}
        const preview=await api(`/papers/${paper.id}/clear-scans-preview?${query}`);
        if(!preview.scans){await Swal.fire({icon:'info',title:'No Test Scans',text:'There are no scans for the selected paper, exam center and room.'});return;}
        const content=document.createElement('div');content.className='text-start';
        const scope=document.createElement('p');scope.textContent=preview.scope;scope.className='fw-semibold';content.append(scope);
        const counts=document.createElement('p');counts.textContent=`Delete ${preview.scans} scan(s): ${preview.pending} pending, ${preview.accepted} accepted, for ${preview.students} student(s). Remove ${preview.scores} scan-created score(s) and their scan images.`;content.append(counts);
        const keep=document.createElement('p');keep.textContent=`Keep student links, paper setup, answer keys and manual scores (${preview.manual_scores_kept} in these filters).`;content.append(keep);
        if(preview.reviews_reset||preview.awards_reset){const dependent=document.createElement('p');dependent.textContent=`Reset ${preview.reviews_reset} score review(s) and ${preview.awards_reset} award selection(s) affected by the removed scores.`;content.append(dependent);}
        const warning=document.createElement('p');warning.className='text-danger mb-0';warning.textContent='This cannot be undone. Use only to clear test data.';content.append(warning);
        const confirmation=await Swal.fire({icon:'warning',title:'Clear Test Scans?',html:content,showCancelButton:true,focusCancel:true,
            confirmButtonText:`Clear ${preview.scans} Test Scan(s)`,confirmButtonColor:'#d63939',cancelButtonText:'Cancel'});
        if(!confirmation.isConfirmed)return;
        const result=await api(`/papers/${paper.id}/clear-scans`,{confirmed:true,token:preview.token});
        await Swal.fire({icon:result.image_cleanup_pending?'warning':'success',title:'Test Scans Cleared',text:result.message});reload();
    }));
    const exitListFullscreen=initAnswerSheetListFullscreen(get('[data-answer-sheet-list]'));
    const form=get('[data-answer-filters]');
    form.querySelectorAll('select').forEach(select=>select.dataset.placeholder=select.options[0].textContent);
    initScholarshipFilterCard({card:get('[data-answer-filter-card]'),body:get('#answerFilters'),toggle:get('[data-answer-filter-toggle]'),selects:form.querySelectorAll('select'),label:'answer sheet filters'});
    form.addEventListener('change',event=>{
        if(event.target.id==='answerYear'){form.querySelectorAll('select:not(#answerYear)').forEach(s=>s.value='');}
        if(event.target.id==='answerPaper'){get('#answerCenter').value='';get('#answerRoom').value='';}
        if(event.target.id==='answerCenter')get('#answerRoom').value='';
        [...form.querySelectorAll('select')].forEach(s=>s.disabled=!s.value);form.submit();
    });
    const setup=get('[data-answer-setup-form]');
    let editingPaperId=null;
    const setupNotice=(paper,editing=false)=>{
        const warning=get('[data-answer-edit-warning]');
        warning.textContent=editing?'Saving setup changes clears this paper’s draft answer key and approvals. Review and approve a new key before printing.':'This paper already exists. Choose a different subject, grade or paper version to add a new paper. To change this paper, use Edit in the Answer Sheet List.';
        warning.classList.toggle('d-none',!paper||(editing&&!paper.revision&&!paper.has_draft));
    };
    const choicePreview=()=>{
        if(!setup)return;
        const count=Number(setup.elements.choice_count.value),letters=setup.elements.choice_style.value==='letters';
        get('[data-answer-choice-preview]').textContent=Number.isInteger(count)&&count>=2&&count<=26
            ?Array.from({length:count},(_,i)=>letters?String.fromCharCode(65+i):String(i+1)).join(', '):'Enter a choice count from 2 to 26.';
    };
    for(const name of ['choice_count','choice_style'])setup?.elements[name].addEventListener('input',choicePreview);
    get('[data-answer-setup]')?.addEventListener('click',()=>{
        editingPaperId=null;setup.reset();setup.elements.existing.disabled=false;get('#answerSetupTitle').textContent='Paper Setup';get('[data-answer-edit-warning]').classList.add('d-none');choicePreview();modal('#answerSetupModal').show();
    });
    setup?.elements.existing.addEventListener('change',()=>{
        const p=data.papers.find(p=>p.id===Number(setup.elements.existing.value));
        if(p)for(const [key,value]of Object.entries({subject_id:p.subject_id,grade:p.grade,paper_version:p.paper_version,question_count:p.question_count||20,points:p.points||5,choice_count:p.choices?.length||5,choice_style:p.choices?.[0]==='A'?'letters':'numbers'}))setup.elements[key].value=value;
        else setup.reset();
        setupNotice(p);
        choicePreview();
    });
    setup?.addEventListener('submit',event=>{event.preventDefault();run(async()=>{
        const payload=Object.fromEntries(new FormData(setup)),old=data.papers.find(p=>p.id===editingPaperId);
        const duplicate=data.papers.find(p=>p.id!==editingPaperId&&p.subject_id===Number(payload.subject_id)&&p.grade===Number(payload.grade)&&p.paper_version===payload.paper_version);
        if(duplicate){await Swal.fire({icon:'warning',title:'Paper Already Exists',text:`${duplicate.subject} · Grade ${duplicate.grade} · Paper ${duplicate.paper_version} already exists. Use Edit in the Answer Sheet List to change it, or choose a different subject, grade or paper version.`,confirmButtonText:'OK'});return;}
        const updating=Boolean(editingPaperId);
        if(updating&&old.linked)throw new Error('Student-linked paper setup is protected. Revise the Answer Key or create a new paper version.');
        if(updating&&(old.revision||old.has_draft)&&!(await Swal.fire({icon:'warning',title:'Update this answer sheet?',text:'Saving setup changes clears its draft answer key and approvals. A new key must be approved before printing.',showCancelButton:true,confirmButtonText:'Update Answer Sheet'})).isConfirmed)return;
        payload.version=updating?old.version:0;delete payload.existing;
        const result=await api(editingPaperId?`/papers/${editingPaperId}/update`:`/exams/${data.exam_id}/papers`,payload);await success('Paper configuration saved.');reload(result.id);
    });});
    root.addEventListener('click',async event=>{
        const button=event.target.closest('[data-answer-paper-view],[data-answer-paper-key],[data-answer-paper-link],[data-answer-paper-unlink],[data-answer-paper-edit],[data-answer-paper-delete]');if(!button||button.disabled||busy)return;
        if(!await exitListFullscreen()||busy)return;
        const id=Number(button.dataset.answerPaperView||button.dataset.answerPaperKey||button.dataset.answerPaperLink||button.dataset.answerPaperUnlink||button.dataset.answerPaperEdit||button.dataset.answerPaperDelete),paper=data.papers.find(p=>p.id===id);if(!paper)return;
        if(button.hasAttribute('data-answer-paper-link'))run(async()=>{
            if(!(await Swal.fire({icon:'question',title:'Link Students',text:`${paper.subject} · Grade ${paper.grade} · Paper ${paper.paper_version}. Link all Grade ${paper.grade} students within your permitted campuses, across all exam centers. Existing links are kept; only students without a link will be added.`,showCancelButton:true,confirmButtonText:'Link Students'})).isConfirmed)return;
            const result=await api(`/papers/${id}/link`,{version:paper.version});await success(result.message);reload(id);
        });
        else if(button.hasAttribute('data-answer-paper-unlink'))run(async()=>{
            if(!(await Swal.fire({icon:'warning',title:'Unlink Students',text:`${paper.subject} · Grade ${paper.grade} · Paper ${paper.paper_version}. Remove student links in your permitted campuses. Their old printed sheets will become invalid. After editing, link students and print new sheets. Links in other campuses remain protected.`,showCancelButton:true,confirmButtonText:'Unlink Students',confirmButtonColor:'#d63939'})).isConfirmed)return;
            const result=await api(`/papers/${id}/unlink`,{version:paper.version});await success(result.message);reload(id);
        });
        else if(button.hasAttribute('data-answer-paper-key')){
            openKey(paper);
        }else if(button.hasAttribute('data-answer-paper-view')){
            get('[data-answer-paper-view-heading]').textContent=`${paper.subject} · Grade ${paper.grade} · Paper ${paper.paper_version}`;
            const details=get('[data-answer-paper-view-details]');details.replaceChildren();
            for(const [label,value]of Object.entries({'Questions':paper.question_count??'Not configured','Choices':(paper.choices||[]).join(', '),'Maximum Score':paper.max_score,
                'Answer Key':paper.revision?`Approved · Revision ${paper.revision}`:(paper.has_draft?'Draft':'Not entered'),'Student Links':paper.linked?`${paper.linked_sheets} linked student(s) · Setup protected`:'Not linked'})){
                const dt=document.createElement('dt'),dd=document.createElement('dd');dt.className='col-sm-4';dd.className='col-sm-8';dt.textContent=label;dd.textContent=value;details.append(dt,dd);
            }
            const key=paper.approved||paper.answers,points=paper.approved?paper.approved_points:paper.question_points,rows=get('[data-answer-paper-view-rows]');rows.replaceChildren();get('[data-answer-paper-view-key]').hidden=!key;
            if(key){get('[data-answer-paper-view-key-title]').textContent=paper.approved?`Approved Answer Key · Revision ${paper.revision}`:'Draft Answer Key';key.forEach((answer,i)=>{const tr=document.createElement('tr');for(const value of [`Q${i+1}`,answer??'Blank',points?.[i]??'—']){const td=document.createElement('td');td.textContent=value;tr.append(td);}rows.append(tr);});}
            const url=new URL(location.href);url.searchParams.set('paper_id',id);for(const name of ['center','room','scan_page'])url.searchParams.delete(name);get('[data-answer-paper-use]').href=url;modal('#answerPaperViewModal').show();
        }else if(button.hasAttribute('data-answer-paper-edit')){
            setup.elements.existing.disabled=false;setup.elements.existing.value=id;setup.elements.existing.dispatchEvent(new Event('change'));setup.elements.existing.disabled=true;editingPaperId=id;
            setupNotice(paper,true);
            get('#answerSetupTitle').textContent=`Edit Answer Sheet · ${paper.subject} · Grade ${paper.grade}`;modal('#answerSetupModal').show();
        }else run(async()=>{
            if(paper.linked)throw new Error('This answer sheet is linked to students and cannot be deleted.');
            if(!(await Swal.fire({icon:'warning',title:'Delete this answer sheet?',text:`${paper.subject} · Grade ${paper.grade} · Paper ${paper.paper_version}. Its unlinked draft key and approvals will also be deleted.`,showCancelButton:true,confirmButtonText:'Delete Answer Sheet',confirmButtonColor:'#d63939'})).isConfirmed)return;
            const result=await api(`/papers/${id}/delete`,{version:paper.version});await success(result.message);
            const url=new URL(location.href);if(selected()?.id===id)for(const name of ['paper_id','center','room'])url.searchParams.delete(name);url.searchParams.delete('scan_page');location.assign(url);
        });
    });
    const makeResponses=(container,choices,answers,flags={},disabled=false)=>{
        container.replaceChildren();
        answers.forEach((answer,i)=>{
            const cell=document.createElement('div'),label=document.createElement('label'),select=document.createElement('select');
            select.id=`${container.dataset.answerKeyGrid!==undefined?'key':'response'}-${i+1}`;label.htmlFor=select.id;
            label.textContent=`Q${i+1}${flags[i+1]?' · '+flags[i+1]:''}`;cell.classList.toggle('answer-flagged',Boolean(flags[i+1]));
            select.className='form-select';select.dataset.question=i;select.disabled=disabled;
            select.add(new Option('Blank',''));choices.forEach(c=>select.add(new Option(c,c)));select.value=answer??'';
            cell.append(label,select);container.append(cell);
        });
    };
    const answers=container=>[...container.querySelectorAll('select')].map(s=>s.value||null);
    const keyGrid=get('[data-answer-key-grid]');
    let keyPaperId=null;
    let keyValidationShown=false;
    const keyPaper=()=>data.papers.find(p=>p.id===keyPaperId)||selected();
    const validateKeyAnswers=()=>{
        const missing=[];
        keyGrid.querySelectorAll('select[data-question]').forEach(select=>{
            const number=Number(select.dataset.question)+1,blank=!select.value,feedback=get(`#answerKeyError${number}`);
            select.classList.toggle('is-invalid',blank);select.closest('td').toggleAttribute('data-answer-key-invalid',blank);feedback.hidden=!blank;
            if(blank){missing.push(`Q${number}`);select.setAttribute('aria-invalid','true');select.setAttribute('aria-describedby',feedback.id);}
            else{select.removeAttribute('aria-invalid');select.removeAttribute('aria-describedby');}
        });
        const alert=get('[data-answer-key-errors]');alert.hidden=!missing.length;
        alert.textContent=missing.length?`Blank answers: ${missing.join(', ')}. Select an answer for every question before approval.`:'';
        return missing;
    };
    const focusBlankKeyAnswer=()=>{
        const select=keyGrid.querySelector('select[aria-invalid="true"]:not(:disabled)');
        select?.focus({preventScroll:true});select?.closest('tr').scrollIntoView({block:'center'});
    };
    keyGrid.addEventListener('change',event=>{if(keyValidationShown&&event.target.matches('select[data-question]'))validateKeyAnswers();});
    const questionPoints=()=>[...keyGrid.querySelectorAll('[data-question-score]')].map(input=>input.value===''?null:Number(input.value));
    const updateKeyTotal=()=>{
        const p=keyPaper(),values=questionPoints(),total=values.reduce((sum,v)=>sum+(v||0),0),target=get('[data-answer-key-total]');
        const valid=values.every(v=>Number.isFinite(v)&&v>0)&&Math.abs(Math.round(total*100)/100-p.max_score)<.001;
        target.textContent=`Question scores total: ${Number(total.toFixed(4))} / ${p.max_score}${valid?' · Matches subject maximum':' · Must match before approval'}`;
        target.classList.toggle('text-success',valid);target.classList.toggle('text-danger',!valid);
    };
    keyGrid.addEventListener('input',updateKeyTotal);
    const openKey=p=>{
        keyPaperId=p.id;
        get('[data-teacher-key-import-note]').hidden=true;get('[data-teacher-key-import-preview]').hidden=true;get('[data-teacher-key-import-image]').removeAttribute('src');
        keyValidationShown=false;get('[data-answer-key-errors]').hidden=true;get('[data-answer-key-errors]').textContent='';
        const values=p.answers||Array(p.question_count||0).fill(null),scores=p.question_points||Array(values.length).fill(p.points),disabled=!data.permissions['answer-key'];
        keyGrid.replaceChildren();
        values.forEach((answer,i)=>{
            const row=document.createElement('tr'),number=document.createElement('td'),answerCell=document.createElement('td'),scoreCell=document.createElement('td');
            number.textContent=`Q${i+1}`;
            const select=document.createElement('select');select.className='form-select';select.dataset.question=i;select.setAttribute('aria-label',`Correct answer for question ${i+1}`);select.disabled=disabled;
            select.add(new Option('Blank',''));p.choices.forEach(c=>select.add(new Option(c,c)));select.value=answer??'';
            const feedback=document.createElement('div');feedback.id=`answerKeyError${i+1}`;feedback.className='answer-key-field-error';feedback.hidden=true;feedback.textContent=`Q${i+1}: Answer key is required.`;
            const score=document.createElement('input');score.type='number';score.className='form-control';score.min='0.0001';score.max=p.max_score;score.step='0.0001';score.dataset.questionScore=i;score.setAttribute('list','answerQuestionScoreOptions');score.setAttribute('aria-label',`Score for question ${i+1}`);score.disabled=disabled;score.value=scores[i]??'';
            answerCell.append(select,feedback);scoreCell.append(score);row.append(number,answerCell,scoreCell);keyGrid.append(row);
        });
        get('[data-answer-key-subject]').textContent=p.subject;
        get('[data-answer-key-status]').textContent=`· Grade ${p.grade} · Paper ${p.paper_version} · ${p.question_count} questions · ${p.revision?'Approved revision '+p.revision:'Not approved'}`;
        get('[data-answer-score-controls]').hidden=disabled;updateKeyTotal();modal('#answerKeyModal').show();
        get('#answerKeyModal').querySelectorAll('[data-teacher-key-print],[data-teacher-key-open]').forEach(button=>button.disabled=!!data.locked||!p.question_count);
    };
    get('[data-answer-key]')?.addEventListener('click',()=>openKey(selected()));
    get('#answerBulkScore').addEventListener('change',()=>get('[data-answer-custom-score]').hidden=get('#answerBulkScore').value!=='custom');
    get('[data-answer-fill-scores]').addEventListener('click',()=>run(async()=>{
        const value=get('#answerBulkScore').value==='custom'?Number(get('#answerCustomScore').value):Number(get('#answerBulkScore').value);
        if(!Number.isFinite(value)||value<=0||value>keyPaper().max_score)throw new Error('Enter a positive question score within the subject maximum.');
        keyGrid.querySelectorAll('[data-question-score]').forEach(input=>input.value=value);updateKeyTotal();
    }));
    get('[data-answer-key-save]')?.addEventListener('click',()=>run(async()=>{
        if([...keyGrid.querySelectorAll('[data-question-score]')].some(input=>!input.checkValidity()))throw new Error('Enter valid question scores with up to four decimal places.');
        const p=keyPaper(),points=questionPoints();await api(`/papers/${p.id}/key`,{answers:answers(keyGrid),points,version:p.version});p.version++;p.answers=answers(keyGrid);p.question_points=points;await success('Draft answers and question scores saved. Review them, then approve.');
    }));
    get('[data-answer-key-approve]')?.addEventListener('click',()=>run(async()=>{
        keyValidationShown=true;const missing=validateKeyAnswers();
        if(missing.length){
            await Swal.fire({icon:'error',title:'Incomplete Answer Key',text:`Please select answers for: ${missing.join(', ')}.`,confirmButtonText:'OK',returnFocus:false,
                didClose:()=>{validateKeyAnswers();focusBlankKeyAnswer();}});
            validateKeyAnswers();focusBlankKeyAnswer();return;
        }
        const p=keyPaper();if(JSON.stringify(answers(keyGrid))!==JSON.stringify(p.answers)||JSON.stringify(questionPoints())!==JSON.stringify(p.question_points))throw new Error('Save the draft before approving these answers and question scores.');
        if(!(await Swal.fire({icon:'question',title:'Approve this answer key?',text:'A corrected key requires affected scans to be explicitly rechecked.',showCancelButton:true,confirmButtonText:'Approve Key'})).isConfirmed)return;
        await api(`/papers/${p.id}/approve`,{version:p.version});await success('Answer key approved.');reload(p.id);
    }));
    initTeacherKeys({root,api,run,selected,keyPaper,onDetected:(paper,result,preview)=>{
        if(paper.id!==keyPaper().id||paper.version!==result.version)throw new Error('The draft key changed. Refresh and scan this key again.');
        const fields=[...keyGrid.querySelectorAll('select[data-question]')];
        if(fields.length!==result.answers.length)throw new Error('The scanned question count does not match this paper.');
        fields.forEach((field,i)=>{field.value=result.answers[i]??'';const flag=result.flags[i+1],feedback=get(`#answerKeyError${i+1}`);
            feedback.textContent=`Q${i+1}: ${flag==='multiple'?'Multiple bubbles detected. Select the correct answer.':flag==='unclear'?'Unclear mark. Select the correct answer.':'Answer key is required.'}`;});
        keyValidationShown=true;validateKeyAnswers();
        const note=get('[data-teacher-key-import-note]');note.textContent='Teacher answers imported into the editor. Review the flagged fields, then Save Draft and Approve Key. Question scores have been kept.';note.hidden=false;
        get('[data-teacher-key-import-image]').src=preview;get('[data-teacher-key-import-preview]').hidden=false;
    }});
    const printForm=get('[data-answer-print-form]');
    const printOptions=()=>{
        const center=printForm.elements.center.value,candidates=data.candidates.filter(c=>center===''||c.center_id===Number(center));
        printForm.elements.rooms.replaceChildren(...[...new Set(candidates.map(c=>c.room))].sort((a,b)=>a-b).map(r=>new Option('Room '+r,r)));
        printForm.elements.candidates.replaceChildren(...candidates.map(c=>new Option(`${c.center} · Room ${c.room} · Seat ${c.seat} · ${c.name}`,c.id)));
        get('[data-answer-print-rooms]').hidden=printForm.elements.scope.value!=='rooms';get('[data-answer-print-students]').hidden=printForm.elements.scope.value!=='students';
    };
    printForm?.addEventListener('change',event=>{if(['scope','center'].includes(event.target.name))printOptions();});
    get('[data-answer-print]')?.addEventListener('click',()=>{printOptions();modal('#answerPrintModal').show();});
    printForm?.addEventListener('submit',event=>{event.preventDefault();if(busy)return;const popup=window.open('about:blank','_blank');run(async()=>{
        try{
        const payload={scope:printForm.elements.scope.value,rooms:[...printForm.elements.rooms.selectedOptions].map(o=>Number(o.value)),candidates:[...printForm.elements.candidates.selectedOptions].map(o=>Number(o.value))};
        if(printForm.elements.center.value!=='')payload.center=Number(printForm.elements.center.value);
        const result=await api(`/papers/${selected().id}/issue`,payload);modal('#answerPrintModal').hide();
        if(popup){popup.opener=null;popup.location.assign(result.url);}
        else{const link=document.createElement('a');link.href=result.url;link.target='_blank';link.rel='noopener';link.textContent='Open A4 Print Preview';await Swal.fire({title:'Print preview ready',html:link});}
        }catch(e){popup?.close();throw e;}
    });});
    initStudentAnswerUploads({root,data,api,run,isBusy:()=>busy,reload,success});
    let review,reviewZoom=1;
    const reviewForm=get('[data-answer-review-form]'),responseGrid=get('[data-answer-response-grid]');
    const openReview=async id=>{
        review=await api(`/scans/${id}`);reviewForm.reset();reviewZoom=1;get('[data-answer-review-image]').style.width='100%';
        get('[data-answer-review-image]').src=review.image;get('[data-answer-original]').href=review.original;
        get('[data-answer-review-heading]').textContent=`${review.name} · ${review.code} · ${review.subject} · Key revision ${review.key_revision} (current: ${review.latest_revision}) · ${review.status}${review.existing_score!==null?' · Existing score: '+review.existing_score:''}`;
        makeResponses(responseGrid,review.choices,review.responses,review.flags,review.status!=='pending');
        get('[data-answer-verify]').disabled=review.status!=='pending'||review.key_revision!==review.latest_revision||review.candidate_version!==review.current_version;
        modal('#answerReviewModal').show();
    };
    root.addEventListener('click',event=>{const button=event.target.closest('[data-answer-review]');if(button)run(()=>openReview(button.dataset.answerReview));});
    document.querySelectorAll('[data-answer-image-zoom]').forEach(button=>button.addEventListener('click',()=>{reviewZoom=Math.min(3,Math.max(1,reviewZoom+Number(button.dataset.answerImageZoom)));get('[data-answer-review-image]').style.width=`${reviewZoom*100}%`;}));
    reviewForm.addEventListener('submit',event=>{event.preventDefault();run(async()=>{
        const result=await api(`/scans/${review.id}/verify`,{responses:answers(responseGrid),version:review.candidate_version,replace:reviewForm.elements.replace.checked,reason:reviewForm.elements.reason.value,confirmed:reviewForm.elements.confirmed.checked});
        await success(result.message);reload();
    });});
    get('[data-answer-recheck]')?.addEventListener('click',()=>run(async()=>{
        const reason=reviewForm.elements.reason.value.trim();if(reason.length<5)throw new Error('Enter a reason of at least five characters for this recheck.');
        const result=await api(`/scans/${review.id}/recheck`,{reason});await openReview(result.id);
    }));
}
