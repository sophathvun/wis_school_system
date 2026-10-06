const locationPrefixes = {
    kh: /^(?:ភូមិ|ឃុំ|សង្កាត់|ស្រុក|ខណ្ឌ|ក្រុង|ខេត្ត|រាជធានី)\s*/u,
    en: /^(?:village|phum|commune|khum|sangkat|sk\.?|district|srok|sruk|khan|province|capital)\s+/i,
};

export function formatStudentBirthplace(student = {}, language = "kh") {
    const suffix = language === "kh" ? "kh" : "en";
    const province = student.birth_province;
    const phnomPenh = /phnom\s*penh/i.test((province ? province.province_name_en : student.birth_province_en) || "")
        || /ភ្នំពេញ/.test((province ? province.province_name_kh : student.birth_province_kh) || "");
    const labels = language === "kh"
        ? ["ភូមិ", phnomPenh ? "សង្កាត់" : "ឃុំ", phnomPenh ? "ខណ្ឌ" : "ស្រុក", phnomPenh ? "រាជធានី" : "ខេត្ត"]
        : ["Phum", phnomPenh ? "Sangkat" : "Khum", phnomPenh ? "Khan" : "Sruk", ""];
    return ["village", "commune", "district", "province"].map((type, index) => {
        const location = student[`birth_${type}`];
        let name = String((location ? location[`${type}_name_${suffix}`] : student[`birth_${type}_${suffix}`]) || "").trim();
        if (!name) return "";
        if (type === "province" && phnomPenh) name = suffix === "kh" ? "ភ្នំពេញ" : "Phnom Penh";
        // Imported location names may already include their administrative label.
        while (locationPrefixes[suffix].test(name)) name = name.replace(locationPrefixes[suffix], "").trim();
        if (suffix === "en" && type === "province") name = name.replace(/\s+(?:province|capital)$/i, "").trim();
        return name ? `${labels[index]}${suffix === "en" && labels[index] ? " " : ""}${name}` : "";
    }).filter(Boolean).join(language === "kh" ? " " : ", ") || "-";
}
