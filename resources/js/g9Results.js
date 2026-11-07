import {Tooltip} from 'bootstrap';
import {setupStaffLocationSelect} from './helpers/staffBirthplaceSelect.js';
import {renderPagination} from './helpers/pagination.js';
import {setupG9PhotoViewer} from './helpers/g9PhotoViewer.js';

const root=document.querySelector('[data-g9-results]');
if(root){
    const get=selector=>root.querySelector(selector),form=get('#g9ResultFilters'),rows=get('[data-results-rows]');
    const error=get('[data-results-error]'),summary=get('[data-results-summary]'),context=get('[data-results-context]');
    const pagination=get('#g9-results-pagination'),perPage=get('#g9-results-per-page'),search=get('[data-results-search]');
    const tabs=[...root.querySelectorAll('[data-results-tab]')],mobile=matchMedia('(max-width:767.98px)'),combos=new Map();
    const order=['academic_year','exam_center','campus','room','grade','student'];
    const outcomes=[['passed','Passed','#269c65'],['failed','Failed','#d94c65'],['pending','Pending','#8670cc'],['absent','Absent','#c58325']];
    let active='results',sequence=0,controller=null,timer=null,report=null;
    const graphTips=new Set(),viewPhoto=setupG9PhotoViewer();
    const line=(parent,text,className='')=>{const el=document.createElement('div');el.className=className;el.textContent=text??'—';parent.append(el);return el;};
    const tooltip=element=>new Tooltip(element,{container:'body',customClass:'g9-results-tooltip',trigger:'hover focus',delay:{show:180,hide:0}});
    tabs.forEach(tooltip);
    const clearGraphTips=()=>{graphTips.forEach(tip=>tip.dispose());graphTips.clear();};
    const format=value=>value===null||value===undefined?'—':Number(value).toLocaleString(undefined,{maximumFractionDigits:3});
    const rate=value=>value===null||value===undefined?'—':format(value)+'%';
    const params=()=>{const query=new URLSearchParams(new FormData(form));query.set('search',search.value.trim());query.set('view',active);return query;};
    root.querySelectorAll('[data-results-export]').forEach(button=>button.addEventListener('click',async()=>{
        error.hidden=true;
        if(!form.elements.academic_year.value){error.textContent='Select an academic year before exporting a report.';error.hidden=false;return;}
        const format=button.dataset.format;
        const popup=format==='print'?window.open('about:blank','_blank'):null;
        if(format==='print'&&!popup){error.textContent='Allow pop-ups to print the report.';error.hidden=false;return;}
        if(popup){popup.opener=null;popup.document.title='Preparing result report';popup.document.body.textContent='Preparing result report…';}
        button.disabled=true;
        try{
            const query=params();query.delete('view');
            const response=await fetch(`${root.dataset.base}/reports/${button.dataset.resultsExport}/${format}?${query}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
            if([401,419].includes(response.status)||response.redirected)throw new Error('Your session expired. Refresh the page and sign in again.');
            if(!response.ok){const data=await response.json();throw new Error(Object.values(data.errors??{}).flat().join('\n')||data.message||'Unable to export the report.');}
            const expected=format==='print'?'text/html':format==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            if(!response.headers.get('Content-Type')?.includes(expected))throw new Error('The server returned an unexpected report. Refresh the page and try again.');
            const href=URL.createObjectURL(await response.blob());
            if(popup)popup.location.replace(href);
            else{const link=document.createElement('a');link.href=href;link.download=`g9-result-${button.dataset.resultsExport}.${format==='pdf'?'pdf':'xlsx'}`;document.body.append(link);link.click();link.remove();}
            setTimeout(()=>URL.revokeObjectURL(href),60000);
        }catch(failure){popup?.close();error.textContent=failure.message;error.hidden=false;}
        finally{button.disabled=false;}
    }));
    const sync=()=>combos.forEach(combo=>combo.sync());
    form.querySelectorAll('select').forEach(select=>{
        [...select.options].forEach(option=>option.dataset.en=option.textContent);
        const combo=setupStaffLocationSelect(form,select,{labelId:select.id+'Label',optionsId:select.id+'Options',locationLabel:select.getAttribute('aria-label')});combos.set(select,combo);
        const menu=select.nextElementSibling.querySelector('.location-combobox-menu'),input=menu.querySelector('input');menu.classList.add('g9-center-menu');
        const heading=document.createElement('div');heading.className='g9-center-menu-heading';heading.textContent=select.getAttribute('aria-label');menu.prepend(heading);
        const box=document.createElement('div'),icon=document.createElement('i');box.className='g9-center-search';icon.className='ti ti-search';icon.setAttribute('aria-hidden','true');input.before(box);box.append(icon,input);
        const position=()=>{
            const combo=select.nextElementSibling,rect=combo.getBoundingClientRect();
            let left=12,right=document.documentElement.clientWidth-12;
            // The app scrolls inside clipped containers as well as the viewport.
            for(let parent=combo.parentElement;parent;parent=parent.parentElement){
                if(!['auto','scroll','hidden','clip'].includes(getComputedStyle(parent).overflowX))continue;
                const bounds=parent.getBoundingClientRect(),edge=bounds.left+parent.clientLeft;
                left=Math.max(left,edge+8);right=Math.min(right,edge+parent.clientWidth-8);
            }
            const width=Math.min(520,Math.max(0,right-left));
            menu.style.width=width+'px';menu.style.left=(Math.max(left,Math.min(rect.left,right-width))-rect.left)+'px';
        };
        // Fit the wide menu before the picker opens and focuses its search box.
        // Otherwise focus can scroll the app frame sideways before positioning.
        combo.toggle.addEventListener('click',position,{capture:true});
        select.nextElementSibling.addEventListener('keydown',position,{capture:true});
        window.addEventListener('resize',position);
    });
    const fill=(name,values)=>{
        const select=form.elements[name],previous=select.value,empty=select.options[0].cloneNode(true);select.replaceChildren(empty);
        for(const value of values??[]){const option=new Option(typeof value==='object'?value.label:value,typeof value==='object'?value.id:value);option.dataset.en=option.textContent;select.append(option);}
        select.value=[...select.options].some(option=>option.value===previous)?previous:'';
    };
    const expandFilters=expanded=>{form.hidden=!expanded;get('[data-results-filter-toggle]').setAttribute('aria-expanded',String(expanded));get('[data-results-filter-toggle]').setAttribute('aria-label',(expanded?'Collapse':'Expand')+' filters');get('[data-results-filter-toggle] i').className='ti ti-chevron-'+(expanded?'up':'down');if(!expanded)combos.forEach(combo=>combo.close());};
    const expandNav=expanded=>{root.classList.toggle('is-collapsed',!expanded);get('[data-results-nav-toggle]').setAttribute('aria-expanded',String(expanded));get('[data-results-nav-toggle]').setAttribute('aria-label',(expanded?'Collapse':'Expand')+' result tabs');get('[data-results-nav-toggle] i').className='ti ti-layout-sidebar-left-'+(expanded?'collapse':'expand');};
    const responsive=()=>{expandFilters(!mobile.matches);expandNav(!mobile.matches);};responsive();mobile.addEventListener('change',responsive);
    get('[data-results-filter-toggle]').addEventListener('click',()=>expandFilters(form.hidden));
    get('[data-results-nav-toggle]').addEventListener('click',()=>expandNav(root.classList.contains('is-collapsed')));
    const empty=text=>{rows.replaceChildren();const cell=rows.insertRow().insertCell();cell.colSpan=9;cell.className='text-center text-secondary py-5';cell.textContent=text;};
    const clearReports=()=>{
        root.querySelectorAll('[data-results-bars],[data-results-legend],[data-results-grades],[data-results-statistics],[data-results-stat-total]').forEach(element=>element.replaceChildren());
        const donut=get('[data-results-donut]');if(donut){donut.style.background='var(--tblr-border-color)';donut.setAttribute('aria-label','No exam result data');get('[data-results-rate]').textContent='—';}
    };
    const request=async(path,signal)=>{
        const response=await fetch(root.dataset.base+path,{credentials:'same-origin',signal,headers:{Accept:'application/json'}});
        if([401,419].includes(response.status)||response.redirected)throw new Error('Your session expired. Refresh the page and sign in again.');
        const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors??{}).flat().join('\n')||data.message||'Unable to load exam results.');return data;
    };
    const renderSummary=data=>{
        summary.hidden=false;
        for(const key of ['total','passed','failed','absent']){
            get(`[data-result-metric="${key}"]`).textContent=format(data[key]);
        }
        get('[data-result-passed-percentage]').textContent=`(${rate(data.passed_percentage)})`;
        get('[data-result-failed-percentage]').textContent=`(${rate(data.failed_percentage)})`;
        for(const language of ['english','khmer']){
            get(`[data-result-absent-language="${language}"]`).textContent=format(data.absent_languages[language]);
            const candidates=data.candidate_languages[language];
            get(`[data-result-candidate-language="${language}"]`).textContent=format(candidates.total);
            get(`[data-result-candidate-language-percentage="${language}"]`).textContent=`(${rate(candidates.percentage)})`;
            const counts=data.passed_languages[language];
            get(`[data-result-passed-language="${language}"]`).textContent=format(counts.total);
            get(`[data-result-language-percentage="${language}"]`).textContent=`(${rate(counts.percentage)})`;
            const failed=data.failed_languages[language];
            get(`[data-result-failed-language="${language}"]`).textContent=format(failed.total);
            get(`[data-result-failed-language-percentage="${language}"]`).textContent=`(${rate(failed.percentage)})`;
        }
    };
    const renderRows=result=>{
        rows.replaceChildren();
        for(const [index,record] of result.data.entries()){
            const cells=[];
            const tr=rows.insertRow();for(let i=0;i<9;i++)cells[i]=tr.insertCell();
            line(cells[0],result.from+index,'g9-result-number');
            if(record.photo_url){const button=document.createElement('button'),img=document.createElement('img');button.type='button';button.className='g9-result-photo-button';button.setAttribute('aria-label','View photo of '+(record.name_en||record.student_code));img.className='g9-result-photo';img.src=record.photo_url;img.alt=record.name_en||'Student photo';img.loading='lazy';button.append(img);button.addEventListener('click',()=>viewPhoto(record));cells[1].append(button);}else line(cells[1],'—','text-secondary');
            line(cells[2],record.name_kh||record.name_en,'fw-semibold');line(cells[2],record.name_en,'small text-secondary');line(cells[2],record.student_code,'small text-secondary');line(cells[2],record.gender,'small text-secondary');
            line(cells[3],record.grade||'—','fw-semibold');line(cells[3],record.campus,'small text-secondary');
            line(cells[4],record.exam_center||'—');line(cells[4],record.exam_language||'—','small text-secondary');
            const badges=line(cells[4],'','d-flex gap-2 mt-1');line(badges,'Room: '+(record.room||'—'),'badge bg-blue-lt');line(badges,'Seat: '+(record.seat||'—'),'badge bg-purple-lt');
            line(cells[5],format(record.eoy),'fw-semibold');line(cells[6],format(record.total),'g9-result-score'+(record.outcome==='failed'?' is-failed':''));
            line(cells[7],record.result||'Pending','g9-result-outcome g9-result-'+record.outcome);
            if(record.outcome==='absent'&&record.attendance_reason)line(cells[7],record.attendance_reason,'small text-secondary');
            line(cells[8],record.letter_grade||'—','g9-result-outcome g9-result-'+record.outcome);
        }
        if(!result.data.length)empty('No candidates match the selected filters.');
        renderPagination(result,'g9-results-pagination','g9-results-per-page',page=>load(page),[25,50,75,100]);
    };
    const renderBars=()=>{
        const container=get('[data-results-bars]');if(!container||!report)return;clearGraphTips();container.replaceChildren();
        for(const group of report[get('[data-results-group]').value]??[]){
            const entry=document.createElement('div'),label=line(entry,'','g9-result-bar-label');line(label,group.label);line(label,`${group.total} candidates · ${rate(group.pass_rate)} passed`,'fw-semibold');
            const track=line(entry,'','g9-result-bar-track');
            for(const [key,title,color] of outcomes){if(!group[key])continue;const segment=document.createElement('button');segment.type='button';segment.className='g9-result-bar-segment';segment.style.width=(group[key]/group.total*100)+'%';segment.style.backgroundColor=color;segment.textContent=group[key]/group.total>=.12?group[key]:'';segment.title=`${group.label} · ${title}: ${group[key]} (${format(group[key]*100/group.total)}%)`;segment.setAttribute('aria-label',segment.title);track.append(segment);graphTips.add(tooltip(segment));}
            container.append(entry);
        }
        if(!container.children.length)line(container,'No candidates match the selected filters.','text-secondary py-4 text-center');
    };
    const renderGraph=()=>{
        const data=report.summary,donut=get('[data-results-donut]'),legend=get('[data-results-legend]');legend.replaceChildren();let start=0;const stops=[];
        for(const [key,label,color] of outcomes){const end=start+(data.total?data[key]/data.total*100:0);stops.push(`${color} ${start}% ${end}%`);start=end;const row=line(legend,'');const dot=document.createElement('span');dot.className='g9-result-dot';dot.style.backgroundColor=color;row.append(dot);line(row,label);line(row,format(data[key]),'fw-semibold');}
        donut.style.background=data.total?`conic-gradient(${stops.join(',')})`:'var(--tblr-border-color)';get('[data-results-rate]').textContent=rate(data.pass_rate);
        donut.setAttribute('aria-label',`Results for ${data.total} candidates. ${outcomes.map(([key,label])=>`${label}: ${data[key]}`).join('. ')}. Pass rate among completed results: ${rate(data.pass_rate)}.`);
        const grades=get('[data-results-grades]');grades.replaceChildren();data.grades.forEach((grade,index)=>line(grades,`${grade.label}: ${grade.total}`,'g9-result-outcome g9-result-'+['failed','pending','absent','passed'][index]));renderBars();
    };
    const renderStatistics=()=>{
        const target=get('[data-results-statistics]'),total=get('[data-results-stat-total]'),grouping=get('[data-results-stat-group]').value;target.replaceChildren();total.replaceChildren();get('[data-results-stat-heading]').textContent=grouping==='campuses'?'Campus':'Exam Center';
        const render=(parent,data,label,number)=>{
            const row=parent.insertRow();line(row.insertCell(),number,'g9-result-number');line(row.insertCell(),label,'fw-semibold');
            for(const key of ['total','passed','failed','pending','absent']){const cell=row.insertCell();line(cell,format(data[key]),'fw-semibold');const note=document.createElement('small');note.textContent='Female: '+data[key==='total'?'female':key+'_female'];cell.append(note);}
            line(row.insertCell(),rate(data.pass_rate),'fw-semibold');
        };
        (report[grouping]??[]).forEach((data,index)=>render(target,data,data.label,index+1));render(total,report.summary,'Total','Σ');
        if(!target.children.length){const cell=target.insertRow().insertCell();cell.colSpan=8;cell.className='text-center py-5 text-secondary';cell.textContent='No candidates match the selected filters.';}
    };
    async function load(page=1,refreshOptions=false){
        clearTimeout(timer);const current=++sequence;controller?.abort();controller=new AbortController();const signal=controller.signal;
        report=null;error.hidden=true;summary.hidden=true;context.hidden=false;pagination.replaceChildren();clearGraphTips();root.setAttribute('aria-busy','true');
        root.querySelectorAll('[data-results-export-toggle]').forEach(button=>button.disabled=true);
        root.querySelectorAll('[data-results-panel]').forEach(panel=>panel.style.opacity='.55');
        try{
            if(!form.elements.academic_year.value){order.slice(1).forEach(name=>fill(name,[]));sync();empty('Select an academic year to view exam results.');clearReports();context.textContent='Select an academic year to view exam results.';return;}
            context.textContent='Loading exam results…';
            if(refreshOptions){const choices=await request('/options?'+params(),signal);if(current!==sequence)return;order.slice(1).forEach(name=>fill(name,choices[name]));sync();}
            const query=params();query.set('page',page);query.set('per_page',perPage.value);const data=await request('/data?'+query,signal);if(current!==sequence)return;
            report=data;renderSummary(data.summary);context.textContent='';context.hidden=true;
            if(active==='results')renderRows(data.records);else if(active==='graph')renderGraph();else renderStatistics();
        }catch(failure){if(failure.name!=='AbortError'&&current===sequence){error.textContent=failure.message;error.hidden=false;context.textContent='Unable to load exam results.';empty('Unable to load exam results.');clearReports();}}
        finally{if(current===sequence){root.setAttribute('aria-busy','false');root.querySelectorAll('[data-results-panel]').forEach(panel=>panel.style.opacity='');root.querySelectorAll('[data-results-export-toggle]').forEach(button=>button.disabled=!report?.summary.total);}}
    }
    const selectTab=button=>{
        active=button.dataset.resultsTab;tabs.forEach(tab=>{const selected=tab===button;tab.setAttribute('aria-selected',String(selected));tab.tabIndex=selected?0:-1;});root.querySelectorAll('[data-results-panel]').forEach(panel=>panel.hidden=panel.dataset.resultsPanel!==active);load(1,true);
    };
    tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>selectTab(tab));tab.addEventListener('keydown',event=>{if(!['ArrowDown','ArrowUp','Home','End'].includes(event.key))return;event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(index+(event.key==='ArrowDown'?1:-1)+tabs.length)%tabs.length;tabs[next].focus();selectTab(tabs[next]);});});
    form.addEventListener('submit',event=>event.preventDefault());
    form.addEventListener('change',event=>{if(event.target===search)return;const index=order.indexOf(event.target.name);if(index>=0)order.slice(index+1).forEach(name=>form.elements[name].value='');sync();load(1,index>=0);});
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(1),300);});
    get('[data-results-group]')?.addEventListener('change',renderBars);get('[data-results-stat-group]')?.addEventListener('change',()=>{if(report)renderStatistics();});
    sync();load();
}
