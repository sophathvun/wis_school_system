import { Modal } from 'bootstrap';
import { createSeatPicker } from './scholarshipSeatPicker.js';

export function initResultReportScope(root) {
    const element=root.querySelector('#scholarshipResultReportScope');if(!element)return;
    const modal=Modal.getOrCreateInstance(element),form=element.querySelector('form');
    const options=JSON.parse(root.querySelector('[data-result-report-options]').textContent);
    const center=form.elements.report_center,scope=form.elements.report_scope,grade=form.elements.report_grade,grades=form.querySelector('[name="report_grades[]"]'),pdfMode=form.elements.pdf_mode;
    const centerPicker=createSeatPicker(center),gradePicker=createSeatPicker(grade),gradesPicker=createSeatPicker(grades);
    const submit=form.querySelector('[data-result-report-open]');let exporting=false,hideAfterShow=false;
    centerPicker.setOptions(options);center.options[0].textContent='All Exam Centers';centerPicker.refresh();
    const clear=()=>{[grade,grades].forEach(select=>select.classList.remove('is-invalid'));form.querySelectorAll('[data-result-report-error]').forEach(node=>node.hidden=true);gradePicker.refresh();gradesPicker.refresh();};
    const refresh=()=>{
        form.querySelector('[data-result-report-single]').hidden=scope.value!=='single-grade';grade.disabled=scope.value!=='single-grade';
        form.querySelector('[data-result-report-multiple]').hidden=scope.value!=='selected-grades';grades.disabled=scope.value!=='selected-grades';
        clear();gradePicker.close();gradesPicker.close();
        submit.textContent=form.dataset.format==='print'?'Print':form.dataset.format==='xlsx'?'Download Excel':pdfMode.value==='separate'?'Download Grade PDFs (ZIP)':'Download PDF';
    };
    const loadGrades=(selected='')=>{
        const within=center.value===''?options:options.filter(item=>String(item.id)===center.value);
        const available=[...new Set(within.flatMap(item=>item.grades))].sort((a,b)=>a-b).map(id=>({id,label:'Grade '+id}));
        gradePicker.setOptions(available,selected);gradesPicker.setOptions(available,selected);refresh();
    };
    center.addEventListener('change',()=>loadGrades());scope.addEventListener('change',refresh);pdfMode.addEventListener('change',refresh);
    grade.addEventListener('change',clear);grades.addEventListener('change',clear);
    element.addEventListener('shown.bs.modal',()=>{if(hideAfterShow)modal.hide();});
    element.addEventListener('hidden.bs.modal',()=>{exporting=false;hideAfterShow=false;submit.disabled=false;});
    root.addEventListener('click',event=>{
        const link=event.target.closest('[data-result-report-link]');if(!link)return;event.preventDefault();
        form.action=link.href;form.dataset.format=link.dataset.resultReportFormat;
        center.value=root.querySelector('#resultFilterCenter')?.value??'';centerPicker.refresh();
        const selected=root.querySelector('#resultFilterGrade')?.value??'';scope.value=selected?'single-grade':'all-grades';
        pdfMode.value='combined';pdfMode.disabled=form.dataset.format!=='pdf';form.querySelector('[data-result-report-pdf]').hidden=pdfMode.disabled;
        form.elements.report_students.value='winners';
        form.querySelector('[data-result-report-help]').textContent=form.dataset.format==='xlsx'?'Each grade has its own worksheet: Grade 1, Grade 2, and so on. The Student Name filter does not limit the report.':form.dataset.format==='pdf'?'Combine all selected grades, or download a ZIP with a separate complete PDF for each grade.':'Each grade starts on a new A4 page. Winners appear first: 1st, 2nd, 3rd, then Best in Subjects.';
        loadGrades(selected);exporting=false;hideAfterShow=false;submit.disabled=false;modal.show();
    });
    form.addEventListener('submit',event=>{
        event.preventDefault();if(exporting)return;
        if(scope.value==='single-grade'&&!grade.value){grade.classList.add('is-invalid');form.querySelector('[data-result-report-error="single"]').hidden=false;gradePicker.refresh();gradePicker.button.focus();return;}
        if(scope.value==='selected-grades'&&!grades.selectedOptions.length){grades.classList.add('is-invalid');form.querySelector('[data-result-report-error="multiple"]').hidden=false;gradesPicker.refresh();gradesPicker.button.focus();return;}
        const url=new URL(form.action,location.href);url.search='';new FormData(form).forEach((value,key)=>url.searchParams.append(key,value));
        exporting=true;submit.disabled=true;
        if(form.dataset.format==='print')window.open(url,'_blank','noopener');else window.location.assign(url);
        centerPicker.close();gradePicker.close();gradesPicker.close();hideAfterShow=true;modal.hide();
    });
}
