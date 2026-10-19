import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import { createSeatPicker } from './scholarshipSeatPicker.js';

export function initSeatReportScope(root,getState) {
    const element=root.querySelector('#scholarshipSeatReportScope');if(!element)return;
    const modal=Modal.getOrCreateInstance(element),form=element.querySelector('form'),rooms=form.querySelector('[name="report_rooms[]"]');
    const picker=createSeatPicker(rooms),roomField=form.querySelector('[data-report-room-field]'),scope=form.elements.report_scope;
    const submit=form.querySelector('[data-seat-report-open]');let exporting=false,hideAfterShow=false;
    element.addEventListener('shown.bs.modal',()=>{if(hideAfterShow)modal.hide();});
    element.addEventListener('hidden.bs.modal',()=>{hideAfterShow=false;exporting=false;submit.disabled=false;});
    const refresh=()=>{const selected=scope.value==='selected-rooms';roomField.hidden=!selected;rooms.disabled=!selected;rooms.classList.remove('is-invalid');picker.refresh();picker.close();};
    scope.addEventListener('change',refresh);
    root.addEventListener('click',event=>{
        const link=event.target.closest('[data-seat-report-link]');if(!link)return;event.preventDefault();
        const {filters,options,loading}=getState();
        if(loading){Swal.fire({toast:true,position:'top-end',icon:'info',title:'Wait for the filters to finish loading.',showConfirmButton:false,timer:3000});return;}
        if(filters.center===null){Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Select an exam center first.',showConfirmButton:false,timer:3500});root.querySelector('#seatFilterCenterToggle')?.focus();return;}
        form.action=link.href;form.dataset.format=link.dataset.reportFormat;
        form.elements.report_center.value=filters.center;form.querySelector('#seatReportCenter').value=options.centers.find(item=>String(item.id)===String(filters.center))?.label||'';
        form.querySelector('[data-seat-report-document]').textContent=link.dataset.reportTitle;
        form.querySelector('[data-seat-report-open]').textContent=link.dataset.reportFormat==='print'?'Print':'Download '+(link.dataset.reportFormat==='xlsx'?'Excel':'PDF');
        picker.setOptions(options.rooms,filters.rooms);scope.value=filters.rooms.length?'selected-rooms':'all-rooms';refresh();hideAfterShow=false;exporting=false;submit.disabled=false;modal.show();
    });
    form.addEventListener('submit',event=>{
        event.preventDefault();
        if(exporting)return;
        if(scope.value==='selected-rooms'&&!rooms.selectedOptions.length){rooms.classList.add('is-invalid');picker.refresh();picker.button.focus();return;}
        exporting=true;submit.disabled=true;
        const url=new URL(form.action,location.href);new FormData(form).forEach((value,key)=>url.searchParams.append(key,value));
        if(form.dataset.format==='print')window.open(url,'_blank','noopener');else window.location.assign(url);
        picker.close();hideAfterShow=true;modal.hide();
    });
    rooms.addEventListener('change',()=>{rooms.classList.remove('is-invalid');picker.refresh();});
}
