const khmerDigits = ['០','១','២','៣','៤','៥','៦','៧','៨','៩'];
export const toKhmerDigits = (value) => String(value ?? '').replace(/\d/g, digit => khmerDigits[Number(digit)]);
export const genderKhmer = (value) => ({male:'ប្រុស',female:'ស្រី',other:'ផ្សេងៗ'}[String(value || '').toLowerCase()] || '');
export const maritalKhmer = (value) => ({single:'នៅលីវ',married:'រៀបការ',divorced:'លែងលះ',widowed:'មេម៉ាយ / ពោះម៉ាយ'}[String(value || '').toLowerCase()] || '');
export function dateKhmer(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return '';
    const [year,month,day] = value.split('-').map(Number);
    const months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
    return months[month-1] ? toKhmerDigits(`${String(day).padStart(2,'0')} ${months[month-1]} ${year}`) : '';
}
export function nationalityNote(english, khmer = '') {
    const value = String(english || '').trim().toLowerCase();
    if (!value && !khmer.trim()) return '';
    return ['cambodian','cambodia','khmer','kh','khm'].includes(value) || (!value && ['ខ្មែរ','កម្ពុជា'].includes(khmer.trim())) ? 'Cambodian' : 'International';
}
