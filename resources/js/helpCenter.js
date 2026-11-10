document.querySelectorAll('[data-help-print]').forEach(button=>{
    button.hidden=false;
    button.addEventListener('click',()=>window.print());
});
const body=document.querySelector('[data-help-body]'),toc=document.querySelector('[data-help-toc]');
if(body&&toc){
    const links=toc.querySelector('[data-help-toc-links]');
    body.querySelectorAll('h2').forEach((heading,index)=>{
        heading.id='guide-section-'+(index+1);
        const link=document.createElement('a');link.href='#'+heading.id;link.textContent=heading.textContent;links.append(link);
    });
    toc.hidden=!links.children.length;
    body.querySelectorAll('table').forEach(table=>{const wrapper=document.createElement('div');wrapper.className='help-table-scroll';wrapper.tabIndex=0;wrapper.setAttribute('role','region');wrapper.setAttribute('aria-label','Guide reference table');table.before(wrapper);wrapper.append(table);});
}
