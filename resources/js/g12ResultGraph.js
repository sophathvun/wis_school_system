const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const percent = (count, total) => total ? count * 100 / total : 0;
const rate = (count, total) => percent(count, total).toFixed(1);
const result = student => {
    const grade = String(student.overall_grade || '').trim().toUpperCase();
    return 'ABCDE'.includes(grade) && grade ? 'passed' : grade === 'F' ? 'failed' : 'pending';
};
const summarize = students => students.reduce((counts, student) => { counts[result(student)]++; return counts; }, {passed:0, failed:0, pending:0});

export function initG12ResultGraph(root) {
    const target = root.querySelector('[data-g12-report-summary="graph"]');
    if (!target) return;
    target.setAttribute('aria-live', 'polite');
    const form = root.querySelector('[data-g12-filters]');
    let controller, timer, exporting=false, ready=false;
    const exportButtons=root.querySelectorAll('[data-g12-graph-export]');
    const syncExports=()=>exportButtons.forEach(button=>button.disabled=!ready||exporting);
    const active = () => !root.querySelector('[data-g12-report-panel="graph"]').hidden;

    async function load() {
        if (!active()) return;
        controller?.abort();
        ready=false;syncExports();
        controller = new AbortController();
        const signal = controller.signal;
        const query = new URLSearchParams(new FormData(form));
        if (!query.get('academic_year')) {
            target.innerHTML = '<div class="g12-graph-empty"><i class="ti ti-chart-donut" aria-hidden="true"></i><h3>Your results at a glance</h3><p>Select an Academic Year to explore pass rates, campus performance, and letter grades.</p></div>';
            return;
        }
        target.setAttribute('aria-busy', 'true');
        target.innerHTML = '<div class="g12-graph-empty"><span class="spinner-border text-primary" role="status" aria-label="Loading graph"></span><p>Preparing examination insights…</p></div>';
        query.set('tab','reports'); query.set('per_page','all');
        try {
            const response = await fetch(`${root.dataset.base}/list?${query}`, {credentials:'same-origin', signal, headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Unable to load examination results. Please try again.');
            const payload = await response.json();
            if (signal.aborted) return;
            render(payload.data, query.get('academic_year'));
            ready=payload.data.length>0;syncExports();
        } catch (error) {
            if (error.name !== 'AbortError') target.innerHTML = `<div class="g12-graph-empty"><h3>Could not load graph</h3><p>${escape(error.message)}</p><button class="btn btn-primary" type="button" data-graph-retry>Retry</button></div>`;
        } finally { if (!signal.aborted) target.removeAttribute('aria-busy'); }
    }

    function render(students, year) {
        if (!students.length) {
            target.innerHTML = '<div class="g12-graph-empty"><i class="ti ti-filter-off" aria-hidden="true"></i><h3>No matching candidates</h3><p>Adjust the campus, student, or search filters to see results.</p></div>';
            return;
        }
        const counts = summarize(students);
        const languageOf = student => {
            const language=String(student.language_exam||'').trim().toLowerCase();
            return language.includes('english')||language.includes('អង់គ្លេស')?'English':language.includes('khmer')||language.includes('ខ្មែរ')?'Khmer':null;
        };
        const streamOf = student => {
            const stream=String(student.class_selected||'').trim().toLowerCase();
            return stream.includes('social')||stream.includes('សង្គម')?'Social Science':stream.includes('science')||stream.includes('វិទ្យាសាស្ត្រ')?'Science':null;
        };
        const breakdown = (label,list,icon) => {
            const c=summarize(list),graded=c.passed+c.failed;
            return `<article class="g12-graph-breakdown"><div class="g12-graph-breakdown-heading"><span class="g12-graph-icon g12-graph-total"><i class="ti ${icon}" aria-hidden="true"></i></span><div><h4>${label}</h4><span>${list.length} candidates · ${rate(list.length,students.length)}% of total</span></div></div><div class="g12-graph-breakdown-rate"><strong>${graded?rate(c.passed,graded)+'%':'—'}</strong><span>pass rate</span></div><div class="g12-graph-stack" role="img" aria-label="${label}: ${c.passed} passed, ${c.failed} failed, ${c.pending} pending">${['passed','failed','pending'].map(key=>`<span class="g12-graph-${key}" style="width:${percent(c[key],list.length)}%"></span>`).join('')}</div><div class="g12-graph-campus-notes"><span>Passed <b>${c.passed}</b></span><span>Failed <b>${c.failed}</b></span><span>Pending <b>${c.pending}</b></span></div></article>`;
        };
        const languageCards=['Khmer','English'].map(language=>breakdown(language,students.filter(student=>languageOf(student)===language),'ti-language')).join('');
        const streamCards=[['Science','Khmer'],['Science','English'],['Social Science','Khmer'],['Social Science','English']].map(([stream,language])=>breakdown(`${stream}–${language}`,students.filter(student=>languageOf(student)===language&&streamOf(student)===stream),stream==='Science'?'ti-flask':'ti-books')).join('');
        const streamGrades=['Science','Social Science'].map(stream=>{
            const candidates=students.filter(student=>streamOf(student)===stream);
            const results=summarize(candidates),graded=results.passed+results.failed;
            return `<section class="g12-graph-card"><header><h3><i class="ti ${stream==='Science'?'ti-flask':'ti-books'} me-2" aria-hidden="true"></i>${stream}</h3><p>${candidates.length} candidates · ${graded} graded · ${results.pending} pending</p></header><div class="g12-graph-stream-grade-list">${'ABCDEF'.split('').map(grade=>{
                const count=candidates.filter(student=>String(student.overall_grade||'').trim().toUpperCase()===grade).length;
                return `<div class="g12-graph-stream-grade grade-${grade.toLowerCase()}"><strong>${grade}</strong><div class="g12-graph-stream-grade-track"><span style="width:${percent(count,graded)}%"></span></div><b>${count}</b><small>${rate(count,graded)}%</small></div>`;
            }).join('')}</div></section>`;
        }).join('');
        const unclassified=students.filter(student=>!languageOf(student)||!streamOf(student)).length;
        const examined = counts.passed + counts.failed;
        const grades = Object.fromEntries('ABCDEF'.split('').map(grade => [grade, students.filter(student => String(student.overall_grade || '').toUpperCase() === grade).length]));
        const campuses = new Map();
        students.forEach(student => {
            const campus = student.campus || 'Unassigned';
            if (!campuses.has(campus)) campuses.set(campus, []);
            campuses.get(campus).push(student);
        });
        const circumference = 2 * Math.PI * 78;
        let offset = 0;
        const arcs = ['passed','failed','pending'].map(key => {
            const length = counts[key] / students.length * circumference;
            const arc = `<circle class="g12-graph-${key}" cx="110" cy="110" r="78" fill="none" stroke-width="19" stroke-dasharray="${length} ${circumference-length}" stroke-dashoffset="${-offset}"/>`;
            offset += length; return arc;
        }).join('');
        const cards = [
            ['Candidates',students.length,'ti-users','total','All matching exam records'],
            ['Passed',counts.passed,'ti-circle-check','passed',`${rate(counts.passed,examined)}% of graded candidates`],
            ['Failed',counts.failed,'ti-circle-x','failed',`${rate(counts.failed,examined)}% of graded candidates`],
            ['Pending',counts.pending,'ti-clock','pending','Awaiting a letter grade'],
        ];
        target.innerHTML = `
            <div class="g12-graph-banner"><div><div class="g12-graph-eyebrow">EXAMINATION INSIGHTS</div><h2>Every result, a clearer picture</h2><p>Grade 12 National Examination · ${escape(year)}</p></div><span class="g12-graph-year"><i class="ti ti-calendar" aria-hidden="true"></i>${escape(year)}</span></div>
            <div class="g12-graph-kpis">${cards.map(([label,count,icon,key,note])=>`<article class="g12-graph-kpi g12-graph-${key}"><span class="g12-graph-icon"><i class="ti ${icon}" aria-hidden="true"></i></span><div><span>${label}</span><strong>${count.toLocaleString()}</strong><small>${note}</small></div></article>`).join('')}</div>
            <section class="g12-graph-card g12-graph-breakdown-section"><header><h3>Exam Language</h3><p>Khmer and English candidates, results, and pass rates</p></header><div class="g12-graph-language-grid">${languageCards}</div></section>
            <section class="g12-graph-card g12-graph-breakdown-section"><header><h3>Science &amp; Social Science</h3><p>Compare each selection by examination language</p></header><div class="g12-graph-stream-grid">${streamCards}</div>${unclassified?`<p class="g12-graph-footnote">${unclassified} candidates have missing or unrecognized language or selection. They remain included in the overall totals.</p>`:''}</section>
            <div class="g12-graph-stream-grades">${streamGrades}</div>
            <div class="g12-graph-main">
                <section class="g12-graph-card"><header><h3>Result Overview</h3><p>Pass rate among candidates with a final grade</p></header><div class="g12-graph-donut"><svg viewBox="0 0 220 220" role="img" aria-label="${counts.passed} passed, ${counts.failed} failed, ${counts.pending} pending"><g transform="rotate(-90 110 110)">${arcs}</g><text x="110" y="106" text-anchor="middle" class="g12-graph-rate">${examined?rate(counts.passed,examined)+'%':'—'}</text><text x="110" y="130" text-anchor="middle" class="g12-graph-rate-label">PASS RATE</text></svg></div><div class="g12-graph-legend">${['passed','failed','pending'].map(key=>`<span><i class="g12-graph-dot g12-graph-${key}"></i>${key[0].toUpperCase()+key.slice(1)} <b>${counts[key]}</b></span>`).join('')}</div><p class="g12-graph-footnote">Pending results are excluded from the pass rate.</p></section>
                <section class="g12-graph-card"><header><h3>Campus Performance</h3><p>Result mix and pass rate by campus</p></header><div class="g12-graph-campus-list">${[...campuses].sort(([a],[b])=>a.localeCompare(b)).map(([campus,list])=>{
                    const c=summarize(list), graded=c.passed+c.failed;
                    return `<div class="g12-graph-campus"><div class="g12-graph-campus-title"><strong>${escape(campus)}</strong><span>${graded?rate(c.passed,graded)+'% passed':'Awaiting results'} · ${list.length} candidates</span></div><div class="g12-graph-stack" role="img" aria-label="${escape(campus)}: ${c.passed} passed, ${c.failed} failed, ${c.pending} pending">${['passed','failed','pending'].map(key=>`<span class="g12-graph-${key}" style="width:${percent(c[key],list.length)}%"></span>`).join('')}</div><div class="g12-graph-campus-notes"><span>Passed <b>${c.passed}</b></span><span>Failed <b>${c.failed}</b></span><span>Pending <b>${c.pending}</b></span></div></div>`;
                }).join('')}</div></section>
            </div>
            <section class="g12-graph-card"><header><h3>Letter Grade Distribution</h3><p>Share of ${examined} candidates with a final grade</p></header><div class="g12-graph-grades">${Object.entries(grades).map(([grade,count])=>`<article class="g12-graph-grade grade-${grade.toLowerCase()}"><span>${grade}</span><strong>${count}</strong><small>${rate(count,examined)}%</small><div class="g12-graph-grade-track"><i style="width:${percent(count,examined)}%"></i></div></article>`).join('')}</div></section>`;
    }

    root.addEventListener('g12-graph-open', load);
    const refresh = () => { clearTimeout(timer); timer = setTimeout(load, 300); };
    form.addEventListener('change', refresh);
    form.querySelector('[name="search"]')?.addEventListener('input', refresh);
    target.addEventListener('click', event => { if (event.target.closest('[data-graph-retry]')) void load(); });
    exportButtons.forEach(button=>button.addEventListener('click',async()=>{
        if(!ready||exporting)return;
        exporting=true;syncExports();
        const original=button.innerHTML;button.textContent='Preparing…';
        try{
            await document.fonts.ready;
            const {toCanvas}=await import('html-to-image');
            const campusList=target.querySelector('.g12-graph-campus-list');
            const oldMax=campusList.style.maxHeight,oldOverflow=campusList.style.overflow;
            let canvas;
            try{
                campusList.style.maxHeight='none';campusList.style.overflow='visible';
                canvas=await toCanvas(target,{pixelRatio:2,backgroundColor:getComputedStyle(target).backgroundColor==='rgba(0, 0, 0, 0)'?getComputedStyle(document.body).backgroundColor:getComputedStyle(target).backgroundColor});
            }finally{campusList.style.maxHeight=oldMax;campusList.style.overflow=oldOverflow;}
            let blob;
            const format=button.dataset.g12GraphExport;
            if(format==='image')blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
            else{
                const data=new FormData();data.append('image',await new Promise(resolve=>canvas.toBlob(resolve,'image/png')),'graph.png');
                const response=await fetch(`${root.dataset.base}/graph/pdf`,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data});
                if(!response.ok){const error=await response.json().catch(()=>({}));throw new Error(error.message||'Unable to generate PDF. Please try again.');}
                blob=await response.blob();
            }
            if(!blob)throw new Error('Unable to create the export.');
            const url=URL.createObjectURL(blob),link=document.createElement('a');
            link.href=url;link.download=`g12-results-dashboard-${form.elements.academic_year.value}.${format==='image'?'png':'pdf'}`;link.click();setTimeout(()=>URL.revokeObjectURL(url),60000);
        }catch(error){const {default:Swal}=await import('sweetalert2');await Swal.fire({icon:'error',title:'Export Dashboard',text:error.message});}
        finally{exporting=false;button.innerHTML=original;syncExports();}
    }));
}
