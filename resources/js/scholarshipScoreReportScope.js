import { Modal } from 'bootstrap';
import { createSeatPicker } from './scholarshipSeatPicker.js';

export function initScoreReportScope(root) {
    const element=root.querySelector('#scholarshipScoreReportScope');
    if(!element)return;
    const options=JSON.parse(root.querySelector('[data-score-report-options]').textContent);
    const modal=Modal.getOrCreateInstance(element),form=element.querySelector('form');
    const center=form.elements.report_center,scope=form.elements.report_scope,room=form.elements.report_room,rooms=form.querySelector('[name="report_rooms[]"]'),pdfMode=form.elements.pdf_mode;
    const centerPicker=createSeatPicker(center),roomPicker=createSeatPicker(room),roomsPicker=createSeatPicker(rooms);
    const submit=form.querySelector('[data-score-report-open]');let exporting=false,hideAfterShow=false;
    centerPicker.setOptions(options);
    const clearError=(name,select,picker)=>{select.classList.remove('is-invalid');form.querySelector(`[data-score-report-error="${name}"]`).hidden=true;picker.refresh();};
    const showError=(name,select,picker)=>{select.classList.add('is-invalid');form.querySelector(`[data-score-report-error="${name}"]`).hidden=false;picker.refresh();picker.button.focus();};
    const refresh=()=>{
        const available=center.value!=='';
        form.querySelector('[data-score-report-single]').hidden=scope.value!=='single-room';
        form.querySelector('[data-score-report-multiple]').hidden=scope.value!=='selected-rooms';
        room.disabled=!available||scope.value!=='single-room';rooms.disabled=!available||scope.value!=='selected-rooms';
        clearError('report_room',room,roomPicker);clearError('report_rooms',rooms,roomsPicker);
        roomPicker.close();roomsPicker.close();
        submit.textContent=form.dataset.format==='print'?'Print':form.dataset.format==='xlsx'?'Download Excel':pdfMode.value==='separate'?'Download Room PDFs (ZIP)':'Download PDF';
    };
    const loadRooms=(selection='')=>{
        const available=options.find(option=>String(option.id)===center.value)?.rooms||[];
        roomPicker.setOptions(available,selection);roomsPicker.setOptions(available,selection);refresh();
    };
    center.addEventListener('change',()=>{clearError('report_center',center,centerPicker);loadRooms();});
    scope.addEventListener('change',refresh);pdfMode.addEventListener('change',refresh);
    room.addEventListener('change',()=>clearError('report_room',room,roomPicker));
    rooms.addEventListener('change',()=>clearError('report_rooms',rooms,roomsPicker));
    element.addEventListener('shown.bs.modal',()=>{if(hideAfterShow)modal.hide();});
    element.addEventListener('hidden.bs.modal',()=>{hideAfterShow=false;exporting=false;submit.disabled=false;});
    root.addEventListener('click',event=>{
        const link=event.target.closest('[data-score-report-link]');
        if(!link||link.getAttribute('aria-disabled')==='true')return;
        event.preventDefault();
        form.action=link.href;form.dataset.format=link.dataset.scoreReportFormat;
        center.value=root.querySelector('#scoreFilterCenter')?.value??'';
        clearError('report_center',center,centerPicker);
        const selectedRoom=root.querySelector('#scoreFilterRoom')?.value||'';
        scope.value=selectedRoom?'single-room':'all-rooms';
        pdfMode.value='combined';pdfMode.disabled=form.dataset.format!=='pdf';
        form.querySelector('[data-score-report-pdf]').hidden=pdfMode.disabled;
        form.querySelector('[data-score-report-format-help]').textContent=form.dataset.format==='xlsx'?'Each room has its own worksheet: ROOM 1, ROOM 2, and so on.':form.dataset.format==='pdf'?'Separate PDFs are downloaded together in one ZIP file.':'Each room starts on a new A4 page.';
        loadRooms(selectedRoom);hideAfterShow=false;exporting=false;submit.disabled=false;modal.show();
    });
    form.addEventListener('submit',event=>{
        event.preventDefault();if(exporting)return;
        if(center.value===''){showError('report_center',center,centerPicker);return;}
        if(scope.value==='single-room'&&!room.value){showError('report_room',room,roomPicker);return;}
        if(scope.value==='selected-rooms'&&!rooms.selectedOptions.length){showError('report_rooms',rooms,roomsPicker);return;}
        const url=new URL(form.action,location.href);url.search='';
        new FormData(form).forEach((value,key)=>url.searchParams.append(key,value));
        exporting=true;submit.disabled=true;
        if(form.dataset.format==='print')window.open(url,'_blank','noopener');else window.location.assign(url);
        centerPicker.close();roomPicker.close();roomsPicker.close();hideAfterShow=true;modal.hide();
    });
}
