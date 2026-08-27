import momentkh from "@thyrith/momentkh";

const khmerDigits = (value) => String(value).replace(/[0-9]/g, (digit) => "០១២៣៤៥៦៧៨៩"[digit]);

window.formatKhmerLunarDate = (isoDate) => {
    const [year, month, day] = String(isoDate).split("-").map(Number);
    if (!year || !month || !day) return "";

    const result = momentkh.fromGregorian(year, month, day);
    const lunar = result.khmer;

    return `ថ្ងៃ${lunar.dayOfWeekName} ${khmerDigits(lunar.day)}${lunar.moonPhaseName} `
        + `ខែ${lunar.monthName} ឆ្នាំ${lunar.animalYearName} ${lunar.sakName} ព.ស.${khmerDigits(lunar.beYear)}`;
};

window.formatKhmerSolarDate = (isoDate, location = "") => {
    const [year, month, day] = String(isoDate).split("-").map(Number);
    if (!year || !month || !day) return "";
    const monthNames = ["មករា", "កុម្ភៈ", "មីនា", "មេសា", "ឧសភា", "មិថុនា", "កក្កដា", "សីហា", "កញ្ញា", "តុលា", "វិច្ឆិកា", "ធ្នូ"];
    return `${location} ថ្ងៃទី${khmerDigits(day)} ខែ${monthNames[month - 1]} ឆ្នាំ${khmerDigits(year)}`;
};

window.resolveMoeysLocation = (campusName = "", address = "") => {
    const value = `${campusName} ${address}`.toLowerCase();
    const locations = [
        ["កំពង់ចាម|kampong cham", "ខេត្តកំពង់ចាម"],
        ["ព្រះសីហនុ|sihanoukville|preah sihanouk", "ខេត្តព្រះសីហនុ"],
        ["កំពង់ស្ពឺ|kampong speu", "ខេត្តកំពង់ស្ពឺ"],
        ["កំពង់ឆ្នាំង|kampong chhnang", "ខេត្តកំពង់ឆ្នាំង"],
        ["កំពង់ធំ|kampong thom", "ខេត្តកំពង់ធំ"],
        ["កណ្ដាល|កណ្តាល|kandal", "ខេត្តកណ្ដាល"],
        ["កោះកុង|koh kong", "ខេត្តកោះកុង"],
        ["ក្រចេះ|kratie", "ខេត្តក្រចេះ"],
        ["តាកែវ|takeo", "ខេត្តតាកែវ"],
        ["បាត់ដំបង|battambang", "ខេត្តបាត់ដំបង"],
        ["បន្ទាយមានជ័យ|banteay meanchey", "ខេត្តបន្ទាយមានជ័យ"],
        ["ពោធិ៍សាត់|pursat", "ខេត្តពោធិ៍សាត់"],
        ["ព្រៃវែង|prey veng", "ខេត្តព្រៃវែង"],
        ["សៀមរាប|siem reap", "ខេត្តសៀមរាប"],
        ["ស្វាយរៀង|svay rieng", "ខេត្តស្វាយរៀង"],
        ["ស្ទឹងត្រែង|stung treng", "ខេត្តស្ទឹងត្រែង"],
        ["កំពត|kampot", "ខេត្តកំពត"],
        ["ប៉ៃលិន|pailin", "ខេត្តប៉ៃលិន"],
        ["ឧត្តរមានជ័យ|oddar meanchey", "ខេត្តឧត្តរមានជ័យ"],
        ["មណ្ឌលគិរី|mondulkiri", "ខេត្តមណ្ឌលគិរី"],
        ["រតនគិរី|ratanakiri", "ខេត្តរតនគិរី"],
        ["ត្បូងឃ្មុំ|tboung khmum", "ខេត្តត្បូងឃ្មុំ"],
        ["បឹងឈូក|bch", "រាជធានីភ្នំពេញ"],
        ["ភ្នំពេញ|phnom penh|រាជធានី", "រាជធានីភ្នំពេញ"],
    ];
    const found = locations.find(([pattern]) => new RegExp(pattern, "i").test(value));
    return found?.[1] || "រាជធានីភ្នំពេញ";
};
