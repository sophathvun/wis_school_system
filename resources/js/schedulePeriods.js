import {Modal} from 'bootstrap';
import Swal from 'sweetalert2';
import {enhanceSubjectTemporal} from './scholarshipTemporalInputs.js';

const root=document.querySelector('[data-schedule-periods]');
if(root){
    const get=selector=>root.querySelector(selector),form=get('[data-period-editor]');
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let periods=[],busy=false;
    const time=raw=>{
        const [hour,minute]=raw.split(':');return `${Number(hour)%12||12}:${minute} ${Number(hour)<12?'AM':'PM'}`;
    };
    const gradeSummary=period=>{
        if(!(period.applicable_groups||[]).includes('III'))return '<span class="text-secondary">All grades in M / A</span>';
        const grades=typeof period.full_day_grades==='string'?JSON.parse(period.full_day_grades):period.full_day_grades||[];
        return `<small class="text-secondary">Group III</small><div class="schedule-grade-badges">${grades.length?grades.map(grade=>`<span class="badge bg-purple-lt">${escape(grade)}</span>`).join(''):'<span class="text-secondary">No grades assigned</span>'}</div>`;
    };
    const controls=new Map();
    for(const name of ['starts_at','ends_at']){
        const input=form.elements[name],label=input.parentElement.querySelector('label');
        input.setAttribute('aria-label',label.textContent);
        const control=enhanceSubjectTemporal(input);label.htmlFor=control.input.id;controls.set(name,control);
    }
    const sync=()=>form.querySelectorAll('.premium-form-field').forEach(field=>{
        const input=field.querySelector('input:not([type="hidden"]),select');field.classList.toggle('has-value',Boolean(input?.value));
    });
    form.addEventListener('input',sync);form.addEventListener('change',sync);
    const api=async body=>{
        const response=await fetch(root.dataset.base,{method:body?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
        const result=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'Unable to load session periods.');return result;
    };
    async function load(){
        periods=(await api()).periods;
        get('[data-period-list]').innerHTML=periods.length?periods.map(period=>`<tr><td>${period.display_order}</td><td><span class="badge ${period.session==='morning'?'bg-azure-lt':'bg-purple-lt'}">${period.session==='morning'?'Morning':'Afternoon'}</span></td><td>${escape(period.name)}</td><td>${time(period.starts_at)} – ${time(period.ends_at)}</td><td>${(period.applicable_groups||[]).map(group=>`<span class="badge bg-blue-lt me-1">${escape(group)}</span>`).join('')}</td><td>${gradeSummary(period)}</td><td><span class="badge ${period.active?'bg-green-lt':'bg-secondary-lt'}">${period.active?'Active':'Inactive'}</span></td><td><button type="button" class="btn btn-sm" data-period-edit="${period.id}">Edit</button></td></tr>`).join(''):'<tr><td colspan="8">No periods configured.</td></tr>';
    }
    function open(period){
        form.reset();
        for(const name of ['id','version','name','session','display_order','active'])form.elements[name].value=period?.[name]??({version:0,session:'morning',display_order:periods.length+1,active:1}[name]??'');
        for(const [name,control] of controls)control.setValue(period?.[name]?.slice(0,5)||'');
        form.querySelectorAll('[name="applicable_groups[]"]').forEach(input=>input.checked=(period?.applicable_groups||[]).includes(input.value));
        const grades=period?(typeof period.full_day_grades==='string'?JSON.parse(period.full_day_grades):period.full_day_grades||[]):['N','K1','K2','K3',...Array.from({length:12},(_,index)=>String(index+1))];
        form.querySelectorAll('[name="full_day_grades[]"]').forEach(input=>input.checked=grades.includes(input.value));
        get('#schedulePeriodTitle').textContent=period?'Edit Shared Period':'Add Shared Period';sync();Modal.getOrCreateInstance(get('#schedulePeriodModal')).show();
    }
    get('[data-period-add]').addEventListener('click',()=>open());
    get('[data-period-list]').addEventListener('click',event=>{const button=event.target.closest('[data-period-edit]');if(button)open(periods.find(period=>period.id===Number(button.dataset.periodEdit)));});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||![...controls.values()].every(control=>control.valid()))return;
        busy=true;get('[data-period-save]').disabled=true;
        try{
            const body=Object.fromEntries(new FormData(form));if(!body.id)delete body.id;body.active=body.active==='1';
            body.applicable_groups=new FormData(form).getAll('applicable_groups[]');delete body['applicable_groups[]'];
            body.full_day_grades=new FormData(form).getAll('full_day_grades[]');delete body['full_day_grades[]'];
            await api(body);Modal.getOrCreateInstance(get('#schedulePeriodModal')).hide();await load();
        }catch(error){await Swal.fire({icon:'error',title:'Session Setup',text:error.message});}
        finally{busy=false;get('[data-period-save]').disabled=false;}
    });
    load().catch(error=>Swal.fire({icon:'error',title:'Session Setup',text:error.message}));
}
