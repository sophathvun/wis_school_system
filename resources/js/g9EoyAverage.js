import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {setupG9PhotoViewer} from './helpers/g9PhotoViewer.js';
import {showSuccess,showWarning} from './helpers/sweet-alert2.js';

const root = document.querySelector('[data-g9-eoy]');
if (root) {
    const viewPhoto = setupG9PhotoViewer();
    const filters = root.querySelector('form'), rows = root.querySelector('[data-eoy-rows]'), error = root.querySelector('[data-eoy-error]');
    const status = root.querySelector('[data-eoy-status]'), toggle = root.querySelector('[data-eoy-filter-toggle]'), mobile = matchMedia('(max-width:767.98px)');
    const editButton=root.querySelector('[data-eoy-edit]');
    editButton.dataset.actionNormalized='true';
    let editLabel=editButton.querySelector('[data-eoy-edit-label]'),editIcon=editButton.querySelector('i');
    if(!editLabel||!editIcon){
        editIcon=document.createElement('i');editIcon.setAttribute('aria-hidden','true');
        editLabel=document.createElement('span');editLabel.setAttribute('data-eoy-edit-label','');
        editButton.replaceChildren(editIcon,editLabel);
    }
    const combos = new Map(), dirtyRows = new Set(), cancelPending = new Set(), rowEditors=new Set();
    let requestId = 0, saving = 0, editing=false, finishing=false, loading=true;
    const syncEditButton=()=>{
        editButton.hidden=![...rowEditors].some(editor=>editor.canAssign);
        editButton.disabled=loading||finishing||saving>0;
        editLabel.textContent=editing?'Lock':'Edit';
        editIcon.className=finishing?'ti ti-loader-2':editing?'ti ti-lock':'ti ti-pencil';
        editButton.title=editing?'Finish editing and lock scores':'Unlock scores for editing';
        editButton.setAttribute('aria-label',editing?'Lock scores':'Edit scores');
        editButton.setAttribute('aria-pressed',String(editing));
    };
    editButton.addEventListener('pointerdown',event=>event.preventDefault());
    editButton.addEventListener('click',async()=>{
        if(loading||finishing||saving)return;
        if(!editing){editing=true;rowEditors.forEach(editor=>editor.refresh());syncEditButton();showSuccess('Editing enabled','You can edit scores now. Changes save automatically.');return;}
        finishing=true;syncEditButton();
        try{
            const results=await Promise.allSettled([...rowEditors].map(editor=>editor.save()));
            if(results.some(result=>result.status==='rejected'||result.value===false)||dirtyRows.size){
                error.textContent='Some scores are not saved. Check the highlighted rows and retry.';return;
            }
            editing=false;error.textContent='';cancelPending.forEach(cancel=>cancel());rowEditors.forEach(editor=>editor.refresh());
            showSuccess('Editing finished','Scores are locked. Click Edit to make changes.');
        }finally{finishing=false;syncEditButton();}
    });
    const api = async (path, body) => {
        const response = await fetch(root.dataset.base + path, {method:body ? 'POST' : 'GET',credentials:'same-origin',headers:{Accept:'application/json',...(body ? {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content} : {})},body:body ? JSON.stringify(body) : undefined});
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'EOY scores unavailable.');
        return result;
    };
    const sync = () => combos.forEach(combo => combo.sync());
    filters.querySelectorAll('select').forEach(select => {
        [...select.options].forEach(option => option.dataset.en = option.textContent);
        const combo = setupStaffLocationSelect(filters,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select.getAttribute('aria-label')}); combos.set(select,combo);
        const menu = select.nextElementSibling.querySelector('.location-combobox-menu'), search = menu.querySelector('input'); menu.classList.add('g9-center-menu');
        const heading = document.createElement('div'); heading.className = 'g9-center-menu-heading'; heading.textContent = select.getAttribute('aria-label'); menu.prepend(heading);
        const box = document.createElement('div'), icon = document.createElement('i'); box.className = 'g9-center-search'; icon.className = 'ti ti-search'; icon.setAttribute('aria-hidden','true'); search.before(box);box.append(icon,search);
        const position = () => {const rect = select.nextElementSibling.getBoundingClientRect(), width = Math.min(520,document.documentElement.clientWidth-24);menu.style.width=width+'px';menu.style.left=(Math.max(12,Math.min(rect.left,document.documentElement.clientWidth-width-12))-rect.left)+'px';};
        combo.toggle.addEventListener('click',position);window.addEventListener('resize',position);
    });
    const expand = value => {filters.hidden=!value;toggle.setAttribute('aria-expanded',String(value));toggle.setAttribute('aria-label',value?'Collapse filters':'Expand filters');toggle.querySelector('i').className='ti ti-chevron-'+(value?'up':'down');if(!value)combos.forEach(c=>c.close());};
    expand(!mobile.matches);mobile.addEventListener('change',()=>expand(!mobile.matches));toggle.addEventListener('click',()=>expand(toggle.getAttribute('aria-expanded')!=='true'));
    filters.addEventListener('submit',event=>event.preventDefault());sync();
    const fill = (name,values) => {
        const select=filters.elements[name],selected=select.value;
        select.replaceChildren(new Option('All '+select.getAttribute('aria-label').toLowerCase(),''),...values.map(value=>new Option(typeof value==='object'?value.label:value,typeof value==='object'?value.id:value)));
        if([...select.options].some(option=>option.value===selected))select.value=selected;
        [...select.options].forEach(option=>option.dataset.en=option.textContent);
    };
    const params = () => new URLSearchParams([...new FormData(filters)].filter(([,value])=>value!==''));
    let acceptedFilters = Object.fromEntries(new FormData(filters));
    root.querySelectorAll('[data-eoy-report]').forEach(button=>button.addEventListener('click',async()=>{
        error.textContent='';
        if(!filters.elements.academic_year.value){showWarning('Select an academic year','Choose the academic year before creating a report.');return;}
        if(saving||finishing||dirtyRows.size){showWarning('Scores are not saved yet','Wait for autosave or resolve the failed rows before creating a report.');return;}
        const type=button.dataset.eoyReport,format=button.dataset.format,url=root.dataset.base+'/reports/'+type+'/'+format+'?'+params();
        if(format==='print'){window.open(url,'_blank','noopener');return;}
        button.disabled=true;
        try{
            const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
            if(!response.ok){const result=await response.json();throw Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Report export failed.');}
            const blob=await response.blob(),href=URL.createObjectURL(blob),link=document.createElement('a');
            link.href=href;link.download='g9-eoy-'+type+'.pdf';document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(href),1000);
        }catch(failure){error.textContent=failure.message;}
        finally{button.disabled=false;}
    }));
    const canLeave = () => !dirtyRows.size || confirm('Discard unsaved semester score changes?');
    const line = (cell,text,className='') => {const element=document.createElement('div');element.className=className;element.textContent=text||'-';cell.append(element);return element;};
    const empty = text => {rows.replaceChildren();const cell=rows.insertRow().insertCell();cell.colSpan=8;cell.className='text-center text-secondary py-5';cell.textContent=text;};
    const semester = value => (Math.floor((Math.round(Number(value)*1000)+5)/10)/100).toFixed(2);
    const total = (a,b) => {
        if(a===''||b===''||!Number.isFinite(Number(a))||!Number.isFinite(Number(b)))return null;
        return String(Math.floor((Math.round(Number(semester(a))*100)+Math.round(Number(semester(b))*100)+50)/100));
    };
    const render = (record,index) => {
        const row=rows.insertRow(),cells=Array.from({length:8},()=>row.insertCell());
        line(cells[0],String(index),'badge bg-blue-lt g9-number-badge');
        if(record.photo_url){
            const trigger=document.createElement('button'),image=document.createElement('img');
            trigger.type='button';trigger.className='g9-photo-trigger';trigger.setAttribute('aria-label',`View photo of ${record.name_en||record.student_code}`);
            image.className='g9-student-photo';image.src=record.photo_url;image.alt=record.name_en;image.loading='lazy';
            trigger.append(image);trigger.addEventListener('click',()=>viewPhoto(record));cells[1].append(trigger);
        }else line(cells[1],'-');
        line(cells[2],record.name_kh,'fw-semibold');line(cells[2],record.name_en);line(cells[2],record.student_code+' · '+[record.campus,record.grade].filter(Boolean).join('-'),'small text-secondary');
        line(cells[3],record.exam_center,'g9-eoy-center');
        line(cells[3],record.exam_date?record.exam_date.split('-').reverse().join('-'):'-','small text-secondary');
        const badges=document.createElement('div');badges.className='d-flex gap-2 mt-1 flex-wrap';
        [['Room',record.room,'bg-blue-lt'],['Seat',record.seat,'bg-purple-lt']].forEach(([label,value,color])=>{const item=document.createElement('span'),badge=document.createElement('span');item.className='small';item.append(document.createTextNode(label+': '));badge.className='badge '+color;badge.textContent=value||'-';item.append(badge);badges.append(item);});cells[3].append(badges);
        const inputs=[4,5].map((column,i)=>{
            const input=document.createElement('input');input.type='number';input.className='form-control g9-eoy-score';input.min='0';input.max='10000';input.step='.001';input.inputMode='decimal';
            input.value=record[i===0?'semester_1':'semester_2']??'';input.readOnly=true;input.disabled=!record.can_assign;input.setAttribute('aria-label',`SEM${i+1} score for ${record.name_en}`);cells[column].append(input);return input;
        });
        const value=line(cells[6],record.calculated_eoy||'—','g9-eoy-total'),note=line(cells[6],'','small text-secondary');
        const retry=document.createElement('button');retry.type='button';retry.className='btn btn-sm btn-outline-danger ms-1';retry.textContent='Retry';retry.hidden=true;cells[7].append(retry);
        const rowStatus=document.createElement('div');rowStatus.className='small mt-2';rowStatus.setAttribute('role','status');cells[7].append(rowStatus);
        if(!record.can_assign)line(cells[7],'View only','small text-secondary');
        let baseline=inputs.map(input=>input.value),saved=record.saved_eoy,normalized=record.scores_normalized;
        let rowSaving=false,timer=null,failed=false;
        const cancelTimer=()=>{clearTimeout(timer);timer=null;};cancelPending.add(cancelTimer);
        const rowMessage=(message,color='secondary')=>{rowStatus.textContent=message;rowStatus.className=`small text-${color} mt-2`;};
        const changed=()=>inputs.some((input,i)=>input.value!==baseline[i]);
        const refresh = () => {
            const calculated=total(inputs[0].value,inputs[1].value);
            const stored=saved!==null&&saved!==undefined&&saved!==''&&Number.isFinite(Number(saved))?String(Math.round(Number(saved))):null;
            value.textContent=calculated??stored??'—';
            changed()?dirtyRows.add(record.id):dirtyRows.delete(record.id);
            const assigned=normalized&&saved!==null&&saved!==''&&calculated!==null&&Number(saved)===Number(calculated)&&!changed();
            note.textContent=calculated===null?(stored!==null?'Saved EOY · semester scores incomplete':'Both scores required'):assigned?'Assigned':'Calculated · not saved';
            inputs.forEach(input=>{input.readOnly=!editing||rowSaving;input.disabled=!record.can_assign;});
            retry.hidden=!failed;retry.disabled=rowSaving;
        };
        const save = async () => {
            cancelTimer();
            if(!record.can_assign||!editing||rowSaving||!row.isConnected||!changed())return !changed();
            if(inputs.some(input=>!input.checkValidity())){rowMessage('Enter a score from 0 to 10000 with up to 3 decimal places.','danger');return false;}
            inputs.forEach(input=>{if(input.value!=='')input.value=semester(input.value);});refresh();
            if(!changed()){rowMessage('No changes to save.');return true;}
            rowSaving=true;saving++;failed=false;combos.forEach(combo=>combo.toggle.disabled=true);rowMessage('Saving...');refresh();syncEditButton();
            try{
                const result=await api('/'+record.id,{version:record.version,semester_1:inputs[0].value||null,semester_2:inputs[1].value||null});
                record.version=result.version;saved=result.eoy_average;normalized=true;inputs.forEach((input,i)=>input.value=result[i===0?'semester_1':'semester_2']??'');baseline=inputs.map(input=>input.value);dirtyRows.delete(record.id);rowMessage('Saved automatically','success');status.textContent='Saved scores for '+record.name_en+'.';showSuccess('Scores saved');return true;
            }catch(failure){failed=true;rowMessage(failure.message+' Changes are not saved.','danger');return false;}
            finally{rowSaving=false;saving--;combos.forEach(combo=>combo.toggle.disabled=saving>0);refresh();syncEditButton();}
        };
        inputs.forEach(input=>{
            input.addEventListener('input',()=>{
                if(!editing)return;cancelTimer();failed=false;refresh();
                if(changed()){rowMessage('Unsaved changes...');timer=setTimeout(save,700);}else rowMessage('');
            });
            input.addEventListener('blur',()=>{if(editing&&!rowSaving)save();});
        });
        retry.addEventListener('click',save);
        rowEditors.add({canAssign:record.can_assign,save,refresh});
        refresh();
    };
    const load = async (page=1,options=false) => {
        const current=++requestId;error.textContent='';status.textContent='';
        try{
            cancelPending.forEach(cancel=>cancel());cancelPending.clear();
            editing=false;rowEditors.clear();loading=true;syncEditButton();dirtyRows.clear();
            if(!filters.elements.academic_year.value){['campus','grade','student'].forEach(name=>fill(name,[]));sync();empty('Select an academic year to view candidates.');document.getElementById('g9-eoy-pagination').replaceChildren();acceptedFilters=Object.fromEntries(new FormData(filters));return;}
            empty('Loading candidates…');
            if(options){const choices=await api('/options?'+params());if(current!==requestId)return;['campus','grade','student'].forEach(name=>fill(name,choices[name]));sync();}
            acceptedFilters=Object.fromEntries(new FormData(filters));
            const query=params();query.set('page',page);query.set('per_page',document.getElementById('g9-eoy-per-page').value);
            const result=await api('/list?'+query);if(current!==requestId)return;
            rows.replaceChildren();result.data.forEach((record,i)=>render(record,result.from+i));if(!result.data.length)empty('No candidates match the selected filters.');
            renderPagination(result,'g9-eoy-pagination','g9-eoy-per-page',next=>{if(saving||finishing||!canLeave())return;load(next);},['all',15,25,50,75,100]);
        }catch(failure){if(current===requestId){empty('Unable to load candidates.');error.textContent=failure.message;document.getElementById('g9-eoy-pagination').replaceChildren();}}
        finally{if(current===requestId){loading=false;syncEditButton();}}
    };
    filters.addEventListener('change',event=>{
        if(saving||finishing||!canLeave()){Object.entries(acceptedFilters).forEach(([name,value])=>filters.elements[name].value=value);sync();return;}
        const order=['academic_year','campus','grade','student'],index=order.indexOf(event.target.name);if(index<0)return;
        order.slice(index+1).forEach(name=>filters.elements[name].value='');load(1,true);
    });
    window.addEventListener('beforeunload',event=>{if(dirtyRows.size||saving){event.preventDefault();event.returnValue='';}});
    load();
}
