export function formatGepCambodiaPhone(value='') {
    let digits=String(value).replace(/\D/g,'');
    if(digits.startsWith('855'))digits=digits.slice(3);
    if(digits.startsWith('0'))digits=digits.slice(1);
    return [digits.slice(0,2),digits.slice(2,5),digits.slice(5)].filter(Boolean).join(' ');
}

export function gepPhoneValue(value='',dialCode='855') {
    const raw=String(value).trim(),digits=raw.replace(/\D/g,'');
    if(!digits)return '';
    if(raw.startsWith('+'))return `+${digits}`;
    let local=digits;
    if(dialCode==='855'){
        if(local.startsWith('855'))local=local.slice(3);
        if(local.startsWith('0'))local=local.slice(1);
    }
    return local?`+${dialCode}${local}`:'';
}
