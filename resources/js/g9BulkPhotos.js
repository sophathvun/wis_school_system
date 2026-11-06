import Swal from 'sweetalert2';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {setupG9PhotoUpload} from './helpers/g9PhotoUpload.js';
import {setupG9ImportProgress} from './helpers/g9ImportProgress.js';
import {setupG9ImportConfirmation} from './helpers/g9ImportConfirmation.js';

const root = document.querySelector('[data-g9-bulk-photos]');
if (root) {
    const form = root.querySelector('[data-photo-upload-form]'), year = form.elements.academic_year_id;
    const replace = form.elements.replace_existing;
    const previewButton = root.querySelector('[data-photo-preview]'), panel = root.querySelector('[data-photo-preview-panel]'), confirmButton = root.querySelector('[data-photo-confirm]');
    const progress = root.querySelector('[data-photo-progress]'), error = root.querySelector('[data-photo-error]'), success = root.querySelector('[data-photo-success]');
    let token = null, busy = false,readyCount=0;
    [...year.options].forEach(option => option.dataset.en = option.textContent);
    const combo = setupStaffLocationSelect(form, year, {labelId: 'g9PhotoYearLabel', optionsId: 'g9PhotoYearOptions', locationLabel: 'Academic Year'});
    combo.sync(); year.closest('.premium-form-field').classList.toggle('has-value', Boolean(year.value));
    const transfer=setupG9ImportProgress(form,{
        liveRows:true,
        checking:message=>({title:message.stage==='reading'?'Reading photo files':message.stage==='optimizing'?'Optimizing student photos':'Matching student photos',count:message.total?`${Number(message.processed).toLocaleString()} of ${Number(message.total).toLocaleString()} photos ${message.stage==='optimizing'?'optimized':'checked'}`:'',status:message.stage==='reading'?'Counting supported photos, including ZIP subfolders.':message.stage==='optimizing'?'Checking and optimizing each image before matching. No student photos are attached during preview.':'Matching photo filenames to candidates. Preparing your matching results.'}),
        uploadTitle:'Uploading photos',uploadLabel:'Student photo upload',uploadStatus:'Uploading your photos securely. Matching begins after the upload finishes.',
        processingTitle:'Matching student photos',processingLabel:'Preparing photo matching preview',processingValue:'Files uploaded. Photo matching and optimization are in progress.',
        processingStatus:'Checking filenames, matching candidates and optimizing images. Large photo collections may take longer. Please keep this page open.',
        previewLabel:'Photo matching preview',unavailable:'The photos could not be processed. Check file sizes and server upload limits.',
        failureStatus:'Your files are still selected. Check the error below and click Preview Photos to retry.',
        fileLabel:data=>{const count=data.getAll('files[]').length;return `${count.toLocaleString()} file${count===1?'':'s'} · Academic year ${year.selectedOptions[0]?.textContent??''}`;},
        completion:summary=>({review:summary.invalid+summary.unmatched+summary.duplicates>0,status:`${summary.images.toLocaleString()} images checked · ${summary.ready.toLocaleString()} ready to upload. Review photo matches below before confirming.`}),
    });
    const confirmation=setupG9ImportConfirmation(root.querySelector('[data-photo-confirm-progress-area]'),{
        title:'Saving student photos',finalizing:'Finalizing photo upload',unit:'photos',label:'Student photo saving progress',
        running:'Keep this page open while matched photos are saved. Changes are committed together when the upload succeeds.',finalStatus:'All matched photos processed. Finalizing the database transaction…',
        unavailable:'This photo batch is unavailable. Preview the photos again.',connectionError:'Photo upload completion could not be confirmed. Check the student photos before previewing again.',
        complete:'Photo upload completed',completion:summary=>`${summary.ready.toLocaleString()} student photos saved`,saved:'All matched student photos have been saved successfully.',
        failed:'Photo upload not completed',rollback:'No student photo changes were saved. Preview the photos again before retrying.',
    });
    const invalidate = () => {token = null;readyCount=0; panel.hidden = true; confirmButton.disabled = true; error.textContent = ''; success.textContent = '';progress.textContent='';transfer.reset();confirmation.reset();};
    const upload=setupG9PhotoUpload(form,{error,previewButton,onChange:invalidate});
    const lock = value => {
        busy = value;upload.setBusy(value);
        combo.toggle.disabled = value; if (replace) replace.disabled = value;
    };
    form.addEventListener('change',()=>{if(!busy)invalidate();});
    const cell = (row, value) => {const element = row.insertCell(); element.textContent = value || '-'; return element;};
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !previewButton) return;
        if (!year.value || !upload.files().length) {error.textContent = 'Select an academic year and choose photos or a ZIP file.'; return;}
        const body = new FormData(form);body.delete('files[]');upload.files().forEach(file=>body.append('files[]',file));
        invalidate(); lock(true);
        try {
            const data = await transfer.preview(root.dataset.base+'/preview',body); token = data.token;readyCount=data.summary.ready;
            const summary = root.querySelector('[data-photo-summary]'); summary.replaceChildren();
            [['images','Uploaded Images'],['ready','Ready'],['existing','Existing Preserved'],['unmatched','Unmatched Files'],['duplicates','Duplicate / Ambiguous'],['invalid','Invalid Images'],['missing','Missing Uploads']].forEach(([key,label]) => {
                const tile = document.createElement('div'), title = document.createElement('span'), count = document.createElement('strong'); title.textContent = label; count.textContent = data.summary[key]; tile.append(title,count); summary.append(tile);
            });
            const rows = root.querySelector('[data-photo-rows]'); rows.replaceChildren();
            data.rows.forEach(record => {
                const row = rows.insertRow(); cell(row, record.filename); cell(row, record.student_code); cell(row, [record.name_kh,record.name_en].filter(Boolean).join(' | '));
                const status = cell(row, ''); status.replaceChildren(); const badge = document.createElement('span'); badge.className = `badge bg-${record.status === 'ready' ? 'green' : record.status === 'existing' ? 'blue' : 'orange'}-lt`; badge.textContent = record.status; status.append(badge); cell(row, record.message);
            });
            const missing = root.querySelector('[data-photo-missing]'); missing.replaceChildren();
            data.missing.forEach(record => {const row = missing.insertRow(); cell(row,record.student_code); cell(row,record.name_en); cell(row,record.photo_filename);});
            panel.hidden = false; confirmButton.disabled = !data.summary.ready; progress.textContent = `${data.summary.ready} matched photos ready for confirmation.`;
            transfer.complete(data.summary);
        } catch (failure) {error.textContent = failure.message; progress.textContent = '';transfer.fail();}
        finally {lock(false);}
    });
    confirmButton.addEventListener('click', async () => {
        if (busy || !token) return;
        const previewToken=token;
        const approval = await Swal.fire({icon: 'question', title: 'Upload matched student photos?', text: 'Only ready photos will be attached to candidates in the selected academic year.', showCancelButton: true, confirmButtonText: 'Upload Photos'});
        if (!approval.isConfirmed||previewToken!==token) return;
        const body = new FormData(); body.set('token',token); lock(true); confirmButton.disabled = true; error.textContent = ''; progress.textContent = 'Saving matched photos...';
        try {const result = await confirmation.run(root.dataset.base+'/confirm',body,readyCount);token=null;progress.textContent='';success.textContent = `${result.summary.ready} student photos uploaded successfully.`;}
        catch (failure) {error.textContent = failure.message; progress.textContent = 'Preview the files again before retrying.'; token = null;}
        finally {lock(false);}
    });
}
