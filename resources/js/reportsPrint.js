document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const lang = document.documentElement.lang || 'en';
    const reportType = body.dataset.reportType || '';
    const printFormat = body.dataset.reportPrintFormat || 'internal';
    const reportDate = body.dataset.reportDate || '';
    const academicYear = body.dataset.reportAcademicYear || '';
    const campusKh = body.dataset.reportCampusKh || '';
    const campusEn = body.dataset.reportCampusEn || '';
    const campusAddress = body.dataset.reportCampusAddress || '';

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-report-action]');
        if (!button) return;
        if (button.dataset.reportAction === 'print') window.print();
        if (button.dataset.reportAction === 'close') window.close();
    });

    const khmerDigits = (value) => String(value ?? '').replace(/[0-9]/g, (digit) => '០១២៣៤៥៦៧៨៩'[digit]);

    const formatEnglishDate = (value) => {
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value || '';
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    };

    const updateEnglishStudentListHeader = () => {
        document.querySelectorAll('.a4-page .report-header').forEach((header) => {
            const title = header.querySelector('h1');
            const details = [...header.querySelectorAll('p')].find((item) => item.textContent.includes('|'));
            if (!title || !details) return;
            const parts = details.textContent.split('|').map((value) => value.trim());
            const year = parts[0] || academicYear || '';
            const campus = parts[1] || campusEn || '';
            const className = (parts[2] || '').replace(/^(Class|Grade)\s*/i, '');
            title.textContent = `${reportType === 'student-contact-list' ? 'Student Contact list' : 'Student List'} for Grade ${className}`;
            details.innerHTML = `Academic Year: ${year}<br>Campus: ${campus}`;

        });
    };

    const updateNameColumns = () => {
        document.querySelectorAll('.a4-page .report-table').forEach((table) => {
            const header = table.querySelector('thead tr');
            if (!header) return;
            const nameHeader = header.children[2];
            if (!nameHeader) return;

            const hasKhmerNameCells = lang !== 'km' && table.querySelector('.khmer');
            if (hasKhmerNameCells && !header.querySelector('.khmer-name-header')) {
                nameHeader.textContent = 'Full-Name';
                const khHeader = document.createElement('th');
                khHeader.textContent = 'នាមត្រកូល និងនាមខ្លួន';
                khHeader.className = 'khmer-name-header';
                header.insertBefore(khHeader, nameHeader.nextSibling);
                table.querySelectorAll('tbody tr').forEach((row) => {
                    const nameCell = row.children[2];
                    if (!nameCell) return;
                    const english = nameCell.querySelector('.english')?.textContent.trim() || '-';
                    const khmer = nameCell.querySelector('.khmer')?.textContent.trim() || '-';
                    nameCell.textContent = english;
                    const khmerCell = document.createElement('td');
                    khmerCell.className = 'left khmer-name';
                    khmerCell.textContent = khmer;
                    row.insertBefore(khmerCell, nameCell.nextSibling);
                });
                return;
            }

            if (lang === 'km') {
                nameHeader.textContent = 'នាមត្រកូល-នាមខ្លួន';
                nameHeader.classList.add('left');
                return;
            }

            if (nameHeader.textContent.trim() === 'Full-Name') return;
            nameHeader.textContent = 'Full-Name';
            nameHeader.classList.remove('left');
        });
    };

    const updateScoreHeaders = () => {
        document.querySelectorAll('.a4-page .report-table').forEach((table) => {
            const header = table.querySelector('thead tr');
            if (!header || header.children.length < 7) return;
            if (reportType === 'student-list' && lang === 'km') {
                if (header.children[4]) header.children[4].textContent = 'ថ្នាក់ទី';
                if (header.children[5]) header.children[5].textContent = 'ក្រុម';
                return;
            }
            if (reportType === 'student-contact-list' && lang === 'km') {
                if (header.children[4]) header.children[4].textContent = 'ក្រុម';
                return;
            }
            if (header.children[5]) header.children[5].textContent = 'Grade';
            const alreadyHasRemarks = [...header.children].some((cell) => ['Remarks', 'សម្គាល់'].includes(cell.textContent.trim()));
            if (!alreadyHasRemarks) {
                const remarks = document.createElement('th');
                remarks.textContent = 'Remarks';
                header.appendChild(remarks);
                table.querySelectorAll('tbody tr').forEach((row) => {
                    const cell = document.createElement('td');
                    cell.textContent = '';
                    row.appendChild(cell);
                });
            }
        });
    };

    const applyHeaderDecorations = () => {
        document.querySelectorAll('.report-header .motto').forEach((motto) => {
            if (!motto.querySelector('.tacteing-number')) {
                const number = document.createElement('div');
                number.className = 'tacteing-number';
                number.textContent = '5';
                motto.appendChild(number);
            }
        });
        document.querySelectorAll('.report-header .motto').forEach((motto) => {
            const number = motto.querySelector('.tacteing-number');
            if (number && number.parentElement !== motto.parentElement) {
                motto.parentElement.insertBefore(number, motto.nextSibling);
            }
        });
        document.querySelectorAll('.a4-page .report-header').forEach((header) => {
            const logo = header.querySelector('.logo');
            const school = header.querySelector('h2')?.textContent.trim() || '';
            const detail = [...header.querySelectorAll('p')].find((item) => item.textContent.includes('Campus:'));
            if (!logo) return;
            const campus = (detail?.textContent.match(/Campus:\s*([^\n]+)/i)?.[1] || campusEn || '').trim();
            if (!header.querySelector('.logo-caption')) {
                const caption = document.createElement('div');
                caption.className = 'logo-caption';
                caption.innerHTML = `<div class="logo-school">${school}</div><div>Campus/Branch: ${campus}</div>`;
                header.appendChild(caption);
            }
            const title = header.querySelector('h1');
            if (title && title.textContent.startsWith('List of Student of Grade')) {
                title.textContent = title.textContent.replace('List of Student of Grade', 'Student List for Grade');
            }
        });
    };

    const applyFooterStyles = () => {
        document.querySelectorAll('.a4-page .report-table').forEach((table) => {
            if (!table.nextElementSibling?.classList.contains('print-office-footer')) {
                const footer = document.createElement('div');
                footer.className = 'print-office-footer';
                footer.innerHTML = '<div>Registrar\'s Office</div><div>Date: &quot;January 15, 2026&quot; Printing Date</div>';
                table.insertAdjacentElement('afterend', footer);
            }
        });
    };

    const applyKhmerHeader = () => {
        if (lang !== 'km') return;
        document.querySelectorAll('.a4-page .report-header').forEach((header) => {
            const title = header.querySelector('h1');
            const details = [...header.querySelectorAll('p')].find((item) => item.textContent.includes('|'));
            if (!title || !details) return;
            const parts = details.textContent.split('|').map((value) => value.trim());
            const grade = (parts[2] || parts[1] || '').replace(/^(ថ្នាក់|Class|Grade)\s*/i, '').trim();
            details.dataset.gradeClass = grade;
            title.textContent = `${reportType === 'student-contact-list' ? 'បញ្ជីទំនាក់ទំនងសិស្សថ្នាក់ទី' : 'បញ្ជីឈ្មោះសិស្សថ្នាក់ទី'} ${grade}`;
            title.classList.add('moeys-title');
            details.textContent = parts[0] || '';
        });
    };

    const applyKhmerAcademicYear = () => {
        if (lang !== 'km') return;
        document.querySelectorAll('.a4-page .report-header').forEach((header) => {
            const title = header.querySelector('h1');
            const details = [...header.querySelectorAll('p')].find((item) => item.textContent.includes('|')) || header.querySelector('p[data-grade-class]');
            if (!title || !details) return;
            const grade = (details.dataset.gradeClass || details.textContent.split('|')[1] || '').replace(/^(ថ្នាក់|Class|Grade)\s*/i, '').trim();
            title.textContent = `${reportType === 'student-contact-list' ? 'បញ្ជីទំនាក់ទំនងសិស្សថ្នាក់ទី' : 'បញ្ជីឈ្មោះសិស្សថ្នាក់ទី'} ${grade}`;
            title.classList.add('moeys-title');
            details.textContent = `ឆ្នាំសិក្សា៖ ${khmerDigits(academicYear)}`;
            details.classList.add('moeys-academic-year');
            let campus = header.querySelector('.moeys-campus');
            if (!campus) {
                campus = document.createElement('p');
                campus.className = 'moeys-campus';
                const year = header.querySelector('.moeys-academic-year') || header.querySelector('p');
                if (year) year.insertAdjacentElement('afterend', campus);
                else header.appendChild(campus);
            }
            campus.textContent = campusKh;
        });
    };

    const moveKhmerCampus = () => {
        if (lang !== 'km') return;
        document.querySelectorAll('.a4-page .report-header').forEach((header) => {
            const campus = header.querySelector('.moeys-campus');
            const year = header.querySelector('.moeys-academic-year');
            if (campus && year) year.parentElement.insertBefore(campus, year);
        });
    };

    const applyKhmerDateFooter = () => {
        if (lang !== 'km') return;
        const digits = (value) => String(value ?? '').replace(/[0-9]/g, (digit) => '០១២៣៤៥៦៧៨៩'[digit]);
        const date = new Date(`${reportDate}T00:00:00`);
        const fallbackMonths = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
        const locationKh = window.resolveMoeysLocation ? window.resolveMoeysLocation(campusKh, campusAddress) : campusKh;
        const firstLine = window.formatKhmerLunarDate
            ? window.formatKhmerLunarDate(reportDate)
            : `ថ្ងៃ${['អាទិត្យ','ចន្ទ','អង្គារ','ពុធ','ព្រហស្បតិ៍','សុក្រ','សៅរ៍'][date.getDay()]} ព.ស.${digits(date.getFullYear() + 544)}`;
        const secondLine = window.formatKhmerSolarDate
            ? window.formatKhmerSolarDate(reportDate, locationKh)
            : `${locationKh} ថ្ងៃទី${digits(date.getDate())} ខែ${fallbackMonths[date.getMonth()] || ''} ឆ្នាំ${digits(date.getFullYear())}`;

        document.querySelectorAll('.print-office-footer, .student-list-date-footer').forEach((item) => item.remove());
        document.querySelectorAll('.a4-page .report-table').forEach((table) => {
            const footer = document.createElement('div');
            footer.className = 'moeys-date-footer';
            footer.innerHTML = `<div>${firstLine}</div><div>${secondLine}</div><div>នាយកសាលា</div>`;
            table.insertAdjacentElement('afterend', footer);
        });
    };

    if (lang !== 'km') {
        updateEnglishStudentListHeader();
        updateNameColumns();
        applyHeaderDecorations();
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    } else {
        updateNameColumns();
        updateScoreHeaders();
        applyHeaderDecorations();
        applyKhmerHeader();
        applyKhmerAcademicYear();
        moveKhmerCampus();
        applyKhmerDateFooter();
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    }
});
