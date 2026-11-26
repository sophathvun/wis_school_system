export function dailyAttendanceDateReason(date, calendar = {}) {
    if(!/^\d{4}-\d{2}-\d{2}$/.test(date||''))return null;
    if((calendar.holidays||[]).includes(date))return `Public holiday${calendar.holiday_descriptions?.[date]?`: ${calendar.holiday_descriptions[date]}`:''}`;
    const weekday=new Date(`${date}T12:00:00`).getDay();
    if((calendar.weekdays||[1,2,3,4,5]).includes(weekday))return null;
    if(weekday===0)return 'Sunday';
    if(weekday===6)return 'Saturday';
    return 'Non-school day';
}
